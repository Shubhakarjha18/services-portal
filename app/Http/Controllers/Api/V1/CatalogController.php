<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDepartmentRequest;
use App\Http\Requests\StoreServiceTypeRequest;
use App\Http\Resources\LookupResource;
use App\Models\ApplicationStatus;
use App\Models\Department;
use App\Models\ServiceType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Reference data endpoints. Reads are open to any authenticated user (the
 * citizen needs the service catalog before creating a case); writes are admin
 * only, guarded by the role middleware plus the request authorize() methods.
 */
class CatalogController extends Controller
{
    public function services(Request $request): AnonymousResourceCollection
    {
        $services = ServiceType::with('department:id,name,code')
            ->when(! $request->user()->isStaff(), fn ($q) => $q->publicCatalog())
            ->when($request->filled('department_id'), fn ($q) => $q->where('department_id', (int) $request->input('department_id')))
            ->orderBy('name')
            ->get();

        return LookupResource::collection($services);
    }

    public function departments(Request $request): AnonymousResourceCollection
    {
        return LookupResource::collection(
            Department::query()
                ->when(! $request->user()->isStaff(), fn ($q) => $q->where('is_active', true))
                ->orderBy('name')
                ->get()
        );
    }

    public function statuses(): AnonymousResourceCollection
    {
        return LookupResource::collection(
            ApplicationStatus::orderBy('sort_order')->get()
        );
    }

    public function storeDepartment(StoreDepartmentRequest $request): JsonResponse
    {
        $department = Department::create($request->validated());

        return (new LookupResource($department))
            ->additional(['message' => 'Department created.'])
            ->response()
            ->setStatusCode(201);
    }

    public function updateDepartment(StoreDepartmentRequest $request, Department $department): LookupResource
    {
        $department->update($request->validated());

        return new LookupResource($department);
    }

    public function storeService(StoreServiceTypeRequest $request): JsonResponse
    {
        $service = ServiceType::create([
            ...$request->validated(),
            'required_documents' => $request->requiredDocuments(),
        ]);

        return (new LookupResource($service->load('department:id,name,code')))
            ->additional(['message' => 'Service published to the catalog.'])
            ->response()
            ->setStatusCode(201);
    }

    public function updateService(StoreServiceTypeRequest $request, ServiceType $serviceType): LookupResource
    {
        $serviceType->update([
            ...$request->validated(),
            'required_documents' => $request->has('required_documents') ? $request->requiredDocuments() : $serviceType->required_documents,
        ]);

        return new LookupResource($serviceType->load('department:id,name,code'));
    }
}
