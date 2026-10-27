<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\FuelTransactionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelTransaction extends Model
{
    /** @use HasFactory<FuelTransactionFactory> */
    use Auditable, HasFactory;

    public const KIND_RECEIPT = 'receipt';

    public const KIND_REFUEL = 'refuel';

    public const KIND_ADJUSTMENT = 'adjustment';

    /**
     * Document number of the row recording a station's pre-ledger balance.
     */
    public const OPENING_DOCUMENT = "Boshlang'ich qoldiq";

    protected $fillable = [
        'fuel_station_id',
        'kind',
        'fuel_shift_id',
        'fuel_supplier_id',
        'document_number',
        'document_date',
        'document_amount',
        'driver_id',
        'odometer',
        'previous_odometer',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
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
            'document_amount' => 'decimal:2',
            'document_date' => 'date',
            'odometer' => 'integer',
            'previous_odometer' => 'integer',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Rows that count towards balances: not cancelled.
     *
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('cancelled_at');
    }

    public function auditOrganizationId(): ?int
    {
        return $this->fuelStation?->organization_id;
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(FuelShift::class, 'fuel_shift_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(FuelSupplier::class, 'fuel_supplier_id');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
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
