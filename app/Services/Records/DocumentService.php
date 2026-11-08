<?php

namespace App\Services\Records;

use App\Exceptions\AcademicRuleException;
use App\Models\RecordsDocument;
use App\Models\RecordsDocumentEvent;
use App\Models\RecordsTemplate;
use App\Models\SsApplication;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Records Office document workflow: Draft -> Submitted for review -> Approved -> Issued -> Reprinted | Revoked.
 *
 * - The compiled snapshot (`payload`) is fixed at submission; issued documents never change.
 * - Every transition is written to the append-only `records_document_events` (the issuance register).
 * - Approval/issue/revoke need `records.issue`; drafting/submitting/reprinting need `records.manage`.
 *   Admission letters follow the already human-decided Senior Secondary outcome and use `senior_admissions.manage`.
 * - A school transcript / testimonial / letter is NEVER an official WAEC result or certificate.
 */
class DocumentService
{
    public const TYPES = ['transcript_g9', 'transcript_g12', 'testimonial', 'cover_letter', 'admission_letter'];

    public const LETTERS = ['testimonial', 'cover_letter', 'admission_letter'];

    public const PREFIX = ['transcript_g9' => 'TR9', 'transcript_g12' => 'TR12', 'testimonial' => 'TES', 'cover_letter' => 'CVL', 'admission_letter' => 'ADL'];

    public function __construct(
        protected TranscriptCompiler $compiler,
        protected SchoolSequence $sequence,
        protected RecordsSettings $settings,
    ) {}

    // ------------------------------------------------------------------ creation

    public function createTranscript(User $user, Student $student, string $type): RecordsDocument
    {
        $this->can($user, 'draft', $type);
        if (! in_array($type, TranscriptCompiler::TYPES, true)) {
            throw AcademicRuleException::because('err.rec.typeInvalid');
        }
        $payload = $this->compiler->compile($student, $type);
        $label = $type === 'transcript_g9' ? 'Grade 9 transcript' : 'Grade 12 transcript';

        return $this->create($user, $type, $label.' - '.$student->full_name, ['student_id' => $student->id], $payload);
    }

    public function createLetter(User $user, Student $student, string $type, ?RecordsTemplate $template, ?string $custom): RecordsDocument
    {
        if (! in_array($type, ['testimonial', 'cover_letter'], true)) {
            throw AcademicRuleException::because('err.rec.typeInvalid');
        }
        $this->can($user, 'draft', $type);
        $this->assertTemplate($template, $type);
        $student->loadMissing('schoolClass.level');
        $school = $this->settings->school();

        $vars = [
            'student_name' => $student->full_name, 'student_number' => $student->student_number,
            'school_name' => $school->name, 'date' => now()->format('j F Y'),
            'programme' => $student->schoolClass?->level?->name ?? '',
        ];
        $body = $this->body($template, $custom, $vars, $type);
        $payload = [
            'kind' => $type,
            'school' => $this->schoolSnapshot($school),
            'student' => ['full_name' => $student->full_name, 'student_number' => $student->student_number, 'programme' => $vars['programme'], 'photo_path' => $student->photo_path],
        ];

        return $this->create($user, $type, ($type === 'testimonial' ? 'Testimonial' : 'Cover letter').' - '.$student->full_name,
            ['student_id' => $student->id, 'template_id' => $template?->id, 'body' => $body], $payload);
    }

    public function createAdmissionLetter(User $user, SsApplication $application, ?RecordsTemplate $template, ?string $custom): RecordsDocument
    {
        $this->can($user, 'draft', 'admission_letter');
        if ($application->status !== 'approved') {
            throw AcademicRuleException::because('err.rec.notApproved');
        }
        $this->assertTemplate($template, 'admission_letter');
        $application->loadMissing('round');
        $school = $this->settings->school();
        $name = trim($application->first_name.' '.$application->last_name);

        $vars = [
            'student_name' => $name, 'student_number' => $application->reference, 'school_name' => $school->name,
            'date' => now()->format('j F Y'), 'programme' => $application->programme_choice ?? '', 'round_name' => $application->round->name,
        ];
        $body = $this->body($template, $custom, $vars, 'admission_letter');
        $payload = [
            'kind' => 'admission_letter', 'school' => $this->schoolSnapshot($school),
            'student' => ['full_name' => $name, 'student_number' => $application->reference, 'programme' => $application->programme_choice, 'photo_path' => null],
            'round' => $application->round->name,
        ];

        return $this->create($user, 'admission_letter', 'Admission letter - '.$name,
            ['ss_application_id' => $application->id, 'template_id' => $template?->id, 'body' => $body], $payload);
    }

    // ------------------------------------------------------------------ editing & exceptions (draft only)

    public function updateDraft(User $user, RecordsDocument $doc, ?string $body, ?string $title = null): RecordsDocument
    {
        $this->can($user, 'draft', $doc->type);
        $this->requireStatus($doc, ['draft']);
        if (! in_array($doc->type, self::LETTERS, true)) {
            throw AcademicRuleException::because('err.rec.typeInvalid');
        }
        $doc->update(array_filter(['body' => $body, 'title' => $title], fn ($v) => $v !== null));

        return $doc;
    }

    public function recompile(User $user, RecordsDocument $doc): RecordsDocument
    {
        $this->can($user, 'draft', $doc->type);
        $this->requireStatus($doc, ['draft']);
        if (! in_array($doc->type, TranscriptCompiler::TYPES, true)) {
            throw AcademicRuleException::because('err.rec.typeInvalid');
        }
        $doc->update(['payload' => $this->compiler->compile($doc->student, $doc->type, $doc->exception)]);

        return $doc;
    }

    /**
     * Authorised exception for a Grade 12 transcript: include Grade 12 Term 3 and/or allow issue with a missing term.
     * Needs `records.issue` and a written reason; recorded on the document and in the register.
     */
    public function grantException(User $user, RecordsDocument $doc, bool $includeTerm3, bool $allowIncomplete, string $reason): RecordsDocument
    {
        $this->can($user, 'issue', $doc->type);
        $this->requireStatus($doc, ['draft', 'submitted']);
        if (! in_array($doc->type, TranscriptCompiler::TYPES, true)) {
            throw AcademicRuleException::because('err.rec.typeInvalid');
        }
        if (blank($reason)) {
            throw AcademicRuleException::because('err.rec.reasonRequired');
        }
        if ($includeTerm3 && $doc->type !== 'transcript_g12') {
            throw AcademicRuleException::because('err.rec.exceptionNa');
        }
        if (! $includeTerm3 && ! $allowIncomplete) {
            throw AcademicRuleException::because('err.rec.exceptionEmpty');
        }

        $exception = [
            'include_grade12_term3' => $includeTerm3, 'allow_incomplete' => $allowIncomplete,
            'reason' => $reason, 'approved_by' => $user->id, 'approved_by_name' => $user->name, 'approved_at' => now()->toIso8601String(),
        ];

        return DB::transaction(function () use ($doc, $exception, $user) {
            $doc->update(['exception' => $exception, 'payload' => $this->compiler->compile($doc->student, $doc->type, $exception)]);
            $this->event($doc, 'exception', $exception['reason'], $user);

            return $doc;
        });
    }

    // ------------------------------------------------------------------ workflow

    public function submit(User $user, RecordsDocument $doc): RecordsDocument
    {
        $this->can($user, 'draft', $doc->type);
        $this->requireStatus($doc, ['draft']);

        if (in_array($doc->type, TranscriptCompiler::TYPES, true)) {
            // Take the final snapshot now; it never changes afterwards.
            $payload = $this->compiler->compile($doc->student, $doc->type, $doc->exception);
            if ($payload['missing'] !== [] && empty($doc->exception['allow_incomplete'])) {
                throw AcademicRuleException::because('err.rec.missingTerms');
            }
            $doc->payload = $payload;
        } elseif (blank($doc->body)) {
            throw AcademicRuleException::because('err.rec.bodyRequired');
        }

        $doc->forceFill(['status' => 'submitted', 'submitted_at' => now()])->save();
        $this->event($doc, 'submitted', null, $user);

        return $doc;
    }

    public function returnToDraft(User $user, RecordsDocument $doc, string $reason): RecordsDocument
    {
        $this->can($user, 'issue', $doc->type);
        $this->requireStatus($doc, ['submitted', 'approved']);
        if (blank($reason)) {
            throw AcademicRuleException::because('err.rec.reasonRequired');
        }
        $doc->forceFill(['status' => 'draft', 'approved_by' => null, 'approved_at' => null])->save();
        $this->event($doc, 'returned', $reason, $user);

        return $doc;
    }

    public function approve(User $user, RecordsDocument $doc): RecordsDocument
    {
        $this->can($user, 'issue', $doc->type);
        $this->requireStatus($doc, ['submitted']);
        $doc->forceFill(['status' => 'approved', 'approved_by' => $user->id, 'approved_at' => now()])->save();
        $this->event($doc, 'approved', null, $user);

        return $doc;
    }

    public function issue(User $user, RecordsDocument $doc, ?string $issueDate = null): RecordsDocument
    {
        $this->can($user, 'issue', $doc->type);
        $this->requireStatus($doc, ['approved']);
        $date = $issueDate ? \Carbon\Carbon::parse($issueDate) : now();
        if ($date->isFuture() && ! $date->isToday()) {
            throw AcademicRuleException::because('err.rec.dateFuture');
        }
        $doc->forceFill(['status' => 'issued', 'issued_by' => $user->id, 'issued_at' => now(), 'issue_date' => $date->toDateString()])->save();
        $this->event($doc, 'issued', null, $user);

        return $doc;
    }

    public function reprint(User $user, RecordsDocument $doc, string $reason): RecordsDocument
    {
        $this->can($user, 'draft', $doc->type);
        $this->requireStatus($doc, ['issued', 'reprinted']);
        if (blank($reason)) {
            throw AcademicRuleException::because('err.rec.reasonRequired');
        }
        $doc->forceFill(['status' => 'reprinted', 'reprint_count' => $doc->reprint_count + 1, 'last_reprinted_at' => now()])->save();
        $this->event($doc, 'reprinted', $reason, $user);

        return $doc;
    }

    public function revoke(User $user, RecordsDocument $doc, string $reason): RecordsDocument
    {
        $this->can($user, 'issue', $doc->type);
        $this->requireStatus($doc, ['issued', 'reprinted', 'approved']);
        if (blank($reason)) {
            throw AcademicRuleException::because('err.rec.reasonRequired');
        }
        $doc->forceFill(['status' => 'revoked', 'revoked_by' => $user->id, 'revoked_at' => now(), 'revoked_reason' => $reason])->save();
        $this->event($doc, 'revoked', $reason, $user);

        return $doc;
    }

    public function verificationUrl(RecordsDocument $doc): string
    {
        return route('records.verify', ['token' => $doc->verification_token]);
    }

    // ------------------------------------------------------------------ templates

    public function saveTemplate(User $user, array $data, ?RecordsTemplate $template = null): RecordsTemplate
    {
        $this->can($user, 'draft', $data['type']);
        $payload = ['type' => $data['type'], 'name' => $data['name'], 'body' => $data['body'], 'is_active' => (bool) ($data['is_active'] ?? true), 'is_default' => (bool) ($data['is_default'] ?? false)];

        return DB::transaction(function () use ($payload, $template, $user) {
            if ($payload['is_default']) {
                RecordsTemplate::query()->where('type', $payload['type'])->update(['is_default' => false]);
            }
            if ($template) {
                $template->update($payload);

                return $template;
            }

            return RecordsTemplate::query()->create($payload + ['created_by' => $user->id]);
        });
    }

    /** Placeholders are `{{ name }}`; values are inserted as PLAIN TEXT (the PDF/HTML views escape them). */
    public function render(string $template, array $vars): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/', fn ($m) => (string) ($vars[$m[1]] ?? $m[0]), $template);
    }

    public function defaultBody(string $type): string
    {
        return match ($type) {
            'testimonial' => "TESTIMONIAL\n\nThis is to certify that {{ student_name }} (Student ID {{ student_number }}) was a student of {{ school_name }}.\n\nThis is a school-issued testimonial. It is not an official WAEC result or certificate.\n\nDate: {{ date }}",
            'cover_letter' => "To whom it may concern,\n\n{{ school_name }} confirms the enclosed records for {{ student_name }} (Student ID {{ student_number }}).\n\nDate: {{ date }}",
            default => "Dear Parent / Guardian,\n\n{{ school_name }} is pleased to offer {{ student_name }} (ref. {{ student_number }}) a place for {{ round_name }}.\n\nDate: {{ date }}",
        };
    }

    // ------------------------------------------------------------------ internals

    protected function create(User $user, string $type, string $title, array $attrs, array $payload): RecordsDocument
    {
        return DB::transaction(function () use ($user, $type, $title, $attrs, $payload) {
            $school = $this->settings->school();
            $n = $this->sequence->next($school->id, 'doc_'.$type);
            $doc = RecordsDocument::query()->create($attrs + [
                'type' => $type, 'title' => $title, 'status' => 'draft', 'payload' => $payload,
                'reference' => sprintf('%s-%s-%06d', self::PREFIX[$type], now()->format('Y'), $n),
                'verification_token' => Str::lower(Str::random(32)),
                'prepared_by' => $user->id,
            ]);
            $this->event($doc, 'created', null, $user);

            return $doc;
        });
    }

    protected function body(?RecordsTemplate $template, ?string $custom, array $vars, string $type): string
    {
        $text = $this->render($template?->body ?? $this->defaultBody($type), $vars);
        if (filled($custom)) {
            $text .= "\n\n".trim($custom);
        }

        return $text;
    }

    protected function assertTemplate(?RecordsTemplate $template, string $type): void
    {
        if ($template && ($template->type !== $type || ! $template->is_active)) {
            throw AcademicRuleException::because('err.rec.templateInvalid');
        }
    }

    protected function schoolSnapshot($school): array
    {
        return ['name' => $school->name, 'address' => $school->address, 'phone' => $school->phone, 'email' => $school->email, 'logo_path' => $school->logo_path];
    }

    protected function requireStatus(RecordsDocument $doc, array $allowed): void
    {
        if (! in_array($doc->status, $allowed, true)) {
            throw AcademicRuleException::because('err.rec.status.'.$doc->status);
        }
    }

    /** @param 'draft'|'issue' $level */
    protected function can(User $user, string $level, string $type): void
    {
        $permission = $type === 'admission_letter'
            ? 'senior_admissions.manage'
            : ($level === 'issue' ? 'records.issue' : 'records.manage');
        if (! $user->can($permission)) {
            throw AcademicRuleException::because('err.rec.permission');
        }
    }

    protected function event(RecordsDocument $doc, string $event, ?string $note, ?User $user): void
    {
        RecordsDocumentEvent::query()->create([
            'school_id' => $doc->school_id, 'document_id' => $doc->id, 'event' => $event,
            'note' => $note ? mb_substr($note, 0, 500) : null, 'user_id' => $user?->id,
        ]);
    }
}
