<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\FuelShiftFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class FuelShift extends Model
{
    /** @use HasFactory<FuelShiftFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'fuel_station_id',
        'status',
        'opened_by',
        'opened_at',
        'opening_balance',
        'opening_measured',
        'closed_by',
        'closed_at',
        'closing_balance',
        'closing_measured',
        'discrepancy',
        'discrepancy_note',
    ];

    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'opening_balance' => 'decimal:2',
            'opening_measured' => 'decimal:2',
            'closing_balance' => 'decimal:2',
            'closing_measured' => 'decimal:2',
            'discrepancy' => 'decimal:2',
        ];
    }

    public function isOpen(): bool
    {
        return $this->status === 'open';
    }

    public function auditOrganizationId(): ?int
    {
        return $this->station?->organization_id;
    }

    public function station(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class, 'fuel_station_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(FuelTransaction::class);
    }

    public function openedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }
}
