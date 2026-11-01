<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\RoleName;
use App\Models\Application;
use App\Models\ApplicationStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Role-aware counters shared by the JSON dashboard endpoint and the Inertia
 * page so both surfaces always report the same numbers.
 */
class DashboardService
{
    /** @return array<string, mixed> */
    public function stats(User $actor): array
    {
        $base = Application::query()->visibleTo($actor);

        return [
            'totals' => [
                'all' => (clone $base)->count(),
                'open' => (clone $base)->open()->count(),
                'overdue' => (clone $base)->overdue()->count(),
                'closed' => (clone $base)->where(fn ($q) => $q->whereNotNull('closed_at'))->count(),
                'due_soon' => (clone $base)->open()
                    ->whereBetween('due_at', [now(), now()->addDays(3)])
                    ->count(),
            ],
            'by_status' => (clone $base)
                ->select('application_status_id', DB::raw('count(*) as aggregate'))
                ->with('status:id,code,name,color')
                ->groupBy('application_status_id')
                ->get()
                ->map(fn (Application $application) => [
                    'code' => $application->status?->code->value,
                    'name' => $application->status?->name,
                    'color' => $application->status?->color,
                    'count' => (int) $application->aggregate,
                ])
                ->values(),
            'by_department' => $actor->hasRole(RoleName::Public)
                ? []
                : (clone $base)
                    ->select('department_id', DB::raw('count(*) as aggregate'))
                    ->with('department:id,name,code')
                    ->groupBy('department_id')
                    ->get()
                    ->map(fn (Application $application) => [
                        'department' => $application->department?->name,
                        'count' => (int) $application->aggregate,
                    ])
                    ->values()
                    ->all(),
            'submissions_last_30_days' => (clone $base)
                ->select(DB::raw('date(submitted_at) as day'), DB::raw('count(*) as aggregate'))
                ->whereNotNull('submitted_at')
                ->where('submitted_at', '>=', now()->subDays(29)->startOfDay())
                ->groupBy('day')
                ->orderBy('day')
                ->pluck('aggregate', 'day'),
            'unassigned' => $actor->hasRole(RoleName::Public)
                ? null
                : (clone $base)->whereNull('assigned_officer_id')->open()->count(),
            'my_queue' => $actor->hasRole(RoleName::Officer)
                ? (clone $base)->where('assigned_officer_id', $actor->id)->open()->count()
                : null,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function workload(): array
    {
        return User::query()
            ->whereHas('role', fn ($q) => $q->where('name', RoleName::Officer->value))
            ->where('is_active', true)
            ->select('users.id', 'users.name')
            ->withCount([
                'assignedCases as open_cases' => fn ($q) => $q->whereHas('status', fn ($s) => $s->where('counts_as_open', true)),
                'assignedCases as overdue_cases' => fn ($q) => $q->where('due_at', '<', now())->whereHas('status', fn ($s) => $s->where('counts_as_open', true)),
                'assignedCases as closed_cases' => fn ($q) => $q->whereNotNull('closed_at'),
            ])
            ->orderByDesc('open_cases')
            ->get()
            ->map(fn (User $officer) => [
                'id' => $officer->id,
                'name' => $officer->name,
                'open_cases' => (int) $officer->open_cases,
                'overdue_cases' => (int) $officer->overdue_cases,
                'closed_cases' => (int) $officer->closed_cases,
            ])
            ->all();
    }

    /** @return array<int, array<string, mixed>> */
    public function statuses(): array
    {
        return ApplicationStatus::orderBy('sort_order')
            ->get(['code', 'name', 'color'])
            ->map(fn (ApplicationStatus $status) => [
                'code' => $status->code->value,
                'name' => $status->name,
                'color' => $status->color,
            ])
            ->values()
            ->all();
    }
}
