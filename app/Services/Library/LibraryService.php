<?php

namespace App\Services\Library;

use App\Exceptions\AcademicRuleException;
use App\Models\LibraryBook;
use App\Models\LibraryCopy;
use App\Models\LibraryFine;
use App\Models\LibraryLoan;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Services\Ops\Ops;
use App\Services\Ops\OpsSettings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Library circulation. Every lendable item is a physical copy; the catalogue counters are derived from the copies.
 * Loan length, limits, renewals and overdue fines are the school's own settings (a fine of 0 means no fines).
 */
class LibraryService
{
    public const COPY_STATUSES = ['available', 'damaged', 'withdrawn', 'lost'];

    public function __construct(private readonly OpsSettings $settings) {}

    /** A catalogue title with its first copies. Accession numbers are generated: B{title}-{001...}. */
    public function addBook(array $data, int $copies): LibraryBook
    {
        return DB::transaction(function () use ($data, $copies) {
            $book = LibraryBook::query()->create($data + ['copies_total' => 0, 'copies_available' => 0, 'status' => 'available']);
            $this->addCopies($book, $copies);

            return $book->refresh();
        });
    }

    public function addCopies(LibraryBook $book, int $count, ?string $acquiredOn = null): LibraryBook
    {
        if ($count < 1 || $count > 200) {
            throw AcademicRuleException::because('err.lib.copyCount');
        }

        return DB::transaction(function () use ($book, $count, $acquiredOn) {
            $book = LibraryBook::query()->lockForUpdate()->findOrFail($book->id);
            $next = $book->copies()->count() + 1;
            for ($i = 0; $i < $count; $i++) {
                LibraryCopy::query()->create([
                    'library_book_id' => $book->id, 'accession_number' => 'B'.$book->id.'-'.str_pad((string) ($next + $i), 3, '0', STR_PAD_LEFT),
                    'status' => 'available', 'acquired_on' => $acquiredOn,
                ]);
            }
            $book->syncCounts();
            Ops::audit('lib.copies.added', 'Copies added to '.$book->title, ['book_id' => $book->id, 'count' => $count]);

            return $book;
        });
    }

    /** Mark a copy damaged / withdrawn / lost, or put it back on the shelf. A copy that is out on loan must be returned first. */
    public function setCopyStatus(LibraryCopy $copy, string $status, ?string $note): LibraryCopy
    {
        if (! in_array($status, self::COPY_STATUSES, true)) {
            throw AcademicRuleException::because('err.lib.copyStatus');
        }

        return DB::transaction(function () use ($copy, $status, $note) {
            $copy = LibraryCopy::query()->lockForUpdate()->findOrFail($copy->id);
            if ($copy->status === 'on_loan') {
                throw AcademicRuleException::because('err.lib.copyOnLoan');
            }
            $copy->update(['status' => $status, 'condition_note' => $note ? mb_substr(trim($note), 0, 190) : null]);
            $copy->book?->syncCounts();
            Ops::audit('lib.copy.status', 'Copy '.$copy->accession_number.' set to '.$status, ['copy_id' => $copy->id, 'status' => $status]);

            return $copy;
        });
    }

    /** Lend the first available copy of a title to a learner or a staff member. */
    public function issue(LibraryBook $book, string $borrowerType, int $borrowerId, ?string $dueOn, User $actor): LibraryLoan
    {
        [$student, $staff] = $this->borrower($borrowerType, $borrowerId);
        $days = max(1, (int) $this->settings->get('library', 'loan_days', 14));
        $due = $dueOn ? Carbon::parse($dueOn)->startOfDay() : today()->addDays($days);
        if ($due->lt(today())) {
            throw AcademicRuleException::because('err.lib.dueDate');
        }

        return DB::transaction(function () use ($book, $student, $staff, $due, $actor) {
            $this->ensureCopies($book);
            // serialise lending per borrower and title so two desks cannot hand out the last copy or exceed a limit together
            $copy = LibraryCopy::query()->where('library_book_id', $book->id)->where('status', 'available')->orderBy('id')->lockForUpdate()->first();
            if (! $copy) {
                throw AcademicRuleException::because('err.lib.noCopy');
            }

            $open = LibraryLoan::query()->where('status', 'out')
                ->when($student, fn ($q) => $q->where('student_id', $student->id))
                ->when($staff, fn ($q) => $q->where('staff_id', $staff->id))
                ->lockForUpdate()->get();

            $limit = (int) $this->settings->get('library', $student ? 'max_loans_student' : 'max_loans_staff', 0);
            if ($limit > 0 && $open->count() >= $limit) {
                throw AcademicRuleException::because('err.lib.limit');
            }
            if ($this->settings->get('library', 'block_if_overdue', true) && $open->contains(fn ($l) => $l->due_on->lt(today()))) {
                throw AcademicRuleException::because('err.lib.hasOverdue');
            }
            if ($open->contains(fn ($l) => (int) $l->library_book_id === (int) $book->id)) {
                throw AcademicRuleException::because('err.lib.alreadyHas');
            }

            $loan = LibraryLoan::query()->create([
                'library_book_id' => $book->id, 'library_copy_id' => $copy->id, 'student_id' => $student?->id, 'staff_id' => $staff?->id,
                'loaned_on' => today()->toDateString(), 'due_on' => $due->toDateString(), 'status' => 'out', 'issued_by' => $actor->id,
            ]);
            $copy->update(['status' => 'on_loan']);
            $book->syncCounts();
            Ops::audit('lib.loan.issued', 'Book lent: '.$book->title, ['loan_id' => $loan->id, 'book_id' => $book->id, 'copy_id' => $copy->id]);

            return $loan;
        });
    }

    /** Extend a loan by the school's loan period. Overdue loans must be returned (and any fine settled) rather than renewed. */
    public function renew(LibraryLoan $loan, User $actor): LibraryLoan
    {
        return DB::transaction(function () use ($loan, $actor) {
            $loan = LibraryLoan::query()->lockForUpdate()->findOrFail($loan->id);
            if ($loan->status !== 'out') {
                throw AcademicRuleException::because('err.lib.notOut');
            }
            if ($loan->due_on->lt(today())) {
                throw AcademicRuleException::because('err.lib.renewOverdue');
            }
            if ((int) $loan->renewals >= (int) $this->settings->get('library', 'max_renewals', 1)) {
                throw AcademicRuleException::because('err.lib.renewLimit');
            }
            $loan->update(['due_on' => $loan->due_on->copy()->addDays(max(1, (int) $this->settings->get('library', 'loan_days', 14)))->toDateString(), 'renewals' => $loan->renewals + 1]);
            Ops::audit('lib.loan.renewed', 'Loan renewed', ['loan_id' => $loan->id, 'renewals' => $loan->renewals]);

            return $loan;
        });
    }

    /**
     * Take a copy back. `$condition` is good | damaged | lost. An overdue fine is added only when the school set a per-day fine;
     * a fine for a lost or damaged copy uses the amount the librarian enters (the platform never invents a price).
     */
    public function receive(LibraryLoan $loan, User $actor, string $condition = 'good', ?int $fineAmount = null, ?string $note = null): LibraryLoan
    {
        if (! in_array($condition, ['good', 'damaged', 'lost'], true)) {
            throw AcademicRuleException::because('err.lib.condition');
        }

        return DB::transaction(function () use ($loan, $actor, $condition, $fineAmount, $note) {
            $loan = LibraryLoan::query()->with(['copy', 'book'])->lockForUpdate()->findOrFail($loan->id);
            if ($loan->status !== 'out') {
                throw AcademicRuleException::because('err.lib.notOut');
            }

            $loan->update(['status' => 'returned', 'returned_on' => today()->toDateString(), 'received_by' => $actor->id]);
            $copy = $loan->copy ?? $this->legacyCopy($loan);
            $copy?->update(['status' => $condition === 'good' ? 'available' : $condition, 'condition_note' => $condition === 'good' ? null : ($note ? mb_substr(trim($note), 0, 190) : null)]);
            $loan->book?->syncCounts();

            $perDay = (int) $this->settings->get('library', 'fine_per_day', 0);
            $late = $loan->due_on->lt(today()) ? (int) $loan->due_on->diffInDays(today()) : 0;
            if ($perDay > 0 && $late > 0) {
                $this->fine($loan, $perDay * $late, 'overdue', $late.' day(s) late');
            }
            if ($condition !== 'good' && $fineAmount !== null && $fineAmount > 0) {
                $this->fine($loan, $fineAmount, $condition, $note);
            }
            Ops::audit('lib.loan.returned', 'Book returned ('.$condition.')', ['loan_id' => $loan->id, 'condition' => $condition, 'late_days' => $late]);

            return $loan;
        });
    }

    /** Record that a fine was paid at the library desk, or waive it with a written reason. */
    public function decideFine(LibraryFine $fine, User $actor, string $decision, ?string $note): LibraryFine
    {
        if (! in_array($decision, ['paid', 'waived'], true)) {
            throw AcademicRuleException::because('err.lib.fineDecision');
        }
        $note = $note !== null ? mb_substr(trim($note), 0, 300) : null;
        if ($decision === 'waived' && ($note === null || $note === '')) {
            throw AcademicRuleException::because('err.lib.waiveNote');
        }

        return DB::transaction(function () use ($fine, $actor, $decision, $note) {
            $fine = LibraryFine::query()->lockForUpdate()->findOrFail($fine->id);
            if ($fine->status !== 'unpaid') {
                throw AcademicRuleException::because('err.lib.fineClosed');
            }
            $fine->update(['status' => $decision, 'decided_by' => $actor->id, 'decided_at' => now(), 'note' => $note ?: $fine->note]);
            Ops::audit('lib.fine.'.$decision, 'Library fine '.$decision, ['fine_id' => $fine->id, 'amount' => $fine->amount]);

            return $fine;
        });
    }

    /** @return array{0:?Student,1:?Staff} */
    private function borrower(string $type, int $id): array
    {
        if ($type === 'student') {
            $student = Student::query()->find($id);
            if (! $student || $student->status !== 'active') {
                throw AcademicRuleException::because('err.lib.borrower');
            }

            return [$student, null];
        }
        if ($type === 'staff') {
            $staff = Staff::query()->find($id);
            if (! $staff || $staff->status !== 'active') {
                throw AcademicRuleException::because('err.lib.borrower');
            }

            return [null, $staff];
        }
        throw AcademicRuleException::because('err.lib.borrower');
    }

    /** Digits only (X allowed as the ISBN-10 check digit); refuses a number whose check digit is wrong. Blank stays blank. */
    public function normaliseIsbn(?string $isbn, ?int $exceptId = null): ?string
    {
        $raw = strtoupper(preg_replace('/[\s-]+/', '', (string) $isbn));
        if ($raw === '') {
            return null;
        }
        $ok = false;
        if (preg_match('/^\d{9}[\dX]$/', $raw)) {
            $sum = 0;
            foreach (str_split($raw) as $i => $c) {
                $sum += ($c === 'X' ? 10 : (int) $c) * (10 - $i);
            }
            $ok = $sum % 11 === 0;
        } elseif (preg_match('/^\d{13}$/', $raw)) {
            $sum = 0;
            foreach (str_split($raw) as $i => $c) {
                $sum += (int) $c * ($i % 2 === 0 ? 1 : 3);
            }
            $ok = $sum % 10 === 0;
        }
        if (! $ok) {
            throw AcademicRuleException::because('err.lib.isbn');
        }
        if (LibraryBook::query()->where('isbn', $raw)->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))->exists()) {
            throw AcademicRuleException::because('err.lib.isbnExists');
        }

        return $raw;
    }

    /** A title created before copies existed has only counters: give it real copies the first time it is lent. */
    private function ensureCopies(LibraryBook $book): void
    {
        if ($book->copies()->exists() || (int) $book->copies_total < 1) {
            return;
        }
        $out = LibraryLoan::query()->where('library_book_id', $book->id)->where('status', 'out')->count();
        for ($i = 1; $i <= (int) $book->copies_total; $i++) {
            LibraryCopy::query()->create([
                'library_book_id' => $book->id, 'accession_number' => 'B'.$book->id.'-'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'status' => $i <= $out ? 'on_loan' : 'available',
            ]);
        }
    }

    /** A loan made before copies were tracked is not tied to one: free any copy that is marked out but has no open loan of its own. */
    private function legacyCopy(LibraryLoan $loan): ?LibraryCopy
    {
        $linked = LibraryLoan::query()->where('status', 'out')->whereNotNull('library_copy_id')->pluck('library_copy_id');

        return LibraryCopy::query()->where('library_book_id', $loan->library_book_id)->where('status', 'on_loan')->whereNotIn('id', $linked)->orderBy('id')->first();
    }

    private function fine(LibraryLoan $loan, int $amount, string $reason, ?string $note): LibraryFine
    {
        return LibraryFine::query()->create([
            'library_loan_id' => $loan->id, 'student_id' => $loan->student_id, 'staff_id' => $loan->staff_id, 'amount' => $amount,
            'reason' => $reason, 'status' => 'unpaid', 'note' => $note ? mb_substr($note, 0, 300) : null,
        ]);
    }
}
