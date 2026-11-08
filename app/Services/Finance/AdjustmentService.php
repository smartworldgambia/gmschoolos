<?php

namespace App\Services\Finance;

use App\Exceptions\AcademicRuleException;
use App\Models\FeeAdjustment;
use App\Models\User;
use App\Services\Ops\Ops;
use App\Services\Ops\OpsSettings;

/**
 * Discounts, scholarships and waivers: requested by one person, approved by another (unless the school explicitly turns
 * self-approval on). An approved adjustment is applied when the learner's NEXT fee invoice is generated; it never rewrites
 * an invoice that already exists (cancel and regenerate it if needed).
 */
class AdjustmentService
{
    public function __construct(private readonly OpsSettings $settings) {}

    public function request(User $actor, int $studentId, string $kind, string $basis, int $value, ?int $categoryId, ?int $yearId, ?int $termId, string $reason): FeeAdjustment
    {
        if (! in_array($kind, ['discount', 'scholarship', 'waiver'], true) || ! in_array($basis, ['percent', 'fixed'], true)) {
            throw AcademicRuleException::because('err.fin.adjustment');
        }
        if ($value < 1 || ($basis === 'percent' && $value > 100)) {
            throw AcademicRuleException::because('err.fin.adjustmentValue');
        }
        if (trim($reason) === '') {
            throw AcademicRuleException::because('err.fin.reasonRequired');
        }

        $a = FeeAdjustment::query()->create([
            'student_id' => $studentId, 'kind' => $kind, 'basis' => $basis, 'value' => $value, 'fee_category_id' => $categoryId,
            'academic_year_id' => $yearId, 'term_id' => $termId, 'reason' => mb_substr(trim($reason), 0, 1000),
            'status' => 'pending', 'requested_by' => $actor->id,
        ]);

        Ops::audit('fin.adjustment.requested', ucfirst($kind).' requested', ['adjustment_id' => $a->id, 'student_id' => $studentId, 'kind' => $kind, 'basis' => $basis, 'value' => $value]);

        return $a;
    }

    public function approve(FeeAdjustment $a, User $actor, ?string $note): FeeAdjustment
    {
        $this->assertPending($a);
        if ((int) $a->requested_by === (int) $actor->id && ! $this->settings->get('finance', 'self_approval', false)) {
            throw AcademicRuleException::because('err.fin.selfApprove');
        }

        $a->forceFill(['status' => 'approved', 'decided_by' => $actor->id, 'decided_at' => now(), 'decision_note' => $note ? mb_substr($note, 0, 500) : null])->save();
        Ops::audit('fin.adjustment.approved', ucfirst($a->kind).' approved', ['adjustment_id' => $a->id, 'student_id' => $a->student_id]);

        return $a;
    }

    public function reject(FeeAdjustment $a, User $actor, string $note): FeeAdjustment
    {
        $this->assertPending($a);
        $a->forceFill(['status' => 'rejected', 'decided_by' => $actor->id, 'decided_at' => now(), 'decision_note' => mb_substr($note, 0, 500)])->save();
        Ops::audit('fin.adjustment.rejected', ucfirst($a->kind).' rejected', ['adjustment_id' => $a->id, 'student_id' => $a->student_id]);

        return $a;
    }

    public function revoke(FeeAdjustment $a, User $actor, string $note): FeeAdjustment
    {
        if ($a->status !== 'approved') {
            throw AcademicRuleException::because('err.fin.notApproved');
        }
        $a->forceFill(['status' => 'revoked', 'decided_by' => $actor->id, 'decided_at' => now(), 'decision_note' => mb_substr($note, 0, 500)])->save();
        Ops::audit('fin.adjustment.revoked', ucfirst($a->kind).' revoked', ['adjustment_id' => $a->id, 'student_id' => $a->student_id]);

        return $a;
    }

    private function assertPending(FeeAdjustment $a): void
    {
        if ($a->status !== 'pending') {
            throw AcademicRuleException::because('err.fin.notPending');
        }
    }
}
