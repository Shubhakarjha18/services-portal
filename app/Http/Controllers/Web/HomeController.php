<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\ServiceType;
use App\Services\DashboardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The public landing page: anonymous tracking plus the service catalogue.
 * Signed-in users are sent straight to their dashboard.
 */
class HomeController extends Controller
{
    public function __invoke(Request $request): RedirectResponse|Response
    {
        if ($request->user() !== null) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('Public/Home', [
            'services' => ServiceType::publicCatalog()
                ->with('department:id,name,code')
                ->orderBy('name')
                ->get()
                ->map(fn (ServiceType $service) => [
                    'name' => $service->name,
                    'code' => $service->code,
                    'department' => $service->department?->name,
                    'sla_days' => $service->sla_days,
                    'fee' => (float) $service->fee,
                ]),
            'statuses' => (new DashboardService)->statuses(),
            'pollAfter' => (int) config('portal.tracking_poll_seconds'),
        ]);
    }
}
