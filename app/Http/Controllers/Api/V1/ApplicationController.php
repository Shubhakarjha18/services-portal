<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ApplicationStatusCode;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicationRequest;
use App\Http\Requests\UpdateApplicationRequest;
use App\Http\Resources\ApplicationResource;
use App\Http\Resources\CommentResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\TimelineResource;
use App\Models\Application;
use App\Services\ApplicationService;
use App\Services\ApplicationWorkflow;
use App\Support\Audit\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applications,
        private readonly ApplicationWorkflow $workflow,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * GET /api/v1/applications
     *
     * Row visibility comes from scopeVisibleTo(); every filter below is additive
     * and can never widen access.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = Application::query()
            ->visibleTo($request->user())
            ->with(['status', 'serviceType:id,name,code', 'department:id,name,code', 'officer:id,name,email'])
            ->withCount(['documents', 'comments', 'transitions']);

        $this->applyFilters($query, $request);

        $applications = $query
            ->orderBy(...$this->sortFrom($request))
            ->paginate(min(max((int) $request->input('per_page', 15), 1), 100))
            ->withQueryString();

        return ApplicationResource::collection($applications);
    }

    /**
     * POST /api/v1/applications
     */
    public function store(StoreApplicationRequest $request): JsonResponse
    {
        $application = $this->applications->create($request->user(), [
            ...$request->validated(),
            'priority' => $request->validatedPriority(),
        ]);

        return (new ApplicationResource($application))
            ->additional(['message' => 'Application submitted. Keep your reference number to track progress.'])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * GET /api/v1/applications/{application}
     */
    public function show(Request $request, Application $application): JsonResponse
    {
        Gate::authorize('view', $application);

        $application->load([
            'status',
            'serviceType.department',
            'department',
            'citizen:id,name,email',
            'officer:id,name,email',
            'documents.uploader:id,name',
            'comments.author:id,name,role_id',
            'transitions.toStatus',
            'transitions.fromStatus',
            'transitions.actor:id,name',
        ]);

        $isStaff = $request->user()->isStaff();

        $visibleComments = $application->comments->filter(
            fn ($comment) => ! $comment->is_internal || $isStaff
        );

        $timeline = $application->transitions
            ->filter(fn ($transition) => $transition->visible_to_citizen || $isStaff)
            ->sortBy('id');

        $application->setAttribute(
            'available_transitions',
            $this->workflow->availableTransitions($application, $request->user())
        );

        $application->increment('view_count');

        return response()->json([
            'data' => new ApplicationResource($application),
            'links' => [
                'self' => route('api.applications.show', $application),
                'timeline' => route('api.applications.timeline', $application),
            ],
            'meta' => [
                'documents' => DocumentResource::collection($application->documents),
                'comments' => CommentResource::collection($visibleComments),
                'timeline' => TimelineResource::collection($timeline),
            ],
        ]);
    }

    /**
     * PUT / PATCH /api/v1/applications/{application}
     */
    public function update(UpdateApplicationRequest $request, Application $application): ApplicationResource
    {
        $application->fill($request->validated())->save();

        $this->audit->log(
            event: 'application_updated',
            subject: $application,
            new: $request->validated(),
            actor: $request->user(),
        );

        return new ApplicationResource(
            $application->fresh()->load(['status', 'serviceType', 'department'])
        );
    }

    /**
     * DELETE /api/v1/applications/{application}
     *
     * Soft delete: the row and its audit trail stay queryable for compliance.
     */
    public function destroy(Request $request, Application $application): JsonResponse
    {
        Gate::authorize('delete', $application);

        $snapshot = [
            'reference_number' => $application->reference_number,
            'status' => $application->code()?->value,
        ];

        $application->delete();

        $this->audit->log(
            event: 'application_withdrawn',
            subject: $application,
            old: $snapshot,
            actor: $request->user(),
        );

        return response()->json(['message' => "Application {$snapshot['reference_number']} was withdrawn."]);
    }

    /**
     * GET /api/v1/applications/{application}/timeline
     *
     * Cheap polling endpoint for the live tracker: pass `since_id` to fetch only
     * entries newer than the one the client already rendered.
     */
    public function timeline(Request $request, Application $application): JsonResponse
    {
        Gate::authorize('view', $application);

        $sinceId = (int) $request->input('since_id', 0);

        $transitions = $application->transitions()
            ->with(['toStatus', 'fromStatus'])
            ->when(! $request->user()->isStaff(), fn (Builder $q) => $q->where('visible_to_citizen', true))
            ->when($sinceId > 0, fn (Builder $q) => $q->where('id', '>', $sinceId))
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => TimelineResource::collection($transitions),
            'meta' => [
                'status' => $application->code()?->value,
                'stage' => $application->stage,
                'progress' => $application->progressPercentage(),
                'is_overdue' => $application->isOverdue(),
                'last_activity_at' => $application->last_activity_at?->toIso8601String(),
                'poll_after_seconds' => (int) config('portal.tracking_poll_seconds'),
            ],
        ]);
    }

    private function applyFilters(Builder $query, Request $request): void
    {
        $actor = $request->user();

        $query
            ->when($request->filled('status'), function (Builder $q) use ($request): void {
                $codes = array_values(array_filter(
                    (array) $request->input('status'),
                    fn ($code) => ApplicationStatusCode::tryFrom((string) $code) !== null
                ));

                if ($codes !== []) {
                    $q->whereHas('status', fn (Builder $s) => $s->whereIn('code', $codes));
                }
            })
            ->when($request->boolean('open'), fn (Builder $q) => $q->open())
            ->when($request->boolean('overdue'), fn (Builder $q) => $q->overdue())
            ->when($request->filled('department_id'), fn (Builder $q) => $q->where('department_id', (int) $request->input('department_id')))
            ->when($request->filled('service_type_id'), fn (Builder $q) => $q->where('service_type_id', (int) $request->input('service_type_id')))
            ->when($request->filled('priority'), fn (Builder $q) => $q->where('priority', (string) $request->input('priority')))
            ->when($request->filled('assigned'), function (Builder $q) use ($request, $actor): void {
                $value = (string) $request->input('assigned');

                if ($value === 'me') {
                    $q->where('assigned_officer_id', $actor->id);

                    return;
                }

                if ($value === 'unassigned') {
                    $q->whereNull('assigned_officer_id');

                    return;
                }

                $q->where('assigned_officer_id', (int) $value);
            })
            ->when($request->filled('q'), function (Builder $q) use ($request): void {
                $like = '%'.trim((string) $request->input('q')).'%';

                $q->where(function (Builder $inner) use ($like): void {
                    $inner->where('reference_number', 'like', $like)
                        ->orWhere('title', 'like', $like)
                        ->orWhere('applicant_name', 'like', $like);
                });
            })
            ->when($request->filled('submitted_from'), fn (Builder $q) => $q->whereDate('submitted_at', '>=', (string) $request->input('submitted_from')))
            ->when($request->filled('submitted_to'), fn (Builder $q) => $q->whereDate('submitted_at', '<=', (string) $request->input('submitted_to')));
    }

    /** @return array<int, mixed> */
    private function sortFrom(Request $request): array
    {
        $allowed = ['created_at', 'due_at', 'priority', 'stage', 'last_activity_at', 'submitted_at', 'reference_number'];
        $column = in_array((string) $request->input('sort'), $allowed, true) ? (string) $request->input('sort') : 'created_at';

        return [$column, $request->input('direction') === 'asc' ? 'asc' : 'desc'];
    }
}
