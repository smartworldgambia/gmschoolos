<?php

namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('fees.view');
    }

    public function view(User $user, Invoice $invoice): bool
    {
        if (! $user->can('fees.view')) {
            return false;
        }

        if (! $user->school_id || (int) $user->school_id !== (int) $invoice->school_id) {
            return false;
        }

        if ($user->isLearnerPortal()) {
            return (int) $user->studentProfile?->id === (int) $invoice->student_id;
        }

        if ($user->isGuardianPortal()) {
            $guardian = $user->guardianProfile;

            return $guardian
                ? $guardian->students()->where('students.id', $invoice->student_id)->exists()
                : false;
        }

        return true;
    }

    public function create(User $user): bool
    {
        return $user->can('fees.manage');
    }
}