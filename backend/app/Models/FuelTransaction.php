<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelTransaction extends Model
{
    protected $fillable = [
        'fuel_station_id',
        'waybill_id',
        'vehicle_id',
        'type',
        'amount',
        'fuel_type',
        'price_per_liter',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'price_per_liter' => 'decimal:2',
        ];
    }

    public function fuelStation(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class);
    }

    public function waybill(): BelongsTo
    {
        return $this->belongsTo(Waybill::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
