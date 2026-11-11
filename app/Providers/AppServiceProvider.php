<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $permisos = [
            'documents.view',
            'documents.create',
            'documents.update',
            'documents.upload',
            'documents.turn',
            'turns.update',
            'turns.respond',
            'admin.catalogs',
            'admin.users',
            'audit.view',
        ];

        foreach ($permisos as $permiso) {
            Gate::define($permiso, fn (User $user) => $user->hasPermission($permiso));
        }

        RateLimiter::for('login', function (Request $request) {
            return Limit::perMinute(10)->by(Str::transliterate(
                Str::lower($request->string('email')->toString()).'|'.$request->ip()
            ));
        });
    }
}
