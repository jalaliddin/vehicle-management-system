<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Database\Factories\EmploymentEventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmploymentEvent extends Model
{
    /** @use HasFactory<EmploymentEventFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'employee_id',
        'type',
        'effective_date',
        'order_number',
        'order_date',
        'organization_id',
        'department_id',
        'position_id',
        'dismissal_reason',
        'dismissal_article',
        'notes',
        'created_by',
        'cancelled_at',
        'cancelled_by',
        'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => 'date',
            'order_date' => 'date',
            'cancelled_at' => 'datetime',
        ];
    }

    /**
     * Events that have not been cancelled, in the order they took effect.
     *
     * @param  Builder<self>  $query
     */
    public function scopeEffective(Builder $query): void
    {
        $query->whereNull('cancelled_at')->orderBy('effective_date')->orderBy('id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withTrashed();
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
