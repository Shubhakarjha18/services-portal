<?php

declare(strict_types=1);

use App\Http\Controllers\Web\Admin\AuditController;
use App\Http\Controllers\Web\Admin\CatalogController;
use App\Http\Controllers\Web\Admin\UserController;
use App\Http\Controllers\Web\ApplicationController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\HomeController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Session-authenticated Inertia routes
|--------------------------------------------------------------------------
|
| The Vue front-end talks to these routes; the same service layer also backs
| the stateless /api/v1 surface, so both entry points enforce identical rules.
|
*/

Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])
        ->middleware('throttle:login')
        ->name('login.attempt');

    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])
        ->middleware('throttle:register')
        ->name('register.store');
});

Route::middleware('auth')->group(function () {
    // Signing out stays available even after an administrator deactivates the
    // account, so the user is returned to the login screen rather than a 403.
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
});

// 'role' without arguments is the account-active check.
Route::middleware(['auth', 'role'])->group(function () {
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('applications', [ApplicationController::class, 'index'])->name('applications.index');
    Route::get('applications/create', [ApplicationController::class, 'create'])->name('applications.create');
    Route::post('applications', [ApplicationController::class, 'store'])->name('applications.store');
    Route::get('applications/{application}', [ApplicationController::class, 'show'])->name('applications.show');
    Route::delete('applications/{application}', [ApplicationController::class, 'destroy'])->name('applications.destroy');

    Route::post('applications/{application}/status', [ApplicationController::class, 'transition'])->name('applications.status');
    Route::post('applications/{application}/assign', [ApplicationController::class, 'assign'])->name('applications.assign');
    Route::post('applications/{application}/comments', [ApplicationController::class, 'comment'])->name('applications.comments.store');
    Route::post('applications/{application}/documents', [ApplicationController::class, 'upload'])->name('applications.documents.store');

    Route::get('documents/{document}/download', [ApplicationController::class, 'download'])->name('portal.documents.download');
    Route::post('documents/{document}/review', [ApplicationController::class, 'reviewDocument'])->name('portal.documents.review');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('users.index');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');

        Route::get('catalog', [CatalogController::class, 'index'])->name('catalog.index');
        Route::post('departments', [CatalogController::class, 'storeDepartment'])->name('departments.store');
        Route::put('departments/{department}', [CatalogController::class, 'updateDepartment'])->name('departments.update');
        Route::post('services', [CatalogController::class, 'storeService'])->name('services.store');
        Route::put('services/{service_type}', [CatalogController::class, 'updateService'])->name('services.update');

        Route::get('audit', [AuditController::class, 'index'])->name('audit.index');
    });
});
