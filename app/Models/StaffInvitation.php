<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** The emailed token is never stored - only its SHA-256 hash. */
class StaffInvitation extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'name', 'email', 'role', 'token_hash', 'invited_by', 'expires_at', 'accepted_at', 'revoked_at', 'user_id',
        'kind', 'student_id', 'guardian_id', 'class_subject_ids',
    ];

    protected function casts(): array
    {
        return [
            'class_subject_ids' => 'array',
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->revoked_at === null && $this->expires_at?->isFuture() === true;
    }

    public function state(): string
    {
        return match (true) {
            $this->accepted_at !== null => 'accepted',
            $this->revoked_at !== null => 'revoked',
            $this->expires_at === null || $this->expires_at->isPast() => 'expired',
            default => 'pending',
        };
    }
}