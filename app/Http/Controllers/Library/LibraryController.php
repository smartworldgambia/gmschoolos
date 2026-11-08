<?php

namespace App\Http\Controllers\Library;

use App\Exceptions\AcademicRuleException;
use App\Http\Controllers\Concerns\HandlesAcademicRules;
use App\Http\Controllers\Concerns\SharesSchoolShell;
use App\Http\Controllers\Controller;
use App\Models\LibraryBook;
use App\Models\LibraryCopy;
use App\Models\LibraryFine;
use App\Models\LibraryLoan;
use App\Models\Staff;
use App\Models\Student;
use App\Services\Library\LibraryService;
use App\Services\Ops\Ops;
use App\Services\Ops\OpsSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Library: catalogue and copies, lending, returns, fines and the school's own lending rules. All of it needs library.manage. */
class LibraryController extends Controller
{
    use HandlesAcademicRules, SharesSchoolShell;

    // ------------------------------------------------------------------ catalogue

    public function index(Request $request): Response
    {
        $q = trim((string) $request->query('q', ''));
        $books = LibraryBook::query()
            ->when($q !== '', fn ($b) => $b->where(fn ($w) => $w->where('title', 'like', "%$q%")->orWhere('author', 'like', "%$q%")->orWhere('isbn', 'like', '%'.preg_replace('/[\s-]+/', '', $q).'%')->orWhere('category', 'like', "%$q%")))
            ->orderBy('title')->paginate(25)->withQueryString();

        return Inertia::render('Library/Index', array_merge($this->shellProps(), [
            'books' => $books->through(fn (LibraryBook $b) => [
                'id' => $b->id, 'title' => $b->title, 'author' => $b->author, 'isbn' => $b->isbn, 'category' => $b->category, 'shelf_location' => $b->shelf_location,
                'copies_total' => (int) $b->copies_total, 'copies_available' => (int) $b->copies_available,
            ]),
            'q' => $q,
            'stats' => [
                'titles' => LibraryBook::query()->count(),
                'available' => (int) LibraryBook::query()->sum('copies_available'),
                'out' => LibraryLoan::query()->where('status', 'out')->count(),
                'overdue' => LibraryLoan::query()->where('status', 'out')->where('due_on', '<', today()->toDateString())->count(),
                'unpaidFines' => (int) LibraryFine::query()->where('status', 'unpaid')->sum('amount'),
            ],
        ]));
    }

    public function storeBook(Request $request, LibraryService $service): RedirectResponse
    {
        $v = $this->bookRules($request) + $request->validate(['copies_total' => ['required', 'integer', 'min:1', 'max:200']]);

        return $this->attempt(function () use ($v, $service) {
            $v['isbn'] = $service->normaliseIsbn($v['isbn'] ?? null);
            $copies = (int) $v['copies_total'];
            unset($v['copies_total']);
            $book = $service->addBook($v, $copies);
            Ops::audit('lib.book.created', 'Title added: '.$book->title, ['book_id' => $book->id, 'copies' => $copies]);

            return redirect()->route('library.books.show', $book)->with('success', 'ok.lib.bookSaved');
        });
    }

    public function updateBook(Request $request, LibraryBook $book, LibraryService $service): RedirectResponse
    {
        $v = $this->bookRules($request);

        return $this->attempt(function () use ($v, $book, $service) {
            $v['isbn'] = $service->normaliseIsbn($v['isbn'] ?? null, $book->id);
            $book->update($v);
            Ops::audit('lib.book.updated', 'Title updated: '.$book->title, ['book_id' => $book->id]);
        }, 'ok.lib.bookSaved');
    }

    public function showBook(LibraryBook $book): Response
    {
        return Inertia::render('Library/BookShow', array_merge($this->shellProps(), [
            'book' => [
                'id' => $book->id, 'title' => $book->title, 'author' => $book->author, 'isbn' => $book->isbn, 'category' => $book->category, 'publisher' => $book->publisher,
                'publication_year' => $book->publication_year, 'language' => $book->language, 'shelf_location' => $book->shelf_location,
                'copies_total' => (int) $book->copies_total, 'copies_available' => (int) $book->copies_available,
            ],
            'copies' => $book->copies()->orderBy('accession_number')->get()->map(fn (LibraryCopy $c) => [
                'id' => $c->id, 'accession_number' => $c->accession_number, 'status' => $c->status, 'condition_note' => $c->condition_note, 'acquired_on' => $c->acquired_on?->toDateString(),
            ]),
            'history' => LibraryLoan::query()->with(['student:id,first_name,last_name', 'staff:id,first_name,last_name', 'copy:id,accession_number'])->where('library_book_id', $book->id)
                ->latest('id')->limit(50)->get()->map(fn (LibraryLoan $l) => $this->loanRow($l)),
        ]));
    }

    public function addCopies(Request $request, LibraryBook $book, LibraryService $service): RedirectResponse
    {
        $v = $request->validate(['count' => ['required', 'integer', 'min:1', 'max:200'], 'acquired_on' => ['nullable', 'date', 'before_or_equal:today']]);

        return $this->attempt(fn () => $service->addCopies($book, (int) $v['count'], $v['acquired_on'] ?? null), 'ok.lib.copiesAdded');
    }

    public function setCopyStatus(Request $request, LibraryCopy $copy, LibraryService $service): RedirectResponse
    {
        $v = $request->validate(['status' => ['required', Rule::in(LibraryService::COPY_STATUSES)], 'note' => ['nullable', 'string', 'max:190']]);

        return $this->attempt(fn () => $service->setCopyStatus($copy, $v['status'], $v['note'] ?? null), 'ok.lib.copySaved');
    }

    // ------------------------------------------------------------------ lending

    public function loans(Request $request): Response
    {
        $view = (string) $request->query('view', 'out');
        $loans = LibraryLoan::query()->with(['book:id,title', 'student:id,first_name,last_name,student_number', 'staff:id,first_name,last_name,staff_number', 'copy:id,accession_number'])
            ->when($view === 'overdue', fn ($q) => $q->where('status', 'out')->where('due_on', '<', today()->toDateString()))
            ->when($view === 'returned', fn ($q) => $q->where('status', 'returned'))
            ->when($view === 'out', fn ($q) => $q->where('status', 'out'))
            ->orderBy($view === 'returned' ? 'returned_on' : 'due_on', $view === 'returned' ? 'desc' : 'asc')->paginate(25)->withQueryString();

        return Inertia::render('Library/Loans', array_merge($this->shellProps(), [
            'loans' => $loans->through(fn (LibraryLoan $l) => $this->loanRow($l)),
            'view' => $view,
            'books' => LibraryBook::query()->where('copies_available', '>', 0)->orderBy('title')->limit(500)->get(['id', 'title', 'author', 'copies_available']),
            'students' => Student::query()->where('status', 'active')->orderBy('last_name')->limit(600)->get(['id', 'first_name', 'last_name', 'student_number'])
                ->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name.' ('.$s->student_number.')']),
            'staff' => Staff::query()->where('status', 'active')->orderBy('last_name')->get(['id', 'first_name', 'last_name'])->map(fn ($s) => ['id' => $s->id, 'name' => $s->full_name]),
            'rules' => app(OpsSettings::class)->group('library'),
        ]));
    }

    /** Lend a copy (route and field names kept from the original foundation; `staff_id` instead of `student_id` lends to a staff member). */
    public function storeLoan(Request $request, LibraryService $service): RedirectResponse
    {
        $v = $request->validate([
            'library_book_id' => ['required', $this->inSchool('library_books')],
            'student_id' => ['nullable', 'required_without:staff_id', $this->inSchool('students')],
            'staff_id' => ['nullable', 'required_without:student_id', 'prohibits:student_id', $this->inSchool('staff')],
            'due_on' => ['nullable', 'date'],
        ]);

        return $this->attempt(function () use ($v, $service, $request) {
            $book = LibraryBook::query()->findOrFail($v['library_book_id']);
            $type = ! empty($v['student_id']) ? 'student' : 'staff';
            $service->issue($book, $type, (int) ($v['student_id'] ?? $v['staff_id']), $v['due_on'] ?? null, $request->user());
        }, 'ok.lib.loanRecorded');
    }

    public function renewLoan(Request $request, LibraryLoan $loan, LibraryService $service): RedirectResponse
    {
        return $this->attempt(fn () => $service->renew($loan, $request->user()), 'ok.lib.renewed');
    }

    public function returnLoan(Request $request, LibraryLoan $loan, LibraryService $service): RedirectResponse
    {
        $v = $request->validate([
            'condition' => ['nullable', Rule::in(['good', 'damaged', 'lost'])],
            'fine_amount' => ['nullable', 'integer', 'min:0', 'max:10000000'],
            'note' => ['nullable', 'string', 'max:190'],
        ]);

        return $this->attempt(fn () => $service->receive($loan, $request->user(), $v['condition'] ?? 'good', isset($v['fine_amount']) ? (int) $v['fine_amount'] : null, $v['note'] ?? null), 'ok.lib.returned');
    }

    public function exportOverdue(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        Ops::audit('lib.overdue.exported', 'Overdue loans exported');
        $rows = LibraryLoan::query()->with(['book:id,title', 'student:id,first_name,last_name,student_number', 'staff:id,first_name,last_name,staff_number'])
            ->where('status', 'out')->where('due_on', '<', today()->toDateString())->orderBy('due_on')->get()
            ->map(fn (LibraryLoan $l) => [$l->book?->title, $l->student?->full_name ?? $l->staff?->full_name, $l->student?->student_number ?? $l->staff?->staff_number, $l->loaned_on?->toDateString(), $l->due_on?->toDateString(), (int) $l->due_on->diffInDays(today())]);

        return Ops::csv('overdue-loans.csv', ['Title', 'Borrower', 'Number', 'Loaned on', 'Due on', 'Days late'], $rows);
    }

    // ------------------------------------------------------------------ fines

    public function fines(Request $request): Response
    {
        $status = (string) $request->query('status', 'unpaid');
        $fines = LibraryFine::query()->with(['loan.book:id,title', 'student:id,first_name,last_name,student_number', 'staff:id,first_name,last_name'])
            ->when(in_array($status, ['unpaid', 'paid', 'waived'], true), fn ($q) => $q->where('status', $status))->latest('id')->paginate(25)->withQueryString();

        return Inertia::render('Library/Fines', array_merge($this->shellProps(), [
            'fines' => $fines->through(fn (LibraryFine $f) => [
                'id' => $f->id, 'borrower' => $f->student?->full_name ?? $f->staff?->full_name, 'title' => $f->loan?->book?->title, 'amount' => (int) $f->amount, 'reason' => $f->reason,
                'status' => $f->status, 'note' => $f->note, 'created_at' => $f->created_at?->toDateString(),
            ]),
            'status' => $status,
        ]));
    }

    public function decideFine(Request $request, LibraryFine $fine, LibraryService $service): RedirectResponse
    {
        $v = $request->validate(['status' => ['required', 'in:paid,waived'], 'note' => ['nullable', 'string', 'max:300']]);

        return $this->attempt(fn () => $service->decideFine($fine, $request->user(), $v['status'], $v['note'] ?? null), $v['status'] === 'paid' ? 'ok.lib.finePaid' : 'ok.lib.fineWaived');
    }

    // ------------------------------------------------------------------ rules

    public function setup(OpsSettings $settings): Response
    {
        return Inertia::render('Library/Setup', array_merge($this->shellProps(), ['rules' => $settings->group('library')]));
    }

    public function saveSetup(Request $request, OpsSettings $settings): RedirectResponse
    {
        $v = $request->validate([
            'loan_days' => ['required', 'integer', 'min:1', 'max:365'],
            'fine_per_day' => ['required', 'integer', 'min:0', 'max:100000'],
            'max_loans_student' => ['required', 'integer', 'min:0', 'max:100'],
            'max_loans_staff' => ['required', 'integer', 'min:0', 'max:100'],
            'max_renewals' => ['required', 'integer', 'min:0', 'max:20'],
            'block_if_overdue' => ['required', 'boolean'],
        ]);

        return $this->attempt(function () use ($v, $settings) {
            $settings->save('library', $v);
            Ops::audit('lib.settings.saved', 'Library rules saved', $v);
        }, 'ok.lib.settingsSaved');
    }

    // ------------------------------------------------------------------ helpers

    private function bookRules(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'author' => ['nullable', 'string', 'max:150'],
            'isbn' => ['nullable', 'string', 'max:40'],
            'category' => ['nullable', 'string', 'max:80'],
            'publisher' => ['nullable', 'string', 'max:150'],
            'publication_year' => ['nullable', 'integer', 'between:1400,'.((int) now()->format('Y') + 1)],
            'language' => ['nullable', 'string', 'max:40'],
            'shelf_location' => ['nullable', 'string', 'max:60'],
        ]);
    }

    private function loanRow(LibraryLoan $l): array
    {
        $overdue = $l->status === 'out' && $l->due_on?->lt(today());

        return [
            'id' => $l->id, 'book' => $l->book?->title, 'accession' => $l->copy?->accession_number, 'borrower' => $l->student?->full_name ?? $l->staff?->full_name,
            'borrower_type' => $l->student_id ? 'student' : 'staff', 'loaned_on' => $l->loaned_on?->toDateString(), 'due_on' => $l->due_on?->toDateString(),
            'returned_on' => $l->returned_on?->toDateString(), 'status' => $overdue ? 'overdue' : $l->status, 'renewals' => (int) $l->renewals,
            'days_late' => $overdue ? (int) $l->due_on->diffInDays(today()) : 0,
        ];
    }
}
