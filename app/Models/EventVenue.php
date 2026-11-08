<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class EventVenue extends Model
{
    use BelongsToSchool;

    protected $guarded = ['id'];
}
