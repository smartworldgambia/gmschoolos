<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;

class SchoolInquiry extends Model
{
    use BelongsToSchool;

    protected $fillable = [
        'school_id', 'name', 'email', 'phone', 'subject', 'message', 'status', 'ip_address',
    ];
}
