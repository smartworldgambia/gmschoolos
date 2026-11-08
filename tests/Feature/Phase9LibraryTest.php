<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\LibraryBook;
use App\Models\LibraryCopy;
use App\Models\LibraryFine;
use App\Models\LibraryLoan;
use App\Models\User;
use App\Services\Library\LibraryService;

/** Phase 9C: catalogue, copies, ISBN, lending rules, renewals, returns, fines and history. */
class Phase9LibraryTest extends Phase9TestCase
{
    private User $librarian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->librarian = $this->user('librarian');
    }

    private function book(int $copies = 2, array $over = []): LibraryBook
    {
        return $this->inSchool(fn () => app(LibraryService::class)->addBook($over + ['title' => 'Things Fall Apart'], $copies));
    }

    private function lend(LibraryBook $book, $student, array $over = [])
    {
        return $this->actAs($this->librarian)->post(route('library.loans.store'), $over + ['library_book_id' => $book->id, 'student_id' => $student->id]);
    }

    private function loanOf(LibraryBook $book): LibraryLoan
    {
        return $this->inSchool(fn () => LibraryLoan::query()->where('library_book_id', $book->id)->latest('id')->firstOrFail());
    }

    private function backdate(LibraryLoan $loan, int $daysLate): void
    {
        \DB::table('library_loans')->where('id', $loan->id)->update(['loaned_on' => now()->subDays($daysLate + 14)->toDateString(), 'due_on' => now()->subDays($daysLate)->toDateString()]);
    }

    // ------------------------------------------------------------------ access and isolation

    public function test_only_library_staff_can_use_the_library(): void
    {
        $parent = $this->user('parent');
        foreach ([$this->teacher, $parent] as $u) {
            foreach (['library.index', 'library.loans.index', 'library.fines.index', 'library.setup.index'] as $r) {
                $this->actAs($u)->get(route($r))->assertForbidden();
            }
            $this->actAs($u)->post(route('library.books.store'), ['title' => 'X', 'copies_total' => 1])->assertForbidden();
        }
        $this->actAs($this->librarian)->get(route('library.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Library/Index'));
        $this->actAs($this->librarian)->get(route('library.loans.index'))->assertOk()->assertInertia(fn ($p) => $p->component('Library/Loans'));
    }

    public function test_another_schools_records_are_invisible_and_foreign_ids_refused(): void
    {
        $other = $this->otherSchool();
        $foreignBook = $this->inSchool(fn () => app(LibraryService::class)->addBook(['title' => 'Foreign'], 1), $other);
        $foreignStudent = $this->student(null, false, 'Far', $other);
        $book = $this->book();
        $mine = $this->student();

        $this->actAs($this->librarian)->get(route('library.books.show', $foreignBook->id))->assertNotFound();
        $this->actAs($this->librarian)->post(route('library.copies.store', $foreignBook->id), ['count' => 1])->assertNotFound();
        $this->lend($foreignBook, $mine)->assertSessionHasErrors('library_book_id');
        $this->lend($book, $foreignStudent)->assertSessionHasErrors('student_id');
        $this->assertSame(0, \DB::table('library_loans')->count());
    }

    // ------------------------------------------------------------------ catalogue, copies, ISBN

    public function test_a_title_gets_real_copies_with_accession_numbers(): void
    {
        $this->actAs($this->librarian)->post(route('library.books.store'), ['title' => 'Arrow of God', 'author' => 'Chinua Achebe', 'copies_total' => 3])->assertRedirect()->assertSessionHas('success', 'ok.lib.bookSaved');
        $book = LibraryBook::query()->withoutGlobalScopes()->where('title', 'Arrow of God')->firstOrFail();
        $copies = LibraryCopy::query()->withoutGlobalScopes()->where('library_book_id', $book->id)->orderBy('id')->get();

        $this->assertCount(3, $copies);
        $this->assertSame(['B'.$book->id.'-001', 'B'.$book->id.'-002', 'B'.$book->id.'-003'], $copies->pluck('accession_number')->all());
        $this->assertSame([3, 3], [(int) $book->copies_total, (int) $book->copies_available]);

        $this->actAs($this->librarian)->post(route('library.copies.store', $book->id), ['count' => 2])->assertSessionHas('success', 'ok.lib.copiesAdded');
        $this->assertSame(5, \DB::table('library_copies')->where('library_book_id', $book->id)->count());
        $this->assertSame(5, (int) $book->fresh()->copies_available);
        $this->actAs($this->librarian)->post(route('library.copies.store', $book->id), ['count' => 500])->assertSessionHasErrors('count');
        $this->assertTrue(ActivityLog::query()->withoutGlobalScopes()->where('event', 'lib.book.created')->exists());
    }

    public function test_isbn_is_checked_normalised_and_unique(): void
    {
        $post = fn (string $isbn, string $title = 'T') => $this->actAs($this->librarian)->post(route('library.books.store'), ['title' => $title, 'isbn' => $isbn, 'copies_total' => 1]);

        $post('978-0-306-40615-7')->assertSessionHas('success');                  // valid ISBN-13, hyphens stripped
        $this->assertSame('9780306406157', \DB::table('library_books')->value('isbn'));
        $post('9780306406157', 'Again')->assertSessionHas('error', 'err.lib.isbnExists');
        $post('978-0-306-40615-8', 'Bad13')->assertSessionHas('error', 'err.lib.isbn');
        $post('0-306-40615-2', 'Ten')->assertSessionHas('success');               // valid ISBN-10
        $post('0-306-40615-3', 'Bad10')->assertSessionHas('error', 'err.lib.isbn');
        $post('', 'NoIsbn')->assertSessionHas('success');                          // an ISBN is optional
        $this->assertSame(3, \DB::table('library_books')->count());
    }

    public function test_a_lost_or_damaged_copy_cannot_be_lent_and_an_out_copy_cannot_be_edited(): void
    {
        $book = $this->book(2);
        $student = $this->student();
        $copy = $this->inSchool(fn () => LibraryCopy::query()->where('library_book_id', $book->id)->orderBy('id')->firstOrFail());

        $this->actAs($this->librarian)->put(route('library.copies.status', $copy->id), ['status' => 'damaged', 'note' => 'Torn cover'])->assertSessionHas('success', 'ok.lib.copySaved');
        $this->assertSame(1, (int) $book->fresh()->copies_available);

        $this->lend($book, $student)->assertSessionHas('success');
        $this->assertSame(0, (int) $book->fresh()->copies_available);
        $out = $this->loanOf($book)->copy;
        $this->actAs($this->librarian)->put(route('library.copies.status', $out->id), ['status' => 'withdrawn'])->assertSessionHas('error', 'err.lib.copyOnLoan');
        $this->actAs($this->librarian)->put(route('library.copies.status', $copy->id), ['status' => 'bogus'])->assertSessionHasErrors('status');
    }

    // ------------------------------------------------------------------ lending

    public function test_lending_uses_a_specific_copy_and_keeps_the_counters_true(): void
    {
        $book = $this->book(2);
        $s1 = $this->student();
        $s2 = $this->student();
        $s3 = $this->student();

        $this->lend($book, $s1)->assertSessionHas('success', 'ok.lib.loanRecorded');
        $loan = $this->loanOf($book);
        $this->assertNotNull($loan->library_copy_id);
        $this->assertSame('on_loan', \DB::table('library_copies')->where('id', $loan->library_copy_id)->value('status'));
        $this->assertSame($this->librarian->id, (int) $loan->issued_by);
        $this->assertSame(now()->addDays(14)->toDateString(), $loan->due_on->toDateString());   // the school's loan period

        $this->lend($book, $s2)->assertSessionHas('success');
        $this->assertSame(0, (int) $book->fresh()->copies_available);
        $this->lend($book, $s3)->assertSessionHas('error', 'err.lib.noCopy');
        $this->assertSame(2, \DB::table('library_loans')->where('status', 'out')->count());

        $this->assertTrue(ActivityLog::query()->withoutGlobalScopes()->where('event', 'lib.loan.issued')->exists());
    }

    public function test_one_copy_per_borrower_per_title_and_inactive_borrowers_are_refused(): void
    {
        $book = $this->book(3);
        $student = $this->student();
        $gone = $this->student();
        $gone->update(['status' => 'withdrawn']);

        $this->lend($book, $student)->assertSessionHas('success');
        $this->lend($book, $student)->assertSessionHas('error', 'err.lib.alreadyHas');
        $this->lend($book, $gone)->assertSessionHas('error', 'err.lib.borrower');
        $this->lend($book, $student, ['due_on' => now()->subDay()->toDateString()])->assertSessionHas('error');
        $this->assertSame(1, \DB::table('library_loans')->count());
    }

    public function test_lending_limits_and_the_overdue_block_follow_the_schools_rules(): void
    {
        $student = $this->student();
        $books = [$this->book(1, ['title' => 'A']), $this->book(1, ['title' => 'B']), $this->book(1, ['title' => 'C']), $this->book(1, ['title' => 'D'])];

        $this->setSchoolSettings(['ops' => ['library' => ['max_loans_student' => 2]]]);
        $this->school->refresh();
        $this->lend($books[0], $student)->assertSessionHas('success');
        $this->lend($books[1], $student)->assertSessionHas('success');
        $this->lend($books[2], $student)->assertSessionHas('error', 'err.lib.limit');

        // 0 means no limit
        $this->setSchoolSettings(['ops' => ['library' => ['max_loans_student' => 0]]]);
        $this->school->refresh();
        $this->lend($books[2], $student)->assertSessionHas('success');

        // an overdue book blocks the next loan while the rule is on, and not when it is off
        $this->backdate($this->loanOf($books[0]), 3);
        $this->lend($books[3], $student)->assertSessionHas('error', 'err.lib.hasOverdue');
        $this->setSchoolSettings(['ops' => ['library' => ['block_if_overdue' => false]]]);
        $this->school->refresh();
        $this->lend($books[3], $student)->assertSessionHas('success');
    }

    public function test_staff_can_borrow_with_their_own_limit(): void
    {
        $staff = $this->staffRecord();
        $book = $this->book(1);
        $this->actAs($this->librarian)->post(route('library.loans.store'), ['library_book_id' => $book->id, 'staff_id' => $staff->id])->assertSessionHas('success');
        $loan = $this->loanOf($book);
        $this->assertSame($staff->id, (int) $loan->staff_id);
        $this->assertNull($loan->student_id);

        // both a learner and a staff member cannot be named at once
        $other = $this->book(1, ['title' => 'Other']);
        $this->actAs($this->librarian)->post(route('library.loans.store'), ['library_book_id' => $other->id, 'staff_id' => $staff->id, 'student_id' => $this->student()->id])->assertSessionHasErrors('staff_id');
    }

    public function test_a_book_created_before_copies_existed_still_circulates(): void
    {
        $legacy = $this->inSchool(fn () => LibraryBook::query()->create(['title' => 'Old', 'copies_total' => 2, 'copies_available' => 2, 'status' => 'available']));
        $student = $this->student();

        $this->lend($legacy, $student)->assertSessionHas('success');
        $this->assertSame(2, \DB::table('library_copies')->where('library_book_id', $legacy->id)->count());
        $this->assertSame(1, (int) $legacy->fresh()->copies_available);

        $loan = $this->loanOf($legacy);
        $this->actAs($this->librarian)->post(route('library.loans.return', $loan->id))->assertSessionHas('success', 'ok.lib.returned');
        $this->assertSame(2, (int) $legacy->fresh()->copies_available);
    }

    // ------------------------------------------------------------------ renewals

    public function test_renewals_respect_the_limit_and_never_apply_to_overdue_loans(): void
    {
        $book = $this->book(2);
        $student = $this->student();
        $this->lend($book, $student)->assertSessionHas('success');
        $loan = $this->loanOf($book);
        $firstDue = $loan->due_on->toDateString();

        $this->actAs($this->librarian)->post(route('library.loans.renew', $loan->id))->assertSessionHas('success', 'ok.lib.renewed');
        $this->assertSame(now()->addDays(28)->toDateString(), $loan->fresh()->due_on->toDateString());
        $this->assertNotSame($firstDue, $loan->fresh()->due_on->toDateString());
        $this->assertSame(1, (int) $loan->fresh()->renewals);
        $this->actAs($this->librarian)->post(route('library.loans.renew', $loan->id))->assertSessionHas('error', 'err.lib.renewLimit');

        $second = $this->student();
        $this->lend($book, $second)->assertSessionHas('success');
        $late = $this->loanOf($book);
        $this->backdate($late, 2);
        $this->actAs($this->librarian)->post(route('library.loans.renew', $late->id))->assertSessionHas('error', 'err.lib.renewOverdue');
    }

    // ------------------------------------------------------------------ returns and fines

    public function test_returning_on_time_costs_nothing_and_frees_the_copy(): void
    {
        $book = $this->book(1);
        $student = $this->student();
        $this->lend($book, $student)->assertSessionHas('success');
        $loan = $this->loanOf($book);

        $this->actAs($this->librarian)->post(route('library.loans.return', $loan->id))->assertSessionHas('success', 'ok.lib.returned');
        $this->assertSame('returned', $loan->fresh()->status);
        $this->assertSame($this->librarian->id, (int) $loan->fresh()->received_by);
        $this->assertSame(1, (int) $book->fresh()->copies_available);
        $this->assertSame(0, \DB::table('library_fines')->count());
        $this->actAs($this->librarian)->post(route('library.loans.return', $loan->id))->assertSessionHas('error', 'err.lib.notOut');
    }

    public function test_overdue_fines_exist_only_when_the_school_sets_a_daily_fine(): void
    {
        $book = $this->book(2);
        $s1 = $this->student();
        $s2 = $this->student();

        // default: no fine configured
        $this->lend($book, $s1)->assertSessionHas('success');
        $l1 = $this->loanOf($book);
        $this->backdate($l1, 5);
        $this->actAs($this->librarian)->post(route('library.loans.return', $l1->id))->assertSessionHas('success');
        $this->assertSame(0, \DB::table('library_fines')->count());

        $this->setSchoolSettings(['ops' => ['library' => ['fine_per_day' => 5]]]);
        $this->school->refresh();
        $this->lend($book, $s2)->assertSessionHas('success');
        $l2 = $this->loanOf($book);
        $this->backdate($l2, 4);
        $this->actAs($this->librarian)->post(route('library.loans.return', $l2->id))->assertSessionHas('success');

        $fine = LibraryFine::query()->withoutGlobalScopes()->firstOrFail();
        $this->assertSame([20, 'overdue', 'unpaid', $s2->id], [(int) $fine->amount, $fine->reason, $fine->status, (int) $fine->student_id]);
    }

    public function test_lost_and_damaged_copies_leave_circulation_and_a_fine_uses_the_amount_entered(): void
    {
        $book = $this->book(2);
        $s1 = $this->student();
        $s2 = $this->student();

        $this->lend($book, $s1)->assertSessionHas('success');
        $lost = $this->loanOf($book);
        $this->actAs($this->librarian)->post(route('library.loans.return', $lost->id), ['condition' => 'lost', 'fine_amount' => 350, 'note' => 'Reported lost'])->assertSessionHas('success');
        $this->assertSame('lost', \DB::table('library_copies')->where('id', $lost->library_copy_id)->value('status'));
        $this->assertSame(1, (int) $book->fresh()->copies_total);
        $this->assertSame([350, 'lost'], [(int) LibraryFine::query()->withoutGlobalScopes()->first()->amount, LibraryFine::query()->withoutGlobalScopes()->first()->reason]);

        $this->lend($book, $s2)->assertSessionHas('success');
        $dam = $this->loanOf($book);
        $this->actAs($this->librarian)->post(route('library.loans.return', $dam->id), ['condition' => 'damaged'])->assertSessionHas('success');
        $this->assertSame('damaged', \DB::table('library_copies')->where('id', $dam->library_copy_id)->value('status'));
        $this->assertSame(1, \DB::table('library_fines')->count());                  // no amount entered, so no invented fine
        $this->assertSame(0, (int) $book->fresh()->copies_available);

        $this->actAs($this->librarian)->post(route('library.loans.return', $dam->id), ['condition' => 'ruined'])->assertSessionHasErrors('condition');
    }

    public function test_fines_are_paid_or_waived_once_and_a_waiver_needs_a_reason(): void
    {
        $book = $this->book(1);
        $student = $this->student();
        $this->lend($book, $student)->assertSessionHas('success');
        $loan = $this->loanOf($book);
        $this->actAs($this->librarian)->post(route('library.loans.return', $loan->id), ['condition' => 'lost', 'fine_amount' => 100])->assertSessionHas('success');
        $fine = LibraryFine::query()->withoutGlobalScopes()->firstOrFail();

        $this->actAs($this->teacher)->post(route('library.fines.decide', $fine->id), ['status' => 'paid'])->assertForbidden();
        $this->actAs($this->librarian)->post(route('library.fines.decide', $fine->id), ['status' => 'waived'])->assertSessionHas('error', 'err.lib.waiveNote');
        $this->assertSame('unpaid', $fine->fresh()->status);

        $this->actAs($this->librarian)->post(route('library.fines.decide', $fine->id), ['status' => 'paid', 'note' => 'Receipt 88'])->assertSessionHas('success', 'ok.lib.finePaid');
        $this->assertSame('paid', $fine->fresh()->status);
        $this->assertSame($this->librarian->id, (int) $fine->fresh()->decided_by);
        $this->actAs($this->librarian)->post(route('library.fines.decide', $fine->id), ['status' => 'waived', 'note' => 'x'])->assertSessionHas('error', 'err.lib.fineClosed');
        $this->assertTrue(ActivityLog::query()->withoutGlobalScopes()->where('event', 'lib.fine.paid')->exists());
    }

    // ------------------------------------------------------------------ views, history, rules, export

    public function test_loan_views_history_and_overdue_export(): void
    {
        $book = $this->book(3);
        $ok = $this->student();
        $late = $this->student();
        $this->lend($book, $ok)->assertSessionHas('success');
        $this->lend($book, $late)->assertSessionHas('success');
        $this->backdate($this->loanOf($book), 3);

        $this->actAs($this->librarian)->get(route('library.loans.index', ['view' => 'overdue']))->assertOk()->assertInertia(fn ($p) => $p->has('loans.data', 1)->where('loans.data.0.status', 'overdue')->where('loans.data.0.days_late', 3));
        $this->actAs($this->librarian)->get(route('library.loans.index', ['view' => 'out']))->assertOk()->assertInertia(fn ($p) => $p->has('loans.data', 2));
        $this->actAs($this->librarian)->get(route('library.books.show', $book->id))->assertOk()->assertInertia(fn ($p) => $p->component('Library/BookShow')->has('history', 2)->has('copies', 3));

        $csv = $this->actAs($this->librarian)->get(route('library.overdue.export'));
        $csv->assertOk();
        $this->assertStringContainsString('Things Fall Apart', $csv->streamedContent());
        $this->assertTrue(ActivityLog::query()->withoutGlobalScopes()->where('event', 'lib.overdue.exported')->exists());
        $this->actAs($this->teacher)->get(route('library.overdue.export'))->assertForbidden();
    }

    public function test_lending_rules_are_saved_per_school_and_validated(): void
    {
        $body = ['loan_days' => 7, 'fine_per_day' => 2, 'max_loans_student' => 4, 'max_loans_staff' => 6, 'max_renewals' => 2, 'block_if_overdue' => false];
        $this->actAs($this->teacher)->put(route('library.setup.save'), $body)->assertForbidden();
        $this->actAs($this->librarian)->put(route('library.setup.save'), $body)->assertSessionHas('success', 'ok.lib.settingsSaved');
        $this->school->refresh();
        $this->assertSame(7, (int) data_get($this->school->settings, 'ops.library.loan_days'));
        $this->assertFalse((bool) data_get($this->school->settings, 'ops.library.block_if_overdue'));

        $this->actAs($this->librarian)->put(route('library.setup.save'), ['loan_days' => 0] + $body)->assertSessionHasErrors('loan_days');
        $this->actAs($this->librarian)->put(route('library.setup.save'), ['fine_per_day' => -1] + $body)->assertSessionHasErrors('fine_per_day');

        // the new loan period is used
        $this->school->refresh();
        $book = $this->book(1);
        $this->lend($book, $this->student())->assertSessionHas('success');
        $this->assertSame(now()->addDays(7)->toDateString(), $this->loanOf($book)->due_on->toDateString());
    }
}
