<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Domain extends Model
{
    protected $fillable = [
        'school_id',
        'host',
        'is_primary',
        'is_custom',
        'primary_on_verify',
        'ssl_active',
        'verification_token',
        'verified_at',
        'last_checked_at',
        'verification_error',
        'verification_method',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'is_custom' => 'boolean',
            'primary_on_verify' => 'boolean',
            'ssl_active' => 'boolean',
            'verified_at' => 'datetime',
            'last_checked_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }
}
