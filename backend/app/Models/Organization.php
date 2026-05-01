<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    protected $fillable = [
        'name',
        'short_name',
        'code',
        'address',
        'phone',
        'director',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function vehicles(): HasMany
    {
        return $this->hasMany(Vehicle::class);
    }

    public function drivers(): HasMany
    {
        return $this->hasMany(Driver::class);
    }

    public function waybills(): HasMany
    {
        return $this->hasMany(Waybill::class);
    }

    public function fuelStations(): HasMany
    {
        return $this->hasMany(FuelStation::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
