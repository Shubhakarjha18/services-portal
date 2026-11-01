<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Enums\RoleName;
use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Services\DashboardService;
use App\Support\Presenters\ApplicationPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $dashboard) {}

    public function index(Request $request): Response
    {
        $actor = $request->user();

        $attention = Application::query()
            ->visibleTo($actor)
            ->open()
            ->with(['status', 'serviceType:id,name'])
            ->when(
                $actor->hasRole(RoleName::Officer),
                fn ($q) => $q->where('assigned_officer_id', $actor->id)
            )
            ->orderBy('due_at')
            ->limit(6)
            ->get();

        return Inertia::render('Dashboard', [
            'stats' => $this->dashboard->stats($actor),
            'statuses' => $this->dashboard->statuses(),
            'workload' => $actor->hasRole(RoleName::Admin) ? $this->dashboard->workload() : [],
            'attention' => $attention->map(fn (Application $application) => ApplicationPresenter::card($application))->values(),
            'view' => match (true) {
                $actor->hasRole(RoleName::Admin) => 'all',
                $actor->hasRole(RoleName::Officer) => 'queue',
                default => 'mine',
            },
        ]);
    }
}
