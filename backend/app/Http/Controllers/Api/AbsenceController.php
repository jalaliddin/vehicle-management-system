<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeAbsence;
use App\Services\Hr\LeaveBalance;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AbsenceController extends Controller
{
    public function index(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize('view', $employee);

        $absences = $employee->absences()
            ->when($request->year, fn ($q) => $q->whereYear('start_date', '<=', $request->year)->whereYear('end_date', '>=', $request->year))
            ->orderByDesc('start_date')
            ->get();

        return response()->json($absences);
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize('update', $employee);

        $validated = $this->validated($request);
        $this->ensureAllowed($employee, $validated);

        $absence = $employee->absences()->create(array_merge($validated, ['created_by' => $request->user()->id]));

        return response()->json($absence, 201);
    }

    public function update(Request $request, EmployeeAbsence $absence): JsonResponse
    {
        Gate::authorize('update', $absence->employee);

        $validated = $this->validated($request);
        $this->ensureAllowed($absence->employee, $validated, $absence);

        $absence->update($validated);

        return response()->json($absence);
    }

    public function destroy(EmployeeAbsence $absence): JsonResponse
    {
        Gate::authorize('update', $absence->employee);

        $absence->delete();

        return response()->json(null, 204);
    }

    public function leaveBalance(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize('view', $employee);

        return response()->json(LeaveBalance::forYear($employee, (int) ($request->year ?? now()->year)));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $isPlanned = $request->boolean('is_planned');

        return $request->validate([
            'type' => ['required', 'in:annual_leave,unpaid_leave,study_leave,maternity_leave,sick_leave,business_trip,absenteeism,other'],
            'start_date' => array_merge(['required', 'date'], $isPlanned ? [] : ['before_or_equal:today']),
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'is_planned' => ['boolean'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'document_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * The employee must be employed on the start date, and records of the
     * same kind (planned or actual) must not overlap.
     *
     * @param  array<string, mixed>  $data
     */
    private function ensureAllowed(Employee $employee, array $data, ?EmployeeAbsence $ignore = null): void
    {
        $start = $data['start_date'];

        if (($employee->hire_date !== null && $employee->hire_date->gt($start))
            || ($employee->dismissal_date !== null && $employee->dismissal_date->lt($start))) {
            throw ValidationException::withMessages(['start_date' => 'Bu sanada xodim ishda emas']);
        }

        $overlaps = $employee->absences()
            ->where('is_planned', (bool) ($data['is_planned'] ?? false))
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $start)
            ->exists();

        if ($overlaps) {
            throw ValidationException::withMessages(['start_date' => 'Bu davrda boshqa yozuv mavjud']);
        }
    }
}
