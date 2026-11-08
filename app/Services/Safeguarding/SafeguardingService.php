<?php

namespace App\Services\Safeguarding;

use App\Exceptions\AcademicRuleException;
use App\Models\SafeguardingCase;
use App\Models\SafeguardingNote;
use App\Models\Student;
use App\Models\User;
use App\Services\Ops\Ops;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SafeguardingService
{
    public const CATEGORIES = ['welfare', 'bullying', 'abuse_concern', 'other'];

    public const SEVERITIES = ['low', 'medium', 'high', 'critical'];

    public function open(User $actor, array $data): SafeguardingCase
    {
        if (empty($data['student_id']) && trim((string) ($data['subject_name'] ?? '')) === '') {
            throw AcademicRuleException::because('err.sg.subject');
        }
        if (! empty($data['student_id'])) {
            Student::query()->findOrFail($data['student_id']);
        }

        $case = SafeguardingCase::query()->create([
            'reference' => 'SG-'.now()->format('Ymd').'-'.Str::upper(Str::random(4)),
            'category' => $data['category'], 'severity' => $data['severity'], 'status' => 'open',
            'student_id' => $data['student_id'] ?? null, 'subject_name' => $data['subject_name'] ?? null,
            'summary' => $data['summary'], 'occurred_on' => $data['occurred_on'] ?? null, 'reported_by' => $actor->id,
        ]);
        Ops::audit('sg.opened', 'Safeguarding case '.$case->reference.' opened', ['case_id' => $case->id, 'severity' => $case->severity, 'category' => $case->category]);
        if ($case->severity === 'critical') {
            Ops::notify(Ops::usersWith('safeguarding.manage', $actor->id), 'Critical safeguarding case '.$case->reference, 'A critical concern was recorded. Open the case in Safeguarding to read it.', route('safeguarding.show', $case), 'critical', 'safeguarding');
        }

        return $case;
    }

    public function assign(SafeguardingCase $case, int $userId): SafeguardingCase
    {
        $assignee = User::query()->where('school_id', $case->school_id)->find($userId);
        if (! $assignee || ! $assignee->can('safeguarding.manage')) {
            throw AcademicRuleException::because('err.sg.assignee');
        }
        $case->update(['assigned_to' => $assignee->id]);
        Ops::audit('sg.assigned', 'Safeguarding case '.$case->reference.' assigned', ['case_id' => $case->id, 'assigned_to' => $assignee->id]);
        Ops::notify([$assignee], 'Safeguarding case assigned: '.$case->reference, 'A case was assigned to you. Open it in Safeguarding.', route('safeguarding.show', $case), 'warning', 'safeguarding');

        return $case;
    }

    public function addNote(SafeguardingCase $case, User $actor, string $body): SafeguardingNote
    {
        $note = SafeguardingNote::query()->create([
            'safeguarding_case_id' => $case->id, 'author_id' => $actor->id, 'body' => $body, 'is_confidential' => true,
        ]);
        if ($case->status === 'open') {
            $case->update(['status' => 'investigating']);
        }
        Ops::audit('sg.note', 'Confidential note added to '.$case->reference, ['case_id' => $case->id, 'note_id' => $note->id]);

        return $note;
    }

    public function close(SafeguardingCase $case, User $actor, string $note): SafeguardingCase
    {
        $note = mb_substr(trim($note), 0, 5000);
        if ($note === '') {
            throw AcademicRuleException::because('err.sg.closeNote');
        }

        return DB::transaction(function () use ($case, $actor, $note) {
            $case = SafeguardingCase::query()->lockForUpdate()->findOrFail($case->id);
            if ($case->status === 'closed') {
                throw AcademicRuleException::because('err.sg.alreadyClosed');
            }
            $case->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $actor->id, 'closure_note' => $note]);
            Ops::audit('sg.closed', 'Safeguarding case '.$case->reference.' closed', ['case_id' => $case->id]);

            return $case;
        });
    }

    /** Every opening of a case file is recorded. Do not put the summary in the audit properties. */
    public function logAccess(SafeguardingCase $case, User $actor): void
    {
        Ops::audit('sg.accessed', 'Safeguarding case '.$case->reference.' opened', ['case_id' => $case->id, 'by' => $actor->id]);
    }
}
