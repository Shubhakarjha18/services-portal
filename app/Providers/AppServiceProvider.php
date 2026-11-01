<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Application;
use App\Models\ApplicationDocument;
use App\Policies\ApplicationDocumentPolicy;
use App\Policies\ApplicationPolicy;
use App\Support\Audit\AuditLogger;
use App\Support\ReferenceNumberGenerator;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ReferenceNumberGenerator::class);
        $this->app->bind(AuditLogger::class, fn ($app) => new AuditLogger($app['request']));
    }

    public function boot(): void
    {
        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(ApplicationDocument::class, ApplicationDocumentPolicy::class);

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->registerRateLimiters();
    }

    private function registerRateLimiters(): void
    {
        $limits = config('portal.rate_limits', []);

        RateLimiter::for('login', fn (Request $request) => Limit::perMinute((int) ($limits['login'] ?? 10))
            ->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip()));

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute((int) ($limits['register'] ?? 5))
            ->by($request->ip()));

        // Unauthenticated tracking lookups are the most exposed endpoint.
        RateLimiter::for('track', fn (Request $request) => [
            Limit::perMinute((int) ($limits['track'] ?? 30))->by($request->ip()),
        ]);

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute((int) ($limits['api'] ?? 120))
            ->by($request->user()?->id ?: $request->ip()));
    }
}
