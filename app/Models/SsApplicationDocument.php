<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SsApplicationDocument extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'application_id', 'type', 'original_name', 'path', 'mime', 'size'];

    protected function casts(): array
    {
        return [
        ];
    }
}
