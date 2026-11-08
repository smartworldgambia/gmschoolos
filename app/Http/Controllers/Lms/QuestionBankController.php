<?php

namespace App\Http\Controllers\Lms;

use App\Http\Controllers\Concerns\UsesLms;
use App\Http\Controllers\Controller;
use App\Models\BankQuestion;
use App\Models\QuestionBank;
use App\Models\QuizBankPull;
use App\Models\QuizQuestion;
use App\Models\Subject;
use App\Services\Lms\QuizService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Reusable question banks. A teacher sees their own banks and banks shared school-wide; managers see all. */
class QuestionBankController extends Controller
{
    use UsesLms;

    public function __construct(protected QuizService $service) {}

    protected function gate(): void
    {
        abort_unless(auth()->user()->canAny(['lms.teach', 'lms.manage']), 403);
    }

    protected function canSee(QuestionBank $bank): bool
    {
        $u = auth()->user();

        return $u->can('lms.manage') || $bank->is_shared || (int) $bank->created_by === (int) $u->id;
    }

    protected function canEdit(QuestionBank $bank): bool
    {
        $u = auth()->user();

        return $u->can('lms.manage') || (int) $bank->created_by === (int) $u->id;
    }

    public function index(): Response
    {
        $this->gate();
        $u = auth()->user();
        $banks = QuestionBank::query()->with(['subject:id,name', 'creator:id,name'])->withCount('questions')
            ->when(! $u->can('lms.manage'), fn ($q) => $q->where(fn ($w) => $w->where('created_by', $u->id)->orWhere('is_shared', true)))
            ->orderBy('title')->get();

        return Inertia::render('Lms/Banks', $this->lmsPage([
            'banks' => $banks->map(fn (QuestionBank $b) => [
                'id' => $b->id, 'title' => $b->title, 'subject' => $b->subject?->name, 'is_shared' => (bool) $b->is_shared, 'questions' => $b->questions_count,
                'owner' => $b->creator?->name, 'mine' => (int) $b->created_by === (int) $u->id, 'url' => route('lms.banks.show', $b),
            ])->values(),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $this->gate();
        $v = $request->validate(['title' => ['required', 'string', 'max:190'], 'subject_id' => ['nullable', 'integer', $this->inSchool('subjects')], 'is_shared' => ['boolean']]);
        $bank = QuestionBank::query()->create($v + ['school_id' => app(\App\Support\TenantContext::class)->id(), 'created_by' => $request->user()->id]);

        return redirect()->route('lms.banks.show', $bank)->with('success', 'ok.lms.bankSaved');
    }

    public function show(QuestionBank $bank): Response
    {
        $this->gate();
        abort_unless($this->canSee($bank), 404);

        return Inertia::render('Lms/BankShow', $this->lmsPage([
            'bank' => ['id' => $bank->id, 'title' => $bank->title, 'subject_id' => $bank->subject_id, 'is_shared' => (bool) $bank->is_shared, 'can_edit' => $this->canEdit($bank)],
            'questions' => $bank->questions()->orderBy('id')->get()->map(fn (BankQuestion $q) => [
                'id' => $q->id, 'type' => $q->type, 'prompt' => $q->prompt, 'points' => $q->points, 'options' => $q->options ?? [],
                'correct_answer' => $q->correct_answer, 'accepted_answers' => $q->accepted_answers ?? [], 'case_sensitive' => (bool) $q->case_sensitive,
                'explanation' => $q->explanation,
            ])->values(),
            'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
            'questionTypes' => QuizQuestion::TYPES,
        ]));
    }

    public function update(Request $request, QuestionBank $bank): RedirectResponse
    {
        $this->gate();
        abort_unless($this->canEdit($bank), 403);
        $v = $request->validate(['title' => ['required', 'string', 'max:190'], 'subject_id' => ['nullable', 'integer', $this->inSchool('subjects')], 'is_shared' => ['boolean']]);
        $bank->update($v);

        return back()->with('success', 'ok.lms.bankSaved');
    }

    public function destroy(QuestionBank $bank): RedirectResponse
    {
        $this->gate();
        abort_unless($this->canEdit($bank), 403);
        if (QuizBankPull::query()->where('question_bank_id', $bank->id)->exists()) {
            return back()->with('error', 'err.lms.bankInUse');
        }
        $bank->delete();

        return redirect()->route('lms.banks.index')->with('success', 'ok.lms.bankRemoved');
    }

    protected function questionRules(): array
    {
        return [
            'type' => ['required', 'in:'.implode(',', QuizQuestion::TYPES)], 'prompt' => ['required', 'string', 'max:3000'],
            'points' => ['required', 'integer', 'min:1', 'max:100'], 'options' => ['nullable', 'array', 'max:8'], 'options.*' => ['nullable', 'string', 'max:300'],
            'correct_answer' => ['nullable', 'string', 'max:500'], 'accepted_answers' => ['nullable', 'array', 'max:20'], 'accepted_answers.*' => ['nullable', 'string', 'max:300'],
            'case_sensitive' => ['boolean'], 'explanation' => ['nullable', 'string', 'max:2000'],
        ];
    }

    public function storeQuestion(Request $request, QuestionBank $bank): RedirectResponse
    {
        $this->gate();
        abort_unless($this->canEdit($bank), 403);
        $v = $request->validate($this->questionRules());

        return $this->attempt(function () use ($bank, $v) {
            BankQuestion::query()->create($this->service->cleanQuestion($v) + ['school_id' => $bank->school_id, 'question_bank_id' => $bank->id]);

            return back()->with('success', 'ok.lms.questionSaved');
        });
    }

    public function updateQuestion(Request $request, BankQuestion $question): RedirectResponse
    {
        $this->gate();
        abort_unless($this->canEdit($question->bank), 403);
        $v = $request->validate($this->questionRules());

        return $this->attempt(function () use ($question, $v) {
            $question->update($this->service->cleanQuestion($v)); // past attempts keep their own snapshot

            return back()->with('success', 'ok.lms.questionSaved');
        });
    }

    public function destroyQuestion(BankQuestion $question): RedirectResponse
    {
        $this->gate();
        abort_unless($this->canEdit($question->bank), 403);
        $question->delete();

        return back()->with('success', 'ok.lms.questionRemoved');
    }
}
