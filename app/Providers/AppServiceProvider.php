<?php

namespace App\Providers;

use App\Models\User;
use App\Support\LiveUpdates;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LiveUpdates::class);

        // Broadcast once per request or command, after the work is done.
        $this->app->terminating(fn () => $this->app->make(LiveUpdates::class)->flush());

        // Memberships are cached for a request at a time; see HasTeams.
        $this->app->terminating(fn () => User::flushMembershipCache());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();

        // Each account gets its own allowance; unauthenticated calls share one per address.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user('sanctum')?->id ?: $request->ip()));

        // Long-running queue workers never "terminate", so flush after each job.
        Queue::after(function () {
            $this->app->make(LiveUpdates::class)->flush();
            User::flushMembershipCache();
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
