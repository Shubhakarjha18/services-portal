<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Role-aware counters for the dashboards. The numbers themselves live in
 * DashboardService so this endpoint and the Inertia page cannot drift apart.
 */
class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function stats(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->dashboard->stats($request->user()),
            'meta' => [
                'statuses' => $this->dashboard->statuses(),
                'generated_at' => now()->toIso8601String(),
            ],
        ]);
    }

    /**
     * GET /api/v1/dashboard/workload — officer throughput for the admin view.
     */
    public function workload(): JsonResponse
    {
        return response()->json(['data' => $this->dashboard->workload()]);
    }
}
