<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelStation extends Model
{
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

    public function getPercentageAttribute(): float
    {
        if ($this->capacity <= 0) {
            return 0;
        }

        return round(($this->current_balance / $this->capacity) * 100, 1);
    }
}
