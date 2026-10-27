<?php

namespace App\Http\Controllers\Api;

use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Models\EmploymentEvent;
use App\Models\Organization;
use App\Models\PositionPpeNorm;
use App\Models\PpeIssue;
use App\Models\SafetyBriefing;
use App\Models\User;
use App\Services\Hr\LeaveBalance;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HrReportController extends Controller
{
    /**
     * Employees on the payroll on a date, by organization and department.
     */
    public function headcount(Request $request): JsonResponse
    {
        Gate::authorize(Permission::EmployeesView->value);

        $request->validate(['date' => ['nullable', 'date'], 'organization_id' => ['nullable', 'integer']]);
        $date = CarbonImmutable::parse($request->date ?? today())->startOfDay();
        $user = $request->user();

        $inScope = fn (?int $organizationId) => $organizationId !== null
            && $user->canAccessOrganization($organizationId)
            && (! $request->organization_id || (int) $request->organization_id === $organizationId);

        $assignments = Employee::with(['events' => fn ($q) => $q->effective()])
            ->when(! $user->canAccessAllOrganizations(), fn ($q) => $q->where(fn ($q2) => $q2
                ->where('organization_id', $user->organization_id)
                ->orWhereIn('id', EmploymentEvent::select('employee_id')->where('organization_id', $user->organization_id))))
            ->get()
            ->map(fn (Employee $employee) => $this->assignmentOn($employee, $date))
            ->filter(fn (?array $assignment) => $assignment !== null && $inScope($assignment['organization_id']));

        $organizations = Organization::whereKey($assignments->pluck('organization_id')->unique())->pluck('short_name', 'id');
        $departments = Department::whereKey($assignments->pluck('department_id')->filter()->unique())->pluck('name', 'id');

        $rows = $assignments
            ->groupBy(fn (array $a) => $a['organization_id'].':'.($a['department_id'] ?? ''))
            ->map(fn ($group) => [
                'organization_id' => $group->first()['organization_id'],
                'organization' => $organizations[$group->first()['organization_id']] ?? null,
                'department_id' => $group->first()['department_id'],
                'department' => $departments[$group->first()['department_id']] ?? null,
                'count' => $group->count(),
            ])
            ->sortBy(fn (array $row) => $row['organization'].'|'.$row['department'])
            ->values();

        return response()->json($rows);
    }

    /**
     * Hire, transfer and dismissal orders that took effect in the period.
     */
    public function movement(Request $request): JsonResponse
    {
        Gate::authorize(Permission::EmployeesView->value);

        [$from, $to] = $this->period($request);

        $events = EmploymentEvent::with(['employee', 'organization:id,short_name', 'department:id,name', 'position:id,name'])
            ->whereNull('cancelled_at')
            ->whereBetween('effective_date', [$from->toDateString(), $to->toDateString()])
            ->whereHas('employee', fn ($q) => $this->scope($q, $request))
            ->orderBy('effective_date')
            ->get()
            ->map(fn (EmploymentEvent $event) => [
                'type' => $event->type,
                'employee_id' => $event->employee_id,
                'employee_name' => $event->employee->full_name,
                'effective_date' => $event->effective_date->toDateString(),
                'order_number' => $event->order_number,
                'organization' => $event->organization?->short_name,
                'department' => $event->department?->name,
                'position' => $event->position?->name,
                'dismissal_reason' => $event->dismissal_reason ? config('hr.dismissal_reasons')[$event->dismissal_reason] ?? $event->dismissal_reason : null,
            ]);

        return response()->json([
            'hired' => $events->where('type', 'hire')->values(),
            'transferred' => $events->where('type', 'transfer')->values(),
            'dismissed' => $events->where('type', 'dismissal')->values(),
            'counts' => [
                'hired' => $events->where('type', 'hire')->count(),
                'transferred' => $events->where('type', 'transfer')->count(),
                'dismissed' => $events->where('type', 'dismissal')->count(),
            ],
        ]);
    }

    /**
     * Employees away on a date and why.
     */
    public function absent(Request $request): JsonResponse
    {
        Gate::authorize(Permission::EmployeesView->value);

        $request->validate(['date' => ['nullable', 'date']]);
        $date = CarbonImmutable::parse($request->date ?? today())->toDateString();

        $rows = EmployeeAbsence::with(['employee.department:id,name', 'employee.position:id,name'])
            ->where('is_planned', false)
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->whereHas('employee', fn ($q) => $this->scope($q, $request)->where('status', 'active'))
            ->get()
            ->map(fn (EmployeeAbsence $absence) => [
                'employee_id' => $absence->employee_id,
                'employee_name' => $absence->employee->full_name,
                'department' => $absence->employee->department?->name,
                'position' => $absence->employee->position?->name,
                'type' => $absence->type,
                'start_date' => $absence->start_date->toDateString(),
                'end_date' => $absence->end_date->toDateString(),
            ])
            ->sortBy('employee_name')
            ->values();

        return response()->json($rows);
    }

    /**
     * Planned against actual annual leave of every active employee for a year.
     */
    public function leaveSchedule(Request $request): JsonResponse
    {
        Gate::authorize(Permission::EmployeesView->value);

        $request->validate(['year' => ['nullable', 'integer', 'min:2000', 'max:2100']]);
        $year = (int) ($request->year ?? now()->year);
        $yearStart = CarbonImmutable::create($year)->startOfYear();
        $yearEnd = $yearStart->endOfYear()->startOfDay();

        $rows = $this->scope(Employee::query(), $request)
            ->where('status', 'active')
            ->with(['department:id,name', 'absences' => fn ($q) => $q->where('type', 'annual_leave')->where('is_planned', true)
                ->whereDate('start_date', '<=', $yearEnd)->whereDate('end_date', '>=', $yearStart)])
            ->orderBy('last_name')
            ->get()
            ->map(fn (Employee $employee) => [
                'employee_id' => $employee->id,
                'employee_name' => $employee->full_name,
                'department' => $employee->department?->name,
                'entitlement' => $employee->annual_leave_days,
                'planned_days' => $employee->absences->sum(fn (EmployeeAbsence $a) => (int) $a->start_date->max($yearStart)
                    ->diffInDays($a->end_date->min($yearEnd)) + 1),
                'planned' => $employee->absences->map(fn (EmployeeAbsence $a) => [
                    'start_date' => $a->start_date->toDateString(), 'end_date' => $a->end_date->toDateString(),
                ])->values(),
                'actual_days' => LeaveBalance::forYear($employee, $year)['used'],
            ]);

        return response()->json($rows);
    }

    public function briefingJournal(Request $request): JsonResponse
    {
        Gate::authorize(Permission::OhsView->value);

        [$from, $to] = $this->period($request);
        $user = $request->user();

        $briefings = SafetyBriefing::with(['organization:id,short_name', 'instructor', 'participants.position:id,name'])
            ->when(! $user->canAccessAllOrganizations(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->when($request->organization_id, fn ($q) => $q->where('organization_id', $request->organization_id))
            ->whereBetween('conducted_on', [$from->toDateString(), $to->toDateString()])
            ->orderBy('conducted_on')
            ->get()
            ->map(fn (SafetyBriefing $briefing) => [
                'id' => $briefing->id,
                'conducted_on' => $briefing->conducted_on->toDateString(),
                'type' => $briefing->type,
                'topic' => $briefing->topic,
                'organization' => $briefing->organization->short_name,
                'instructor' => $briefing->instructor?->full_name ?? $briefing->instructor_name,
                'participants' => $briefing->participants->sortBy('last_name')->map(fn (Employee $employee) => [
                    'id' => $employee->id,
                    'full_name' => $employee->full_name,
                    'position' => $employee->position?->name,
                ])->values(),
            ]);

        return response()->json($briefings);
    }

    public function ppeIssues(Request $request): JsonResponse
    {
        Gate::authorize(Permission::OhsView->value);

        [$from, $to] = $this->period($request);

        $rows = PpeIssue::with(['employee.department:id,name', 'ppeItem:id,name,unit'])
            ->whereBetween('issued_on', [$from->toDateString(), $to->toDateString()])
            ->whereHas('employee', fn ($q) => $this->scope($q, $request))
            ->orderBy('issued_on')
            ->get()
            ->map(fn (PpeIssue $issue) => [
                'id' => $issue->id,
                'employee_id' => $issue->employee_id,
                'employee_name' => $issue->employee->full_name,
                'department' => $issue->employee->department?->name,
                'ppe_item' => $issue->ppeItem->name,
                'unit' => $issue->ppeItem->unit,
                'quantity' => $issue->quantity,
                'size' => $issue->size,
                'issued_on' => $issue->issued_on->toDateString(),
                'due_on' => $issue->due_on?->toDateString(),
                'status' => $issue->status,
            ]);

        return response()->json($rows);
    }

    /**
     * PPE to buy for the coming months: in-use items falling due plus norms
     * nobody has been issued yet, summed by item and size.
     */
    public function ppeDemand(Request $request): JsonResponse
    {
        Gate::authorize(Permission::OhsView->value);

        $request->validate(['months' => ['nullable', 'in:3,6,12']]);
        $until = today()->addMonths((int) ($request->months ?? 3));

        $employees = $this->scope(Employee::query(), $request)
            ->where('status', 'active')
            ->with(['ppeIssues' => fn ($q) => $q->where('status', 'in_use'), 'position.ppeNorms.ppeItem'])
            ->get();

        $needs = collect();

        foreach ($employees as $employee) {
            $inUse = $employee->ppeIssues->keyBy('ppe_item_id');

            foreach ($employee->position?->ppeNorms ?? [] as $norm) {
                /** @var PositionPpeNorm $norm */
                $issue = $inUse->get($norm->ppe_item_id);

                if ($issue === null) {
                    $needs->push(['item' => $norm->ppeItem, 'size' => $this->sizeFor($employee, $norm->ppeItem->kind), 'quantity' => $norm->quantity]);
                } elseif ($issue->due_on !== null && $issue->due_on->lte($until)) {
                    $needs->push(['item' => $norm->ppeItem, 'size' => $issue->size, 'quantity' => $norm->quantity]);
                }
            }
        }

        $rows = $needs
            ->groupBy(fn (array $need) => $need['item']->id.'|'.$need['size'])
            ->map(fn ($group) => [
                'ppe_item_id' => $group->first()['item']->id,
                'ppe_item' => $group->first()['item']->name,
                'unit' => $group->first()['item']->unit,
                'size' => $group->first()['size'],
                'quantity' => $group->sum('quantity'),
            ])
            ->sortBy(fn (array $row) => $row['ppe_item'].'|'.$row['size'])
            ->values();

        return response()->json($rows);
    }

    /**
     * Limit an employee query to organizations the user may see and the one asked for.
     *
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    private function scope(Builder $query, Request $request): Builder
    {
        /** @var User $user */
        $user = $request->user();

        return $query
            ->when(! $user->canAccessAllOrganizations(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->when($request->organization_id, fn ($q) => $q->where('organization_id', $request->organization_id));
    }

    /**
     * @return array{CarbonImmutable, CarbonImmutable}
     */
    private function period(Request $request): array
    {
        $request->validate([
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'organization_id' => ['nullable', 'integer'],
        ]);

        return [
            CarbonImmutable::parse($request->from ?? today()->startOfYear())->startOfDay(),
            CarbonImmutable::parse($request->to ?? today())->startOfDay(),
        ];
    }

    /**
     * The organization and department an employee held on a date, or null when
     * they were not employed then.
     *
     * @return array{organization_id: int, department_id: int|null}|null
     */
    private function assignmentOn(Employee $employee, CarbonImmutable $date): ?array
    {
        if ($employee->events->isEmpty()) {
            $employed = ($employee->hire_date === null || $employee->hire_date->lte($date))
                && ($employee->dismissal_date === null || $employee->dismissal_date->gt($date))
                && ($employee->isActive() || $employee->dismissal_date !== null);

            return $employed ? ['organization_id' => $employee->organization_id, 'department_id' => $employee->department_id] : null;
        }

        $last = $employee->events->filter(fn (EmploymentEvent $event) => $event->effective_date->lte($date))->last();

        if ($last === null || $last->type === 'dismissal') {
            return null;
        }

        return ['organization_id' => (int) $last->organization_id, 'department_id' => $last->department_id];
    }

    private function sizeFor(Employee $employee, string $kind): ?string
    {
        return match ($kind) {
            'clothing' => $employee->clothing_size,
            'footwear' => $employee->shoe_size,
            default => null,
        };
    }
}
