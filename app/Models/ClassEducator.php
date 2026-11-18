<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassEducator extends Model
{
    use BelongsToSchool;

    public const LEAD = 'lead';

    public const ASSISTANT = 'assistant';

    public const SPECIALIST = 'specialist';

    public const ROLES = [self::LEAD, self::ASSISTANT, self::SPECIALIST];

    protected $fillable = [
        'school_id',
        'school_class_id',
        'user_id',
        'role',
    ];

    public function schoolClass(): BelongsTo
    {
        return $this->belongsTo(SchoolClass::class, 'school_class_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
