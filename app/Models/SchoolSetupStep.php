<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

/** One saved decision of the setup wizard: a step the school completed or deliberately skipped. */
class SchoolSetupStep extends Model
{
    use BelongsToSchool;

    public const DONE = 'done';

    public const SKIPPED = 'skipped';

    protected $fillable = ['school_id', 'step', 'status', 'completed_by', 'completed_at'];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }
}