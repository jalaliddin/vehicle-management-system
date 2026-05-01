<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'organization_id',
        'full_name',
        'license_number',
        'license_category',
        'vehicle_id',
        'work_experience',
        'pinfl',
        'phone',
        'license_expiry',
        'employment_type',
        'status',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'license_expiry' => 'date',
            'work_experience' => 'integer',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function waybills(): HasMany
    {
        return $this->hasMany(Waybill::class);
    }
}
