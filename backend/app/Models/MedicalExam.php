<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Hr\DueDates;
use Carbon\CarbonInterface;
use Database\Factories\MedicalExamFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicalExam extends Model
{
    /** @use HasFactory<MedicalExamFactory> */
    use Auditable, HasFactory;

    /**
     * Fields only users with ohs.medical.view may see — in responses and in the audit log.
     *
     * @var list<string>
     */
    public const MEDICAL_DETAILS = ['institution', 'result', 'restrictions', 'notes', 'document_path'];

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
     * When the next exam is due: the recorded date, else — for exams taken
     * before the position had a periodicity — exam date plus the position's
     * current periodicity. Load `employee.position` (or set the relation) first.
     */
    public function dueDate(): ?CarbonInterface
    {
        return $this->next_due_date ?? DueDates::medicalNextDue(
            $this->exam_date,
            $this->employee?->position?->medical_exam_months,
            $this->result,
        );
    }

    /**
     * valid, due_soon, overdue or unfit — what deadline tracking needs, without
     * revealing medical details.
     */
    public function status(?CarbonInterface $today = null): string
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $due = $this->dueDate();

        return match (true) {
            $this->result === 'unfit' => 'unfit',
            $due === null => 'valid',
            $due->lt($today) => 'overdue',
            $due->lte($today->copy()->addDays(config('hr.alert_due_soon_days'))) => 'due_soon',
            default => 'valid',
        };
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
