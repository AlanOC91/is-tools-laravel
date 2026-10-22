<?php

namespace App\Providers;

use App\Models\User;
use App\Services\DirectoryService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app->bind(DirectoryService::class, fn () => DirectoryService::fromConfig());
        Gate::define('admin', fn (User $user) => $user->isAdministrator());
        RateLimiter::for('login', fn (Request $r) => [
            Limit::perMinute(5)->by(strtolower((string) $r->input('username')).'|'.$r->ip()),
            Limit::perMinute(30)->by($r->ip()),
        ]);
        RateLimiter::for('submissions', fn (Request $r) => Limit::perMinute(15)->by($r->user()?->id ?? $r->ip()));
        if (str_starts_with(config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
