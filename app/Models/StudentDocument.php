<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentDocument extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'student_id', 'admission_application_id', 'type', 'original_name', 'path', 'mime', 'size', 'uploaded_by'];

    public const TYPES = ['birth_certificate', 'previous_report', 'transfer_letter', 'medical', 'photo', 'other'];
}
