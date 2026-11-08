<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class ScormPackage extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'title', 'version', 'entry_point', 'package_path', 'status', 'manifest',
    ];

    protected function casts(): array
    {
        return ['manifest' => 'array'];
    }
}
