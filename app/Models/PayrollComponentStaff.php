<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollComponentStaff extends Model
{
    use BelongsToSchool;

    protected $table = 'payroll_component_staff';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'value_override' => 'float',
        ];
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(PayrollComponent::class, 'payroll_component_id');
    }
}
