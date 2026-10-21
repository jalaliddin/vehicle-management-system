<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Hr\DueDates;
use Database\Factories\EmployeeAbsenceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAbsence extends Model
{
    /** @use HasFactory<EmployeeAbsenceFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'employee_id',
        'type',
        'start_date',
        'end_date',
        'is_planned',
        'document_number',
        'document_date',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'document_date' => 'date',
            'is_planned' => 'boolean',
            'days' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (self $absence) {
            $absence->days = DueDates::absenceDays($absence->start_date, $absence->end_date);
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
