<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class RecordsDocument extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'type', 'reference', 'verification_token', 'student_id', 'ss_application_id', 'template_id', 'status', 'title', 'payload', 'body', 'exception', 'prepared_by', 'submitted_at', 'approved_by', 'approved_at', 'issued_by', 'issued_at', 'issue_date', 'reprint_count', 'last_reprinted_at', 'revoked_by', 'revoked_at', 'revoked_reason'];

    protected $hidden = ['verification_token'];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'exception' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'issued_at' => 'datetime',
            'issue_date' => 'date',
            'last_reprinted_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'student_id');
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(SsApplication::class, 'ss_application_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(RecordsTemplate::class, 'template_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(RecordsDocumentEvent::class, 'document_id');
    }

    public function preparer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'prepared_by');
    }
}
