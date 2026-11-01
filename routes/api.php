<?php

declare(strict_types=1);

use App\Enums\ApplicationStatusCode;
use App\Http\Controllers\Api\V1\ApplicationController;
use App\Http\Controllers\Api\V1\AuditLogController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CatalogController;
use App\Http\Controllers\Api\V1\CommentController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\DocumentController;
use App\Http\Controllers\Api\V1\PublicTrackingController;
use App\Http\Controllers\Api\V1\TransitionController;
use App\Http\Controllers\Api\V1\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Services Portal — REST API v1
|--------------------------------------------------------------------------
|
| Mounted at /api/v1. Every response uses the { data, meta?, message? }
| envelope. Authentication is a Sanctum bearer token: token abilities gate the
| coarse role and policies still gate the individual row.
|
*/

Route::prefix('v1')->name('api.')->group(function (): void {

    /*
    |--------------------------------------------------------------------------
    | Anonymous
    |--------------------------------------------------------------------------
    */
    Route::post('auth/register', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('auth.register');

    Route::post('auth/login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('auth.login');

    // "Where is my document?" — reference number only, heavily throttled.
    Route::get('track', [PublicTrackingController::class, 'track'])
        ->middleware('throttle:track')
        ->name('track');

    Route::get('meta', function () {
        return response()->json([
            'data' => [
                'name' => config('app.name'),
                'version' => 'v1',
                'reference_prefix' => config('portal.reference.prefix'),
                'poll_after_seconds' => (int) config('portal.tracking_poll_seconds'),
                'statuses' => ApplicationStatusCode::values(),
            ],
        ]);
    })->name('meta');

    // The machine readable contract behind /docs: one file, served as-is so the
    // Swagger UI and a Postman import always describe the same surface.
    Route::get('openapi.yaml', function () {
        $spec = base_path('docs/openapi.yaml');

        abort_unless(is_file($spec), 404, 'The API description is not installed.');

        return response(file_get_contents($spec), 200, [
            'Content-Type' => 'application/yaml',
            'Cache-Control' => 'public, max-age=300',
        ]);
    })->name('openapi');

    /*
    |--------------------------------------------------------------------------
    | Authenticated
    |--------------------------------------------------------------------------
    |
    | 'role' with no arguments is the account-active check, so a deactivated
    | account cannot keep using a token that was never revoked.
    */
    Route::middleware(['auth:sanctum', 'throttle:api', 'role'])->group(function (): void {

        Route::prefix('auth')->group(function (): void {
            Route::post('logout', [AuthController::class, 'logout'])->name('auth.logout');
            Route::get('me', [AuthController::class, 'me'])->name('auth.me');
            Route::post('refresh', [AuthController::class, 'refreshToken'])->name('auth.refresh');
        });

        Route::apiResource('applications', ApplicationController::class)
            ->parameters(['applications' => 'application']);

        Route::prefix('applications/{application}')->group(function (): void {
            Route::post('status', [TransitionController::class, 'store'])->name('applications.status');
            Route::get('status-options', [TransitionController::class, 'options'])->name('applications.status-options');
            Route::get('timeline', [ApplicationController::class, 'timeline'])->name('applications.timeline');
            Route::post('assign', [TransitionController::class, 'assign'])->name('applications.assign');
            Route::post('comments', [CommentController::class, 'store'])->name('applications.comments.store');
            Route::post('documents', [DocumentController::class, 'store'])->name('applications.documents.store');
        });

        Route::prefix('documents/{document}')->name('documents.')->group(function (): void {
            Route::get('download', [DocumentController::class, 'download'])->name('download');
            Route::patch('review', [DocumentController::class, 'review'])->name('review');
            Route::delete('', [DocumentController::class, 'destroy'])->name('destroy');
        });

        Route::get('dashboard/stats', [DashboardController::class, 'stats'])->name('dashboard.stats');

        // Reference data.
        Route::get('services', [CatalogController::class, 'services'])->name('services.index');
        Route::get('departments', [CatalogController::class, 'departments'])->name('departments.index');
        Route::get('statuses', [CatalogController::class, 'statuses'])->name('statuses.index');

        /*
        |----------------------------------------------------------------------
        | Staff only
        |----------------------------------------------------------------------
        */
        Route::middleware('role:admin,officer')->group(function (): void {
            Route::get('dashboard/workload', [DashboardController::class, 'workload'])->name('dashboard.workload');
            Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
            Route::get('audit-logs/export', [AuditLogController::class, 'export'])->name('audit.export');
            Route::get('audit-logs/{auditLog}', [AuditLogController::class, 'show'])->name('audit.show');
        });

        /*
        |----------------------------------------------------------------------
        | Administrators only
        |----------------------------------------------------------------------
        */
        Route::middleware(['role:admin', 'abilities:users:manage,catalog:manage'])->group(function (): void {
            Route::apiResource('users', UserController::class);
            Route::get('roles', [UserController::class, 'roles'])->name('roles.index');

            Route::post('departments', [CatalogController::class, 'storeDepartment'])->name('departments.store');
            Route::put('departments/{department}', [CatalogController::class, 'updateDepartment'])->name('departments.update');

            Route::post('services', [CatalogController::class, 'storeService'])->name('services.store');
            Route::put('services/{service_type}', [CatalogController::class, 'updateService'])->name('services.update');
        });
    });
});

Route::fallback(fn () => response()->json([
    'message' => 'Endpoint not available on API v1.',
    'code' => 'route_not_found',
], 404));
