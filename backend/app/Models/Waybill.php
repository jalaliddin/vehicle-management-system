<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Waybill extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'waybill_number',
        'organization_id',
        'vehicle_id',
        'driver_id',
        'vehicle_type',
        'tabel_number',
        'created_date',
        'valid_until',
        'trip_count',
        'destination',
        'destination_organization',
        'route_coordinates',
        'planned_km',
        'actual_km',
        'fuel_issued',
        'fuel_consumed',
        'fuel_returned',
        'has_ac',
        'status',
        'mechanic_id',
        'mechanic_checked_at',
        'mechanic_notes',
        'mechanic_passed',
        'doctor_id',
        'doctor_checked_at',
        'doctor_notes',
        'doctor_passed',
        'approved_by',
        'approved_at',
        'departed_at',
        'arrived_at',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'created_date' => 'date',
            'valid_until' => 'date',
            'route_coordinates' => 'array',
            'has_ac' => 'boolean',
            'mechanic_passed' => 'boolean',
            'doctor_passed' => 'boolean',
            'mechanic_checked_at' => 'datetime',
            'doctor_checked_at' => 'datetime',
            'approved_at' => 'datetime',
            'departed_at' => 'datetime',
            'arrived_at' => 'datetime',
            'planned_km' => 'decimal:2',
            'actual_km' => 'decimal:2',
            'fuel_issued' => 'decimal:2',
            'fuel_consumed' => 'decimal:2',
            'fuel_returned' => 'decimal:2',
        ];
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (Waybill $waybill) {
            $year = date('Y');
            $month = date('m');
            $count = static::whereYear('created_at', $year)->whereMonth('created_at', $month)->count() + 1;
            $waybill->waybill_number = "UTG-{$year}{$month}-".str_pad($count, 4, '0', STR_PAD_LEFT);
        });
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function mechanic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mechanic_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function approvedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function fuelTransactions(): HasMany
    {
        return $this->hasMany(FuelTransaction::class);
    }

    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'Yaratilgan',
            'mechanic_check' => 'Mexanik tekshiruvi',
            'mechanic_ok' => 'Mexanik tasdiqladi',
            'doctor_check' => 'Shifokor tekshiruvi',
            'doctor_ok' => 'Shifokor tasdiqladi',
            'hq_review' => 'Markaziy apparat',
            'approved' => 'Tasdiqlandi',
            'in_progress' => "Yo'lda",
            'completed' => 'Bajarildi',
            'cancelled' => 'Bekor qilindi',
            default => $this->status,
        };
    }

    public function getStatusColorAttribute(): string
    {
        return match ($this->status) {
            'draft' => 'grey',
            'mechanic_check', 'doctor_check', 'hq_review' => 'warning',
            'mechanic_ok', 'doctor_ok' => 'info',
            'approved', 'in_progress' => 'success',
            'completed' => 'primary',
            'cancelled' => 'error',
            default => 'grey',
        };
    }
}
