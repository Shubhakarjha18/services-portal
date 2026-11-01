<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Models\ApplicationStatus;
use App\Models\ServiceType;
use App\Models\User;
use App\Services\ApplicationService;
use App\Services\ApplicationWorkflow;
use App\Support\Audit\AuditLogger;
use App\Support\Presenters\ApplicationPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ApplicationController extends Controller
{
    public function __construct(
        private readonly ApplicationService $applications,
        private readonly ApplicationWorkflow $workflow,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * GET /portal/applications — one list, three audiences.
     */
    public function index(Request $request): Response
    {
        $actor = $request->user();

        $query = Application::query()
            ->visibleTo($actor)
            ->with(['status', 'serviceType:id,name', 'department:id,name', 'officer:id,name'])
            ->withCount('documents');

        $filters = [
            'q' => (string) $request->input('q', ''),
            'status' => (string) $request->input('status', ''),
            'assigned' => (string) $request->input('assigned', ''),
            'overdue' => $request->boolean('overdue'),
            'sort' => (string) $request->input('sort', 'created_at'),
        ];

        $query
            ->when($filters['q'] !== '', function ($q) use ($filters) {
                $like = '%'.$filters['q'].'%';

                $q->where(fn ($inner) => $inner->where('reference_number', 'like', $like)
                    ->orWhere('title', 'like', $like)
                    ->orWhere('applicant_name', 'like', $like));
            })
            ->when($filters['status'] !== '', fn ($q) => $q->whereHas('status', fn ($s) => $s->where('code', $filters['status'])))
            ->when($filters['assigned'] === 'me', fn ($q) => $q->where('assigned_officer_id', $actor->id))
            ->when($filters['assigned'] === 'unassigned', fn ($q) => $q->whereNull('assigned_officer_id'))
            ->when($filters['overdue'], fn ($q) => $q->overdue());

        $allowed = ['created_at', 'due_at', 'stage', 'last_activity_at', 'reference_number'];
        $column = in_array($filters['sort'], $allowed, true) ? $filters['sort'] : 'created_at';

        $applications = $query->orderByDesc($column)->paginate(10)->withQueryString();

        return Inertia::render('Applications/Index', [
            'applications' => [
                'data' => collect($applications->items())->map(fn (Application $a) => ApplicationPresenter::card($a))->all(),
                'links' => [
                    'prev' => $applications->previousPageUrl(),
                    'next' => $applications->nextPageUrl(),
                ],
                'from' => $applications->currentPage(),
                'to' => $applications->lastPage(),
                'total' => $applications->total(),
            ],
            'filters' => $filters,
            'statuses' => ApplicationStatus::orderBy('sort_order')
                ->get(['code', 'name', 'color'])
                ->map(fn (ApplicationStatus $status) => [
                    'code' => $status->code->value,
                    'name' => $status->name,
                    'color' => $status->color,
                ]),
            'view' => $actor->hasRole(RoleName::Public) ? 'mine' : ($actor->hasRole(RoleName::Officer) ? 'queue' : 'all'),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Applications/Create', [
            'services' => ServiceType::publicCatalog()
                ->with('department:id,name,code')
                ->orderBy('name')
                ->get()
                ->map(fn (ServiceType $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'code' => $service->code,
                    'description' => $service->description,
                    'fee' => (float) $service->fee,
                    'sla_days' => $service->sla_days,
                    'required_documents' => $service->requiredDocumentLabels(),
                    'department' => $service->department?->name,
                ]),
            'prefill' => [
                'applicant_name' => $request->user()->name,
                'applicant_phone' => $request->user()->phone,
                'applicant_address' => null,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_type_id' => ['required', 'integer', 'exists:service_types,id'],
            'title' => ['required', 'string', 'min:8', 'max:200'],
            'description' => ['required', 'string', 'min:20', 'max:4000'],
            'applicant_name' => ['required', 'string', 'min:3', 'max:160'],
            'applicant_phone' => ['required', 'string', 'regex:/^\+?[0-9 ()-]{7,20}$/'],
            'applicant_address' => ['required', 'string', 'min:10', 'max:500'],
        ]);

        $application = $this->applications->create($request->user(), $validated);

        return redirect()
            ->route('applications.show', $application)
            ->with('success', "Application {$application->reference_number} submitted. Save the tracking number.");
    }

    public function show(Request $request, Application $application): Response
    {
        Gate::authorize('view', $application);

        $application->load([
            'status', 'serviceType.department', 'department', 'citizen', 'officer',
            'documents.uploader', 'comments.author',
        ]);

        $isStaff = $request->user()->isStaff();

        $transitions = $application->transitions()
            ->with(['toStatus', 'fromStatus'])
            ->when(! $isStaff, fn ($q) => $q->where('visible_to_citizen', true))
            ->orderBy('id')
            ->get();

        $application->setAttribute(
            'available_transitions',
            $this->workflow->availableTransitions($application, $request->user())
        );

        return Inertia::render('Applications/Show', [
            'application' => ApplicationPresenter::detail($application, $request->user(), $transitions->all()),
            'officers' => $isStaff
                ? User::query()
                    ->roleNamed(RoleName::Officer)
                    ->where('is_active', true)
                    ->when(
                        $request->user()->hasRole(RoleName::Officer),
                        fn ($q) => $q->where('department_id', $request->user()->department_id)
                    )
                    ->orderBy('name')
                    ->get(['id', 'name', 'department_id'])
                    ->map(fn (User $officer) => [
                        'id' => $officer->id,
                        'name' => $officer->name,
                        'open' => $officer->assignedCases()->whereHas('status', fn ($s) => $s->where('counts_as_open', true))->count(),
                    ])
                : [],
            'pollAfter' => (int) config('portal.tracking_poll_seconds'),
        ]);
    }

    /**
     * POST /portal/applications/{application}/status
     */
    public function transition(Request $request, Application $application): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'max:48'],
            'note' => ['nullable', 'string', 'min:5', 'max:500'],
            'internal_only' => ['nullable', 'boolean'],
        ]);

        $this->workflow->transition(
            application: $application,
            toCode: $data['status'],
            note: $data['note'] ?? null,
            actor: $request->user(),
            visibleToCitizen: ! (bool) ($data['internal_only'] ?? false),
        );

        return back()->with('success', 'The status was updated and written to the audit trail.');
    }

    public function assign(Request $request, Application $application): RedirectResponse
    {
        Gate::authorize('assign', Application::class);

        $data = $request->validate([
            'officer_id' => ['required', 'integer', 'exists:users,id'],
        ]);

        $officer = User::findOrFail($data['officer_id']);

        $application->forceFill([
            'assigned_officer_id' => $officer->id,
            'last_activity_at' => now(),
        ])->save();

        $this->audit->log(
            event: 'assigned',
            subject: $application,
            new: ['assigned_officer_id' => $officer->id, 'officer' => $officer->name],
            actor: $request->user(),
        );

        return back()->with('success', "Case handed to {$officer->name}.");
    }

    public function comment(Request $request, Application $application): RedirectResponse
    {
        Gate::authorize('comment', $application);

        $data = $request->validate([
            'body' => ['required', 'string', 'min:3', 'max:2000'],
            'is_internal' => ['sometimes', 'boolean'],
        ]);

        $application->comments()->create([
            'user_id' => $request->user()->id,
            'body' => trim($data['body']),
            'is_internal' => (bool) ($data['is_internal'] ?? false) && $request->user()->isStaff(),
        ]);

        $application->forceFill(['last_activity_at' => now()])->save();

        return back()->with('success', 'Message added.');
    }

    /**
     * POST /portal/applications/{application}/documents
     */
    public function upload(Request $request, Application $application): RedirectResponse
    {
        Gate::authorize('uploadDocument', $application);

        $request->validate([
            'label' => ['required', 'string', 'min:3', 'max:160'],
            'file' => [
                'required',
                'file',
                'max:'.config('portal.documents.max_kilobytes'),
                'mimes:'.implode(',', config('portal.documents.allowed_mimes', [])),
            ],
        ]);

        $document = $this->applications->attachDocument(
            application: $application,
            uploader: $request->user(),
            file: $request->file('file'),
            label: $request->input('label'),
        );

        return back()->with('success', "{$document->label} uploaded for verification.");
    }

    public function reviewDocument(Request $request, ApplicationDocument $document): RedirectResponse
    {
        Gate::authorize('verifyDocument', $document);

        $data = $request->validate([
            'status' => ['required', 'in:accepted,rejected'],
            'review_note' => ['nullable', 'string', 'min:5', 'max:500', 'required_if:status,rejected'],
        ]);

        $this->applications->reviewDocument($document, $data, $request->user());

        return back()->with('success', 'Document marked as '.$data['status'].'.');
    }

    public function download(Request $request, ApplicationDocument $document)
    {
        Gate::authorize('download', $document);

        $this->audit->log(
            event: 'document_downloaded',
            subject: $document,
            meta: ['checksum' => $document->checksum],
            actor: $request->user(),
        );

        $disk = Storage::disk($document->disk);

        abort_unless($disk->exists($document->path), 404, 'The stored file is missing.');

        return $disk->download($document->path, $document->original_name);
    }

    /**
     * DELETE /portal/applications/{application} — the citizen withdraws the case.
     */
    public function destroy(Request $request, Application $application): RedirectResponse
    {
        Gate::authorize('delete', $application);

        $reference = $application->reference_number;

        $application->delete();

        $this->audit->log(
            event: 'application_withdrawn',
            subject: $application,
            old: ['reference_number' => $reference, 'status' => $application->code()?->value],
            actor: $request->user(),
        );

        return redirect()->route('applications.index')
            ->with('success', "Application {$reference} was withdrawn.");
    }
}
