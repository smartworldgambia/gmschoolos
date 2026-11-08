<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class NewsPost extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'title', 'slug', 'excerpt', 'body', 'image_url', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }
}
