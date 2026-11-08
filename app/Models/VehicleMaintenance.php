<?php

namespace App\Models;

use App\Models\Concerns\BelongsToSchool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleMaintenance extends Model
{
    use BelongsToSchool;

    protected $table = 'vehicle_maintenance';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'performed_on' => 'date',
            'next_due_on' => 'date',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TransportVehicle::class, 'transport_vehicle_id');
    }
}
