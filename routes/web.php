<?php

use App\Http\Controllers\AnimalController;
use App\Http\Controllers\Api\V1\CareActivityController as ApiCareActivityController;
use App\Http\Controllers\Api\V1\LivestockController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BatchController;
use App\Http\Controllers\CareActivityController;
use App\Http\Controllers\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/dashboard');
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->name('register.store');
});
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('animals', AnimalController::class)->only(['index', 'create', 'store', 'show']);
    Route::resource('batches', BatchController::class)->only(['index', 'create', 'store', 'show']);
    Route::get('/activities', [CareActivityController::class, 'index'])->name('activities.index');
    Route::get('/activities/create/{type}', [CareActivityController::class, 'create'])->name('activities.create');
    Route::post('/activities/health', [CareActivityController::class, 'storeHealth'])->name('activities.health.store');
    Route::post('/activities/treatments', [CareActivityController::class, 'storeTreatment'])->name('activities.treatments.store');
    Route::post('/activities/vaccinations', [CareActivityController::class, 'storeVaccination'])->name('activities.vaccinations.store');
    Route::post('/activities/deworming', [CareActivityController::class, 'storeDeworming'])->name('activities.deworming.store');
    Route::patch('/reminders/{reminder}/complete', [CareActivityController::class, 'completeReminder'])->name('reminders.complete');
    Route::prefix('api/v1')->name('api.v1.')->group(function () {
        Route::get('/animals', [LivestockController::class, 'animals'])->name('animals.index');
        Route::get('/batches', [LivestockController::class, 'batches'])->name('batches.index');
        Route::get('/health-records', [ApiCareActivityController::class, 'healthRecords'])->name('health-records.index');
        Route::get('/reminders', [ApiCareActivityController::class, 'reminders'])->name('reminders.index');
    });
});
