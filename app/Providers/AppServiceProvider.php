<?php

namespace App\Providers;

use App\Notifications\ReminderDueNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\DevCommands;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Illuminate\View\View as ViewContract;

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
        DevCommands::artisan('schedule:work', 'scheduler')->yellow();

        RateLimiter::for('login', function (Request $request): Limit {
            $emailHash = hash('sha256', Str::lower($request->string('email')->toString()));

            return Limit::perMinute(5)->by($emailHash.'|'.$request->ip());
        });
        RateLimiter::for('registration', fn (Request $request): Limit => Limit::perHour(5)->by((string) $request->ip()));

        View::composer('layouts.app', function (ViewContract $view): void {
            $user = Auth::user();
            if (! $user) {
                return;
            }

            $availableFarms = $user->farms()->wherePivot('is_active', true)->orderBy('name')->get();
            $currentFarm = $availableFarms->firstWhere('id', (int) session('current_farm_id')) ?? $availableFarms->first();
            $unreadNotificationCount = $currentFarm
                ? $user->unreadNotifications()
                    ->where('type', ReminderDueNotification::DATABASE_TYPE)
                    ->where('data->farm_id', $currentFarm->id)
                    ->count()
                : 0;

            $view->with([
                'availableFarms' => $availableFarms,
                'currentFarm' => $currentFarm,
                'currentFarmRole' => $currentFarm?->pivot?->role,
                'unreadNotificationCount' => $unreadNotificationCount,
            ]);
        });
    }
}
