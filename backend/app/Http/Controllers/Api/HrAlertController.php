<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Hr\ComplianceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class HrAlertController extends Controller
{
    public function __construct(private ComplianceService $compliance) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('view-hr-alerts');

        $filters = $request->validate([
            'organization_id' => ['nullable', 'integer'],
            'category' => ['nullable', 'in:'.implode(',', ComplianceService::CATEGORIES)],
            'state' => ['nullable', 'in:overdue,due_soon'],
        ]);

        return response()->json($this->compliance->alerts($request->user(), $filters));
    }

    public function summary(Request $request): JsonResponse
    {
        Gate::authorize('view-hr-alerts');

        return response()->json($this->compliance->summary($request->user()));
    }
}
