<?php

namespace App\Providers;

use App\Models\AdminUser;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        Gate::define('manage-admin', fn (AdminUser $user) => $user->is_active);
        RateLimiter::for('registration', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        RateLimiter::for('admin-login', fn (Request $request) => [
            Limit::perMinute(20)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('account:'.hash('sha256', strtolower((string) $request->input('email')))),
        ]);
    }
}
