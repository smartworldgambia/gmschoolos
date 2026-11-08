<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FeeCategory extends Model
{
    use BelongsToSchool;

    protected $guarded = ['id'];

    public function structures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }
}
