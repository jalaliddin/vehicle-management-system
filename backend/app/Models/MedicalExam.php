<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Carbon\CarbonInterface;
use Database\Factories\MedicalExamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalExam extends Model
{
    /** @use HasFactory<MedicalExamFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'employee_id',
        'type',
        'exam_date',
        'institution',
        'result',
        'restrictions',
        'next_due_date',
        'document_path',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'exam_date' => 'date',
            'next_due_date' => 'date',
        ];
    }

    /**
     * valid, due_soon, overdue or unfit — what deadline tracking needs, without
     * revealing medical details.
     */
    public function status(?CarbonInterface $today = null): string
    {
        $today = ($today ?? now())->copy()->startOfDay();

        return match (true) {
            $this->result === 'unfit' => 'unfit',
            $this->next_due_date === null => 'valid',
            $this->next_due_date->lt($today) => 'overdue',
            $this->next_due_date->lte($today->copy()->addDays(config('hr.alert_due_soon_days'))) => 'due_soon',
            default => 'valid',
        };
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
