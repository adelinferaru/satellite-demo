<?php

namespace App\Providers;

use App\Repositories\ISSContract;
use App\Repositories\ISSGateway;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ISSContract::class, fn () => new ISSGateway(
            timeoutSeconds: (int) config('services.iss.http_timeout', 5),
            cacheSeconds: (int) config('services.iss.cache_seconds', 1),
        ));
    }

    public function boot(): void
    {
        // The nestedflowtracker viewer (/flow) is open in local; in other
        // environments it requires this gate. This is a public showcase, so the
        // traces are intentionally viewable. Tighten this to restrict access.
        Gate::define('viewFlow', static fn ($user = null) => true);
    }
}
