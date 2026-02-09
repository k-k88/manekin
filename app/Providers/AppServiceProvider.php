<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Gate;

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
        // ✅ super_admin 権限 Gate
        Gate::define('super-admin', function ($user) {
            return $user->role === 'super_admin';
        });
  if (app()->environment('production')) {
        URL::forceScheme('https');
    }
        // ✅ ngrok（https環境）でもHTTPSを強制
       
    }
}
