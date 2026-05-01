<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'state_number',
        'model',
        'manufacture_year',
        'chassis_number',
        'body_type',
        'tech_passport_series',
        'tech_passport_number',
        'engine_number',
        'empty_weight',
        'full_weight',
        'color',
        'engine_power',
        'seat_count',
        'vehicle_type',
        'fuel_norm',
        'fuel_norm_ac',
        'fuel_type',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'manufacture_year' => 'integer',
            'empty_weight' => 'decimal:2',
            'full_weight' => 'decimal:2',
            'fuel_norm' => 'decimal:2',
            'fuel_norm_ac' => 'decimal:2',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function driver(): HasOne
    {
        return $this->hasOne(Driver::class);
    }

    public function waybills(): HasMany
    {
        return $this->hasMany(Waybill::class);
    }

    public function fuelTransactions(): HasMany
    {
        return $this->hasMany(FuelTransaction::class);
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'active' => 'success',
            'broken' => 'error',
            'maintenance' => 'warning',
            default => 'grey',
        };
    }
}
