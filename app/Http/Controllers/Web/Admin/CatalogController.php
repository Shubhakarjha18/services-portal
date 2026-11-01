<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\StoreServiceTypeRequest;
use App\Models\Application;
use App\Models\Department;
use App\Models\ServiceType;
use App\Support\Audit\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Departments and service catalogue. Editing a service only affects future
 * applications — submitted cases keep the snapshot they were filed with.
 */
class CatalogController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): Response
    {
        return Inertia::render('Admin/Catalog', [
            'departments' => Department::query()
                ->withCount(['serviceTypes' => fn ($q) => $q->where('is_public', true)])
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department) => [
                    'id' => $department->id,
                    'name' => $department->name,
                    'code' => $department->code,
                    'description' => $department->description,
                    'contact_email' => $department->contact_email,
                    'default_sla_days' => $department->default_sla_days,
                    'is_active' => $department->is_active,
                    'services_count' => (int) $department->service_types_count,
                ]),
            'services' => ServiceType::query()
                ->with('department:id,name,code')
                ->orderBy('name')
                ->get()
                ->map(fn (ServiceType $service) => [
                    'id' => $service->id,
                    'name' => $service->name,
                    'code' => $service->code,
                    'department_id' => $service->department_id,
                    'department' => $service->department?->name,
                    'description' => $service->description,
                    'fee' => (float) $service->fee,
                    'sla_days' => $service->sla_days,
                    'required_documents' => $service->requiredDocumentLabels(),
                    'is_public' => $service->is_public,
                    'requires_payment' => $service->requires_payment,
                    'applications' => Application::where('service_type_id', $service->id)->count(),
                ]),
        ]);
    }

    public function storeDepartment(StoreDepartmentRequest $request): RedirectResponse
    {
        $department = Department::create($request->validated());

        $this->audit->log(event: 'department_created', subject: $department, new: $department->only(['name', 'code']), actor: $request->user());

        return back()->with('success', "{$department->name} added to the directory.");
    }

    public function updateDepartment(StoreDepartmentRequest $request, Department $department): RedirectResponse
    {
        $before = $department->only(['name', 'code', 'default_sla_days', 'is_active']);
        $department->update($request->validated());

        $this->audit->log(
            event: 'department_updated',
            subject: $department,
            old: $before,
            new: $department->only(['name', 'code', 'default_sla_days', 'is_active']),
            actor: $request->user(),
        );

        return back()->with('success', 'Department updated.');
    }

    public function storeService(StoreServiceTypeRequest $request): RedirectResponse
    {
        $service = ServiceType::create([
            ...$request->validated(),
            'required_documents' => $request->requiredDocuments(),
        ]);

        $this->audit->log(event: 'service_created', subject: $service, new: ['code' => $service->code, 'name' => $service->name], actor: $request->user());

        return back()->with('success', "{$service->name} is now published.");
    }

    public function updateService(StoreServiceTypeRequest $request, ServiceType $serviceType): RedirectResponse
    {
        $before = $serviceType->only(['name', 'code', 'sla_days', 'fee', 'is_public']);

        $serviceType->update([
            ...$request->validated(),
            'required_documents' => $request->requiredDocuments(),
        ]);

        $this->audit->log(
            event: 'service_updated',
            subject: $serviceType,
            old: $before,
            new: $serviceType->only(['name', 'code', 'sla_days', 'fee', 'is_public']),
            actor: $request->user(),
        );

        return back()->with('success', "{$serviceType->name} updated.");
    }
}
