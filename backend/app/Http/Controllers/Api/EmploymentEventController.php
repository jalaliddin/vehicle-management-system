<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmploymentEvent;
use App\Services\Hr\EmploymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class EmploymentEventController extends Controller
{
    public function __construct(private EmploymentService $employment) {}

    public function index(Employee $employee): JsonResponse
    {
        Gate::authorize('view', $employee);

        $events = $employee->events()
            ->with(['organization:id,short_name', 'department:id,name', 'position:id,name', 'creator:id,name'])
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->get();

        return response()->json($events);
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize('update', $employee);

        $validated = $request->validate([
            'type' => ['required', 'in:hire,transfer,dismissal'],
            'effective_date' => ['required', 'date', 'before_or_equal:today'],
            'order_number' => ['required', 'string', 'max:50'],
            'order_date' => ['required', 'date', 'before_or_equal:today'],
            'organization_id' => ['nullable', 'exists:organizations,id'],
            'department_id' => ['nullable', 'integer'],
            'position_id' => [Rule::requiredIf($request->type === 'hire'), 'nullable', 'exists:positions,id'],
            'dismissal_reason' => [Rule::requiredIf($request->type === 'dismissal'), 'nullable', Rule::in(array_keys(config('hr.dismissal_reasons')))],
            'dismissal_article' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string'],
        ]);

        if (isset($validated['organization_id'])) {
            Gate::authorize('access-organization', [(int) $validated['organization_id']]);
        }

        $event = match ($validated['type']) {
            'hire' => $this->employment->hire($employee, $validated, $request->user()),
            'transfer' => $this->employment->transfer($employee, $validated, $request->user()),
            'dismissal' => $this->employment->dismiss($employee, $validated, $request->user()),
        };

        return response()->json($event->load(['organization:id,short_name', 'department:id,name', 'position:id,name']), 201);
    }

    public function cancel(Request $request, EmploymentEvent $event): JsonResponse
    {
        Gate::authorize('update', $event->employee);

        $validated = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        $this->employment->cancel($event, $validated['reason'], $request->user());

        return response()->json($event->fresh());
    }
}
