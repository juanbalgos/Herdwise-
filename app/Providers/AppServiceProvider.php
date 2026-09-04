<?php

namespace App\Providers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
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
        View::composer('layouts.app', function (ViewContract $view): void {
            $user = Auth::user();
            if (! $user) {
                return;
            }

            $availableFarms = $user->farms()->wherePivot('is_active', true)->orderBy('name')->get();
            $currentFarm = $availableFarms->firstWhere('id', (int) session('current_farm_id')) ?? $availableFarms->first();

            $view->with([
                'availableFarms' => $availableFarms,
                'currentFarm' => $currentFarm,
                'currentFarmRole' => $currentFarm?->pivot?->role,
            ]);
        });
    }
}
