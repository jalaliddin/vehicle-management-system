<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\FuelStationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class FuelStation extends Model
{
    /** @use HasFactory<FuelStationFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'organization_id',
        'name',
        'fuel_type',
        'capacity',
        'current_balance',
        'min_threshold',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'decimal:2',
            'current_balance' => 'decimal:2',
            'min_threshold' => 'decimal:2',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FuelTransaction::class);
    }

    public function fuelers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'fuel_station_user');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(FuelShift::class);
    }

    public function openShift(): HasOne
    {
        return $this->hasOne(FuelShift::class)->where('status', 'open');
    }

    /**
     * Unit of the station's fuel: litres or cubic metres.
     */
    public function unit(): string
    {
        return config("fuel.units.{$this->fuel_type}", 'L');
    }

    public function getPercentageAttribute(): float
    {
        if ($this->capacity <= 0) {
            return 0;
        }

        return round(($this->current_balance / $this->capacity) * 100, 1);
    }
}
