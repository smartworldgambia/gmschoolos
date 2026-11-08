<?php

namespace App\Services\Ops;

use App\Models\FeeCategory;
use App\Models\Student;
use App\Models\StudentFee;
use App\Models\User;

/**
 * One pending charge per learner + source key (transport:{route}:{term} / boarding:{hostel}:{term}).
 * Already-invoiced rows are left alone so a later regenerate does not double-bill.
 */
class TermCharge
{
    public static function ensure(Student $student, string $source, string $description, int $amount, string $categoryName, ?int $yearId, ?int $termId, User $actor): StudentFee
    {
        $existing = StudentFee::query()->where('student_id', $student->id)->where('source', $source)->first();
        if ($existing) {
            if ($existing->status === 'pending') {
                $existing->update(['amount' => $amount, 'description' => mb_substr($description, 0, 190), 'academic_year_id' => $yearId, 'term_id' => $termId]);
            }

            return $existing;
        }

        $category = FeeCategory::query()->firstOrCreate(['name' => $categoryName], ['is_active' => true]);

        return StudentFee::query()->create([
            'student_id' => $student->id, 'fee_category_id' => $category->id, 'academic_year_id' => $yearId, 'term_id' => $termId,
            'description' => mb_substr($description, 0, 190), 'amount' => $amount, 'status' => 'pending', 'source' => $source, 'created_by' => $actor->id,
        ]);
    }
}
