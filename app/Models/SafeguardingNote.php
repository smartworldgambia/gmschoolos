<?php

namespace App\Models;

use App\Casts\SensitiveText;
use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SafeguardingNote extends Model
{
    use BelongsToSchool;

    protected $guarded = ['id'];

    protected $hidden = ['body'];

    protected function casts(): array
    {
        return ['is_confidential' => 'boolean', 'body' => SensitiveText::class];
    }

    public function case(): BelongsTo
    {
        return $this->belongsTo(SafeguardingCase::class, 'safeguarding_case_id');
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }
}
