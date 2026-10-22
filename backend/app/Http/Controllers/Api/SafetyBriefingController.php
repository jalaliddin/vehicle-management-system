<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\SafetyBriefing;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class SafetyBriefingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', SafetyBriefing::class);

        $user = $request->user();

        $briefings = SafetyBriefing::with(['organization:id,short_name', 'instructor:id,last_name,first_name,middle_name'])
            ->withCount('participants')
            ->when(! $user->canAccessAllOrganizations(), fn ($q) => $q->where('organization_id', $user->organization_id))
            ->when($request->organization_id, fn ($q) => $q->where('organization_id', $request->organization_id))
            ->when($request->type, fn ($q) => $q->where('type', $request->type))
            ->when($request->from, fn ($q) => $q->whereDate('conducted_on', '>=', $request->from))
            ->when($request->to, fn ($q) => $q->whereDate('conducted_on', '<=', $request->to))
            ->orderByDesc('conducted_on')
            ->orderByDesc('id')
            ->paginate(50);

        return response()->json($briefings);
    }

    public function store(Request $request): JsonResponse
    {
        Gate::authorize('create', SafetyBriefing::class);

        $validated = $this->validated($request);
        Gate::authorize('access-organization', [(int) $validated['organization_id']]);

        $briefing = DB::transaction(function () use ($validated, $request) {
            $briefing = SafetyBriefing::create(array_merge(
                array_diff_key($validated, ['participant_ids' => true]),
                ['created_by' => $request->user()->id],
            ));
            $briefing->syncParticipants($validated['participant_ids']);

            return $briefing;
        });

        return response()->json($this->withParticipants($briefing), 201);
    }

    public function show(SafetyBriefing $safetyBriefing): JsonResponse
    {
        Gate::authorize('view', $safetyBriefing);

        return response()->json($this->withParticipants($safetyBriefing));
    }

    public function update(Request $request, SafetyBriefing $safetyBriefing): JsonResponse
    {
        Gate::authorize('update', $safetyBriefing);

        $validated = $this->validated($request, $safetyBriefing->organization_id);

        DB::transaction(function () use ($safetyBriefing, $validated) {
            $safetyBriefing->update(array_diff_key($validated, ['participant_ids' => true, 'organization_id' => true]));
            $safetyBriefing->syncParticipants($validated['participant_ids']);
        });

        return response()->json($this->withParticipants($safetyBriefing));
    }

    public function destroy(SafetyBriefing $safetyBriefing): JsonResponse
    {
        Gate::authorize('delete', $safetyBriefing);

        $safetyBriefing->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?int $organizationId = null): array
    {
        $validated = $request->validate([
            'organization_id' => [$organizationId ? 'nullable' : 'required', 'exists:organizations,id'],
            'type' => ['required', 'in:introductory,primary,repeated,unscheduled,targeted'],
            'conducted_on' => ['required', 'date', 'before_or_equal:today'],
            'topic' => ['required', 'string', 'max:255'],
            'instructor_employee_id' => ['nullable', 'exists:employees,id'],
            'instructor_name' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
            'participant_ids' => ['required', 'array', 'min:1'],
            'participant_ids.*' => ['integer', 'distinct'],
        ]);

        $organizationId ??= (int) $validated['organization_id'];

        $eligible = Employee::whereKey($validated['participant_ids'])
            ->where('organization_id', $organizationId)
            ->where('status', 'active')
            ->count();

        if ($eligible !== count($validated['participant_ids'])) {
            throw ValidationException::withMessages([
                'participant_ids' => "Qatnashchilar shu tashkilotning ishlayotgan xodimlari bo'lishi kerak",
            ]);
        }

        return $validated;
    }

    /**
     * @return array<string, mixed>
     */
    private function withParticipants(SafetyBriefing $briefing): array
    {
        $briefing->load(['organization:id,short_name', 'instructor:id,last_name,first_name,middle_name']);

        return array_merge($briefing->toArray(), [
            'participants' => $briefing->participants()->orderBy('last_name')->get()->map(fn (Employee $employee) => [
                'id' => $employee->id,
                'full_name' => $employee->full_name,
                'position_id' => $employee->position_id,
                'next_due_date' => $employee->pivot->next_due_date,
            ])->all(),
        ]);
    }
}
