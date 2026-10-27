<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Support\Hr\DueDates;
use Database\Factories\SafetyBriefingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class SafetyBriefing extends Model
{
    /** @use HasFactory<SafetyBriefingFactory> */
    use Auditable, HasFactory;

    protected $fillable = [
        'organization_id',
        'type',
        'conducted_on',
        'topic',
        'instructor_employee_id',
        'instructor_name',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'conducted_on' => 'date',
        ];
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'instructor_employee_id');
    }

    public function participants(): BelongsToMany
    {
        return $this->belongsToMany(Employee::class, 'safety_briefing_participants')->withPivot('next_due_date');
    }

    /**
     * Set the participants and each one's next repeated-briefing date. Existing
     * participants keep their date unless $recompute (the date or type changed).
     *
     * @param  list<int>  $employeeIds
     */
    public function syncParticipants(array $employeeIds, bool $recompute = true): void
    {
        $current = $this->participants()->get()->mapWithKeys(fn (Employee $e) => [$e->id => $e->pivot->next_due_date]);
        $before = $current->keys()->sort()->values()->all();

        $rows = Employee::with('position')->whereKey($employeeIds)->get()
            ->mapWithKeys(fn (Employee $employee) => [$employee->id => [
                'next_due_date' => ! $recompute && $current->has($employee->id)
                    ? $current[$employee->id]
                    : DueDates::briefingNextDue($this->conducted_on, $this->type, $employee->position?->briefing_months)?->toDateString(),
            ]])
            ->all();

        $this->participants()->sync($rows);

        $after = collect($employeeIds)->map(fn ($id) => (int) $id)->sort()->values()->all();

        if ($before !== $after) {
            AuditLog::record($this, 'participants_synced', ['participant_ids' => $before], ['participant_ids' => $after]);
        }
    }
}
