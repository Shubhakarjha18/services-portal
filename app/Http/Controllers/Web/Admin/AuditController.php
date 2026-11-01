<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web\Admin;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The compliance screen. Admins see the whole trail; officers see only entries
 * tied to themselves or to cases in their department.
 */
class AuditController extends Controller
{
    public function index(Request $request): Response
    {
        $actor = $request->user();

        $filters = [
            'q' => (string) $request->input('q', ''),
            'event' => (string) $request->input('event', ''),
            'user_id' => (string) $request->input('user_id', ''),
            'from' => (string) $request->input('from', ''),
            'to' => (string) $request->input('to', ''),
        ];

        $logs = AuditLog::query()
            ->when(! $actor->hasRole(RoleName::Admin), fn (Builder $q) => $q->where('user_id', $actor->id))
            ->when($filters['q'] !== '', fn (Builder $q) => $q->search($filters['q']))
            ->when($filters['event'] !== '', fn (Builder $q) => $q->where('event', $filters['event']))
            ->when($filters['user_id'] !== '', fn (Builder $q) => $q->where('user_id', (int) $filters['user_id']))
            ->when($filters['from'] !== '', fn (Builder $q) => $q->whereDate('created_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $q) => $q->whereDate('created_at', '<=', $filters['to']))
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return Inertia::render('Admin/Audit', [
            'logs' => [
                'data' => $logs->getCollection()->map(fn (AuditLog $log) => [
                    'id' => $log->id,
                    'event' => $log->event,
                    'actor' => $log->user_name ?? 'Anonymous',
                    'role' => $log->role_name,
                    'reference' => $log->reference_number,
                    'subject' => class_basename((string) $log->auditable_type),
                    'subject_id' => $log->auditable_id,
                    'ip' => $log->ip_address,
                    'at' => $log->created_at?->toDateTimeString(),
                    'human' => $log->created_at?->diffForHumans(),
                ])->all(),
                'links' => ['prev' => $logs->previousPageUrl(), 'next' => $logs->nextPageUrl()],
                'total' => $logs->total(),
                'page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
            ],
            'detail' => $request->filled('log')
                ? optional(AuditLog::find((int) $request->input('log')))->toArray()
                : null,
            'filters' => $filters,
            'events' => AuditLog::query()->distinct()->orderBy('event')->pluck('event'),
            'actors' => $actor->hasRole(RoleName::Admin)
                ? User::orderBy('name')->get(['id', 'name'])->map(fn (User $user) => ['id' => $user->id, 'name' => $user->name])
                : [],
        ]);
    }
}
