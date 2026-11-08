<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportBatch extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'uploaded_by', 'type', 'original_filename', 'storage_path', 'status',
        'total_rows', 'processed_rows', 'success_rows', 'failed_rows', 'errors',
        'started_at', 'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'errors' => 'array',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
