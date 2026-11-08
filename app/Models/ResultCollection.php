<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ResultCollection extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'candidate_id', 'received_on', 'collected_on', 'collector_name', 'collector_relationship', 'collector_id_type', 'collector_id_number', 'staff_user_id', 'acknowledgement', 'acknowledged_at', 'status', 'reminders_sent', 'last_reminded_at', 'notes'];

    protected $hidden = ['collector_id_number'];

    protected function casts(): array
    {
        return [
            'received_on' => 'date',
            'collected_on' => 'date',
            'acknowledged_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'collector_id_number' => 'encrypted',
        ];
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ExternalCandidate::class, 'candidate_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_user_id');
    }
}
