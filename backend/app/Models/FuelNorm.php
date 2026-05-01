<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FuelNorm extends Model
{
    protected $fillable = [
        'route_name',
        'route_number',
        'vehicle_model',
        'norm_without_ac',
        'norm_with_ac',
        'heating_norm',
        'fuel_type',
    ];

    protected function casts(): array
    {
        return [
            'norm_without_ac' => 'decimal:2',
            'norm_with_ac' => 'decimal:2',
            'heating_norm' => 'decimal:2',
        ];
    }
}
