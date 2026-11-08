<?php

namespace App\Models;

use App\Casts\SensitiveText;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Welfare / child-protection concern. Free text is encrypted at rest and hidden from serialisation by default. */
class SafeguardingCase extends Model
{
    use BelongsToSchool;

    protected $guarded = ['id'];

    protected $hidden = ['summary', 'subject_name', 'closure_note'];

    protected function casts(): array
    {
        return [
            'closed_at' => 'datetime',
            'occurred_on' => 'date',
            'summary' => SensitiveText::class,
            'subject_name' => SensitiveText::class,
            'closure_note' => SensitiveText::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(SafeguardingNote::class);
    }
}
