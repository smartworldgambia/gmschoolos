<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class InventoryLocation extends Model
{
    use BelongsToSchool;

    protected $guarded = ['id'];
}
