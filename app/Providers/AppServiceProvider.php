<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class AppServiceProvider extends ServiceProvider
{



    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        RateLimiter::for('api', function (Request $request) {
        return $request->user()
            ? Limit::perMinute(120)->by($request->user()->id)
            : Limit::perMinute(60)->by($request->ip());
    });

    // 2. Strict limiter for public submissions/registrations (e.g., developer applications)
    RateLimiter::for('submissions', function (Request $request) {
        return Limit::perMinute(3)->by($request->ip());
    });

    // 3. Sensitive admin actions limiter (e.g., changing developer status)
    RateLimiter::for('admin-actions', function (Request $request) {
        return Limit::perMinute(30)->by($request->user()?->id ?: $request->ip());
    });

    RateLimiter::for('login', function (Request $request) {
        // Limit by IP address to block brute-force attacks
        return Limit::perMinute(5)->by($request->ip());
    });
    
    }
}
