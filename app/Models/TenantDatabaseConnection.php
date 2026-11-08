<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantDatabaseConnection extends Model
{
    protected $fillable = [
        'school_id', 'connection_name', 'driver', 'host', 'port', 'database',
        'username', 'password_encrypted', 'status', 'notes',
    ];

    protected function casts(): array
    {
        return ['notes' => 'array'];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
