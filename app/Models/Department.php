<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    use BelongsToSchool;

    protected $fillable = ['school_id', 'name', 'code', 'head_user_id'];

    public function head(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(User::class, 'head_user_id');
    }
}