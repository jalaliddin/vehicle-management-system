<?php

namespace App\Services\Hr;

use App\Models\AuditLog;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmploymentEvent;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Hire, transfer and dismissal orders. An employee's current assignment is
 * always the replay of their non-cancelled events.
 */
class EmploymentService
{
    /**
     * Create an employee card together with its hire order.
     *
     * @param  array<string, mixed>  $card  Card fields plus organization_id.
     * @param  array{effective_date: string, order_number: string, order_date: string, department_id?: int|null, position_id: int, notes?: string|null}  $hire
     */
    public function createWithHire(array $card, array $hire, User $by): Employee
    {
        $this->ensureDepartmentBelongsTo($hire['department_id'] ?? null, (int) $card['organization_id']);

        return DB::transaction(function () use ($card, $hire, $by) {
            $employee = Employee::create(array_merge($card, ['status' => 'active']));

            $this->record($employee, 'hire', $hire, $by, [
                'organization_id' => $employee->organization_id,
                'department_id' => $hire['department_id'] ?? null,
                'position_id' => $hire['position_id'],
            ]);

            return $employee->refresh();
        });
    }

    /**
     * Rehire a dismissed employee.
     *
     * @param  array{effective_date: string, order_number: string, order_date: string, organization_id?: int|null, department_id?: int|null, position_id: int, notes?: string|null}  $data
     */
    public function hire(Employee $employee, array $data, User $by): EmploymentEvent
    {
        if ($this->isEmployed($employee)) {
            throw ValidationException::withMessages(['type' => 'Xodim allaqachon ishda']);
        }

        $organizationId = (int) ($data['organization_id'] ?? $employee->organization_id);
        $this->ensureDepartmentBelongsTo($data['department_id'] ?? null, $organizationId);

        return $this->record($employee, 'hire', $data, $by, [
            'organization_id' => $organizationId,
            'department_id' => $data['department_id'] ?? null,
            'position_id' => $data['position_id'],
        ]);
    }

    /**
     * @param  array{effective_date: string, order_number: string, order_date: string, organization_id?: int|null, department_id?: int|null, position_id?: int|null, notes?: string|null}  $data
     */
    public function transfer(Employee $employee, array $data, User $by): EmploymentEvent
    {
        $this->ensureEmployed($employee);

        $organizationId = (int) ($data['organization_id'] ?? $employee->organization_id);
        $organizationChanges = $organizationId !== $employee->organization_id;
        $departmentId = array_key_exists('department_id', $data)
            ? $data['department_id']
            : ($organizationChanges ? null : $employee->department_id);
        $positionId = $data['position_id'] ?? $employee->position_id;

        $this->ensureDepartmentBelongsTo($departmentId, $organizationId);

        if (! $organizationChanges && $departmentId == $employee->department_id && $positionId == $employee->position_id) {
            throw ValidationException::withMessages(['type' => "O'tkazishda tashkilot, bo'lim yoki lavozim o'zgarishi kerak"]);
        }

        return $this->record($employee, 'transfer', $data, $by, [
            'organization_id' => $organizationId,
            'department_id' => $departmentId,
            'position_id' => $positionId,
        ]);
    }

    /**
     * @param  array{effective_date: string, order_number: string, order_date: string, dismissal_reason: string, dismissal_article?: string|null, notes?: string|null}  $data
     */
    public function dismiss(Employee $employee, array $data, User $by): EmploymentEvent
    {
        $this->ensureEmployed($employee);

        return $this->record($employee, 'dismissal', $data, $by, [
            'organization_id' => $employee->organization_id,
            'department_id' => $employee->department_id,
            'position_id' => $employee->position_id,
            'dismissal_reason' => $data['dismissal_reason'],
            'dismissal_article' => $data['dismissal_article'] ?? null,
        ]);
    }

    public function cancel(EmploymentEvent $event, string $reason, User $by): void
    {
        $employee = $event->employee;
        $effective = $employee->events()->effective()->get();

        if ($event->cancelled_at !== null || $effective->last()?->id !== $event->id) {
            throw ValidationException::withMessages(['event' => 'Faqat oxirgi buyruqni bekor qilish mumkin']);
        }

        if ($effective->count() === 1) {
            throw ValidationException::withMessages(['event' => "Yagona ishga qabul buyrug'ini bekor qilib bo'lmaydi; xodimni o'chiring"]);
        }

        DB::transaction(function () use ($event, $reason, $by, $employee) {
            $event->update(['cancelled_at' => now(), 'cancelled_by' => $by->id, 'cancel_reason' => $reason]);
            AuditLog::record($event, 'cancelled', null, ['reason' => $reason]);
            $this->recompute($employee);
        });
    }

    /**
     * Rebuild the employee's current assignment from their events and carry
     * its consequences over to the driver profile.
     */
    public function recompute(Employee $employee): void
    {
        $events = $employee->events()->effective()->get();

        if ($events->isEmpty()) {
            return;
        }

        $previousOrganization = $employee->organization_id;

        foreach ($events as $event) {
            $employee->fill([
                'organization_id' => $event->organization_id ?? $employee->organization_id,
                'department_id' => $event->department_id,
                'position_id' => $event->position_id,
            ]);

            if ($event->type === 'hire') {
                $employee->fill(['status' => 'active', 'hire_date' => $event->effective_date, 'dismissal_date' => null]);
            } elseif ($event->type === 'dismissal') {
                $employee->fill(['status' => 'dismissed', 'dismissal_date' => $event->effective_date]);
            }
        }

        $employee->save();
        $this->applyToDriver($employee, $previousOrganization);
    }

    /**
     * Whether the employee currently works here: per their latest event, or,
     * for records migrated without events, per their status.
     */
    public function isEmployed(Employee $employee): bool
    {
        $latest = $employee->events()->effective()->get()->last();

        return $latest === null ? ($employee->exists && $employee->isActive()) : $latest->type !== 'dismissal';
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>  $assignment
     */
    private function record(Employee $employee, string $type, array $data, User $by, array $assignment): EmploymentEvent
    {
        $this->ensureNotBeforeLatest($employee, $data['effective_date']);

        return DB::transaction(function () use ($employee, $type, $data, $by, $assignment) {
            $event = $employee->events()->create(array_merge($assignment, [
                'type' => $type,
                'effective_date' => $data['effective_date'],
                'order_number' => $data['order_number'],
                'order_date' => $data['order_date'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $by->id,
            ]));

            $this->recompute($employee);

            return $event;
        });
    }

    private function ensureEmployed(Employee $employee): void
    {
        if (! $this->isEmployed($employee)) {
            throw ValidationException::withMessages(['type' => 'Xodim ishda emas']);
        }
    }

    private function ensureNotBeforeLatest(Employee $employee, string $effectiveDate): void
    {
        $latest = $employee->events()->effective()->get()->last()?->effective_date ?? $employee->hire_date;

        if ($latest !== null && Carbon::parse($effectiveDate)->lt($latest)) {
            throw ValidationException::withMessages([
                'effective_date' => "Sana oxirgi kadrlar buyrug'i sanasidan oldin bo'lishi mumkin emas",
            ]);
        }
    }

    private function ensureDepartmentBelongsTo(mixed $departmentId, int $organizationId): void
    {
        if ($departmentId !== null && ! Department::whereKey($departmentId)->where('organization_id', $organizationId)->exists()) {
            throw ValidationException::withMessages(['department_id' => "Bo'lim tanlangan tashkilotga tegishli emas"]);
        }
    }

    private function applyToDriver(Employee $employee, int $previousOrganization): void
    {
        $driver = $employee->driver;

        if ($driver === null) {
            return;
        }

        $changes = [];

        if ($employee->organization_id !== $previousOrganization && $driver->vehicle_id !== null
            && (int) Vehicle::whereKey($driver->vehicle_id)->value('organization_id') !== $employee->organization_id) {
            $changes['vehicle_id'] = null;
        }

        if (! $employee->isActive()) {
            $changes += ['status' => 'inactive', 'vehicle_id' => null];
        }

        if ($changes !== []) {
            $driver->update($changes);
        }

        $employee->syncDriverProfile();
    }
}
