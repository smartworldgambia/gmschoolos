<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class TransportDriver extends Model
{
    use BelongsToSchool;

    protected $guarded = ['id'];
}
