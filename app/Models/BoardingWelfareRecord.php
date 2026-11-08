<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BoardingWelfareRecord extends Model
{
    use BelongsToSchool;

    protected $table = 'boarding_welfare_records';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'note' => \App\Casts\SensitiveText::class,
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }
}
