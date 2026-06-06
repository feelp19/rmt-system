<?php

namespace App\Providers;

use App\Models\LedgerEntry;
use App\Models\User;
use App\Policies\LedgerPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Opcodes\LogViewer\Facades\LogViewer;

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
        // LedgerPolicy não segue a convenção ModelNamePolicy (seria LedgerEntryPolicy),
        // então registramos explicitamente para o auto-discovery funcionar.
        Gate::policy(LedgerEntry::class, LedgerPolicy::class);

        Gate::define('viewPulse', function (?User $user) {
            return $this->app->environment('local')
                || $user?->email === 'flp.pietro19@gmail.com';
        });

        LogViewer::auth(function ($request) {
            return $this->app->environment('local')
                || $request->user()?->email === 'flp.pietro19@gmail.com';
        });
    }
}
