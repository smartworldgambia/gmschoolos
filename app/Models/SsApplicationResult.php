<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SsApplicationResult extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'application_id', 'subject_code', 'subject_name', 'grade'];

    protected function casts(): array
    {
        return [
        ];
    }
}
