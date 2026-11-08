<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WorkloadModel extends Model
{
    protected $fillable = [
        'profile', 'label', 'assumed_active_users', 'assumed_schools',
        'requests_per_user_per_min', 'estimated_rps', 'estimated_db_connections',
        'recommended_app_replicas', 'recommended_workers', 'assumptions',
        'cost_notes', 'is_certified',
    ];

    protected function casts(): array
    {
        return [
            'assumptions' => 'array',
            'cost_notes' => 'array',
            'is_certified' => 'boolean',
            'estimated_rps' => 'float',
            'requests_per_user_per_min' => 'float',
        ];
    }
}
