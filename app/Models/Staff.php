<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Staff extends Model
{
    use BelongsToSchool;

    protected $table = 'staff';

    protected $guarded = ['id'];

    /** Payroll destination details are encrypted at rest and never serialised by accident. */
    protected $hidden = ['bank_name', 'bank_account'];

    protected function casts(): array
    {
        return [
            'hired_on' => 'date',
            'exited_on' => 'date',
            'bank_name' => 'encrypted',
            'bank_account' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(JobPosition::class, 'job_position_id');
    }

    public function contracts(): HasMany
    {
        return $this->hasMany(StaffContract::class)->orderByDesc('starts_on');
    }

    public function getFullNameAttribute(): string
    {
        return trim($this->first_name.' '.$this->last_name);
    }
}
