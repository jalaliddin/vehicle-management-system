<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\PositionPpeNorm;
use App\Models\PpeIssue;
use App\Models\PpeItem;
use App\Support\Hr\DueDates;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EmployeePpeController extends Controller
{
    /**
     * The employee's PPE issues and how they cover the position's norms.
     */
    public function show(Employee $employee): JsonResponse
    {
        Gate::authorize('viewFor', [PpeIssue::class, $employee]);

        $issues = $employee->ppeIssues()->with('ppeItem')->orderByDesc('issued_on')->orderByDesc('id')->get();
        $inUse = $issues->where('status', 'in_use')->keyBy('ppe_item_id');

        $norms = $employee->position?->ppeNorms()->with('ppeItem')->get() ?? collect();

        return response()->json([
            'issues' => $issues,
            'norms' => $norms->map(fn (PositionPpeNorm $norm) => [
                'ppe_item' => $norm->ppeItem,
                'quantity' => $norm->quantity,
                'wear_months' => $norm->wear_months,
                'current_issue' => $inUse->get($norm->ppe_item_id),
            ])->values(),
        ]);
    }

    public function store(Request $request, Employee $employee): JsonResponse
    {
        Gate::authorize('createFor', [PpeIssue::class, $employee]);

        $validated = $request->validate([
            'ppe_item_id' => ['required', 'exists:ppe_items,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:1000'],
            'size' => ['nullable', 'string', 'max:10'],
            'issued_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string'],
        ]);

        $item = PpeItem::findOrFail($validated['ppe_item_id']);
        $wearMonths = $employee->position?->ppeNorms()->where('ppe_item_id', $item->id)->value('wear_months') ?? $item->default_wear_months;

        $issue = DB::transaction(function () use ($employee, $validated, $item, $wearMonths, $request) {
            $employee->ppeIssues()->where('ppe_item_id', $item->id)->where('status', 'in_use')->get()
                ->each->update(['status' => 'replaced', 'closed_on' => $validated['issued_on']]);

            return $employee->ppeIssues()->create(array_merge($validated, [
                'due_on' => DueDates::ppeDue(Carbon::parse($validated['issued_on']), $wearMonths)?->toDateString(),
                'status' => 'in_use',
                'created_by' => $request->user()->id,
            ]));
        });

        return response()->json($issue->load('ppeItem'), 201);
    }

    public function close(Request $request, PpeIssue $issue): JsonResponse
    {
        Gate::authorize('close', $issue);

        $validated = $request->validate([
            'status' => ['required', 'in:returned,written_off'],
            'closed_on' => ['required', 'date', 'before_or_equal:today', 'after_or_equal:'.$issue->issued_on->toDateString()],
        ]);

        if ($issue->status !== 'in_use') {
            return response()->json(['message' => 'Bu buyum allaqachon yopilgan'], 422);
        }

        $issue->update($validated);

        return response()->json($issue->load('ppeItem'));
    }
}
