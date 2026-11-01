<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\Application;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Read-only compliance view. Admins see everything; officers only see entries
 * that concern the cases they hold — in the list, the detail and the export.
 */
class AuditLogController extends Controller
{
    private const MAX_PER_PAGE = 200;

    /** Rows a single export may return, so a report cannot stall the app. */
    private const EXPORT_LIMIT = 5000;

    public function index(Request $request): AnonymousResourceCollection
    {
        $logs = $this->filtered($request)
            ->latest('id')
            ->paginate(min(max((int) $request->input('per_page', 50), 1), self::MAX_PER_PAGE))
            ->withQueryString();

        return AuditLogResource::collection($logs);
    }

    public function show(Request $request, AuditLog $auditLog): JsonResponse
    {
        abort_unless(
            $this->visible($request, $auditLog),
            403,
            'This audit entry belongs to another account.'
        );

        return response()->json(['data' => new AuditLogResource($auditLog->load('user:id,name'))]);
    }

    /** Export for internal audit requests; same filters and scope as the list. */
    public function export(Request $request): JsonResponse
    {
        $rows = $this->filtered($request)->latest('id')->limit(self::EXPORT_LIMIT)->get();

        return response()->json([
            'data' => AuditLogResource::collection($rows),
            'meta' => ['exported' => $rows->count(), 'truncated_at' => self::EXPORT_LIMIT],
        ]);
    }

    private function filtered(Request $request): Builder
    {
        $query = AuditLog::query()->with('user:id,name');

        $this->restrict($query, $request);

        return $query
            ->when($request->filled('event'), fn (Builder $q) => $q->where('event', (string) $request->input('event')))
            ->when($request->filled('user_id'), fn (Builder $q) => $q->where('user_id', (int) $request->input('user_id')))
            ->when($request->filled('from'), fn (Builder $q) => $q->whereDate('created_at', '>=', (string) $request->input('from')))
            ->when($request->filled('to'), fn (Builder $q) => $q->whereDate('created_at', '<=', (string) $request->input('to')))
            ->search($request->input('q'));
    }

    private function restrict(Builder $query, Request $request): void
    {
        if ($request->user()->hasRole(RoleName::Admin)) {
            return;
        }

        $references = Application::query()
            ->visibleTo($request->user())
            ->pluck('reference_number');

        $query->where(function (Builder $q) use ($request, $references): void {
            $q->where('user_id', $request->user()->id)
                ->orWhereIn('reference_number', $references);
        });
    }

    private function visible(Request $request, AuditLog $auditLog): bool
    {
        if ($request->user()->hasRole(RoleName::Admin)) {
            return true;
        }

        if ($auditLog->user_id === $request->user()->id) {
            return true;
        }

        return $auditLog->reference_number !== null
            && Application::query()->visibleTo($request->user())->where('reference_number', $auditLog->reference_number)->exists();
    }
}
