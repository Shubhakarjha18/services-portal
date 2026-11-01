<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\WorkflowException;
use App\Http\Controllers\Controller;
use App\Http\Requests\AssignOfficerRequest;
use App\Http\Requests\TransitionStatusRequest;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\TimelineResource;
use App\Models\Application;
use App\Models\User;
use App\Services\ApplicationWorkflow;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TransitionController extends Controller
{
    public function __construct(
        private readonly ApplicationWorkflow $workflow,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * POST /api/v1/applications/{application}/status
     *
     * @throws WorkflowException when the move breaks the declared workflow
     */
    public function store(TransitionStatusRequest $request, Application $application): JsonResponse
    {
        $transition = $this->workflow->transition(
            application: $application,
            toCode: $request->status(),
            note: $request->note(),
            actor: $request->user(),
            visibleToCitizen: ! $request->internalOnly(),
        );

        $application->loadMissing('status');

        return response()->json([
            'message' => "Status changed to {$transition->toStatus->name}.",
            'data' => new ApplicationResource($application->loadMissing(['serviceType', 'department', 'officer'])),
            'meta' => ['transition' => new TimelineResource($transition->loadMissing(['toStatus', 'fromStatus']))],
        ]);
    }

    /**
     * GET /api/v1/applications/{application}/status-options
     *
     * Lets the UI render only legal buttons instead of hiding them client side.
     */
    public function options(Request $request, Application $application): JsonResponse
    {
        Gate::authorize('view', $application);

        return response()->json([
            'data' => $this->workflow->availableTransitions($application, $request->user()),
            'meta' => ['current' => $application->code()?->value],
        ]);
    }

    /**
     * POST /api/v1/applications/{application}/assign
     */
    public function assign(AssignOfficerRequest $request, Application $application): JsonResponse
    {
        $officer = User::with('role', 'department')->findOrFail($request->officerId());

        if (! $officer->isStaff()) {
            return response()->json(['message' => 'Only staff accounts can hold cases.'], 422);
        }

        $before = ['assigned_officer_id' => $application->assigned_officer_id];

        $application->forceFill([
            'assigned_officer_id' => $officer->id,
            'department_id' => $application->department_id ?: $officer->department_id,
            'last_activity_at' => now(),
        ])->save();

        $this->audit->log(
            event: 'assigned',
            subject: $application,
            old: $before,
            new: ['assigned_officer_id' => $officer->id, 'assigned_officer_name' => $officer->name],
            meta: ['reason' => $request->input('reason')],
            actor: $request->user(),
        );

        return response()->json([
            'message' => "Case assigned to {$officer->name}.",
            'data' => new ApplicationResource($application->load(['status', 'serviceType', 'department', 'officer'])),
        ]);
    }
}
