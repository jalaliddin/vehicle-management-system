<?php

namespace App\Services\Hr;

use App\Models\Driver;
use App\Models\Employee;
use App\Models\PpeIssue;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Overdue and soon-due medical exams, briefings, PPE and driving licenses of
 * active employees the user may see.
 */
class ComplianceService
{
    public const CATEGORIES = ['medical', 'briefing', 'ppe', 'license'];

    /**
     * @param  array{organization_id?: int|string|null, category?: string|null, state?: string|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function alerts(User $user, array $filters = []): Collection
    {
        $today = CarbonImmutable::today();
        $soon = $today->addDays(config('hr.alert_due_soon_days'));
        $categories = empty($filters['category']) ? self::CATEGORIES : [$filters['category']];

        $employees = $this->employees($user, $filters['organization_id'] ?? null)
            ->with(['latestMedicalExam', 'position:id,name,medical_exam_months,briefing_months', 'department:id,name'])
            ->get()
            ->keyBy('id');

        $alerts = collect();

        foreach ($categories as $category) {
            $alerts = $alerts->merge(match ($category) {
                'medical' => $this->medical($employees),
                'briefing' => $this->briefing($employees),
                'ppe' => $this->ppe($employees, $soon),
                'license' => $this->license($employees, $soon),
                default => [],
            });
        }

        return $alerts
            ->map(fn (array $alert) => $this->finish($alert, $employees, $today, $soon))
            ->filter(fn (?array $alert) => $alert !== null)
            ->when(! empty($filters['state']), fn ($c) => $c->where('state', $filters['state']))
            ->sortBy(fn (array $alert) => $alert['days_left'] ?? PHP_INT_MIN)
            ->values();
    }

    /**
     * @return array<string, array{overdue: int, due_soon: int}>
     */
    public function summary(User $user): array
    {
        $alerts = $this->alerts($user);

        return collect(self::CATEGORIES)->mapWithKeys(fn (string $category) => [$category => [
            'overdue' => $alerts->where('category', $category)->where('state', 'overdue')->count(),
            'due_soon' => $alerts->where('category', $category)->where('state', 'due_soon')->count(),
        ]])->all();
    }

    /**
     * @return Builder<Employee>
     */
    private function employees(User $user, mixed $organizationId): Builder
    {
        return Employee::query()
            ->where('status', 'active')
            ->when(! $user->canAccessAllOrganizations(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->when($organizationId, fn ($q) => $q->where('organization_id', $organizationId));
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<array<string, mixed>>
     */
    private function medical(Collection $employees): array
    {
        $alerts = [];

        foreach ($employees as $employee) {
            $exam = $employee->latestMedicalExam;

            if ($exam === null) {
                if ($employee->position?->medical_exam_months !== null) {
                    $alerts[] = ['category' => 'medical', 'employee_id' => $employee->id, 'due_date' => null, 'detail' => "Tibbiy ko'rik o'tkazilmagan"];
                }

                continue;
            }

            if ($exam->result === 'unfit') {
                $alerts[] = ['category' => 'medical', 'employee_id' => $employee->id, 'due_date' => null, 'detail' => 'Tibbiy xulosa: yaroqsiz'];
            } elseif ($exam->next_due_date !== null) {
                $alerts[] = ['category' => 'medical', 'employee_id' => $employee->id, 'due_date' => $exam->next_due_date, 'detail' => "Davriy tibbiy ko'rik"];
            }
        }

        return $alerts;
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<array<string, mixed>>
     */
    private function briefing(Collection $employees): array
    {
        $required = $employees->filter(fn (Employee $e) => $e->position?->briefing_months !== null);

        $latestDue = DB::table('safety_briefing_participants')
            ->whereIn('employee_id', $required->keys())
            ->whereNotNull('next_due_date')
            ->groupBy('employee_id')
            ->pluck(DB::raw('MAX(next_due_date)'), 'employee_id');

        return $required->map(fn (Employee $employee) => [
            'category' => 'briefing',
            'employee_id' => $employee->id,
            'due_date' => isset($latestDue[$employee->id]) ? CarbonImmutable::parse($latestDue[$employee->id]) : null,
            'detail' => isset($latestDue[$employee->id]) ? "Takroriy yo'riqnoma" : "Yo'riqnoma o'tkazilmagan",
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<array<string, mixed>>
     */
    private function ppe(Collection $employees, CarbonInterface $soon): array
    {
        return PpeIssue::with('ppeItem:id,name')
            ->whereIn('employee_id', $employees->keys())
            ->where('status', 'in_use')
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<=', $soon)
            ->get()
            ->map(fn (PpeIssue $issue) => [
                'category' => 'ppe', 'employee_id' => $issue->employee_id, 'due_date' => $issue->due_on, 'detail' => $issue->ppeItem->name,
            ])->all();
    }

    /**
     * @param  Collection<int, Employee>  $employees
     * @return list<array<string, mixed>>
     */
    private function license(Collection $employees, CarbonInterface $soon): array
    {
        return Driver::whereIn('employee_id', $employees->keys())
            ->where('status', '!=', 'inactive')
            ->whereNotNull('license_expiry')
            ->whereDate('license_expiry', '<=', $soon)
            ->get()
            ->map(fn (Driver $driver) => [
                'category' => 'license', 'employee_id' => $driver->employee_id, 'due_date' => $driver->license_expiry,
                'detail' => "Haydovchilik guvohnomasi {$driver->license_number}",
            ])->all();
    }

    /**
     * Add the state and the employee's details; drop deadlines not yet near.
     *
     * @param  array<string, mixed>  $alert
     * @param  Collection<int, Employee>  $employees
     * @return array<string, mixed>|null
     */
    private function finish(array $alert, Collection $employees, CarbonInterface $today, CarbonInterface $soon): ?array
    {
        $due = $alert['due_date'];

        if ($due !== null && $due->gt($soon)) {
            return null;
        }

        $employee = $employees[$alert['employee_id']];

        return array_merge($alert, [
            'state' => $due === null || $due->lt($today) ? 'overdue' : 'due_soon',
            'due_date' => $due?->toDateString(),
            'days_left' => $due === null ? null : (int) round($today->diffInDays($due, false)),
            'employee_name' => $employee->full_name,
            'organization_id' => $employee->organization_id,
            'department' => $employee->department?->name,
            'position' => $employee->position?->name,
        ]);
    }
}
