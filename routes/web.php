<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ResidentController;
use App\Http\Controllers\ServiceRequestController;
use Illuminate\Support\Facades\Route;

/*
 * Record identifiers in URLs are plain digits, short enough to always fit a
 * database integer. Anything else is not a record and resolves to 404.
 */
Route::pattern('id', '[0-9]{1,18}');

Route::get('/', [HomeController::class, 'index']);

Route::get('/health', [HealthController::class, 'index']);

Route::controller(ResidentController::class)->group(function () {
    Route::get('/residents', 'index')->name('residents.index');
    Route::get('/residents/register', 'create')->name('residents.create');
    Route::post('/residents', 'store')->name('residents.store');
    Route::get('/residents/{id}/edit', 'edit')->name('residents.edit');
    Route::put('/residents/{id}', 'update')->name('residents.update');
    Route::post('/residents/{id}/deactivate', 'deactivate')->name('residents.deactivate');
});

Route::controller(ServiceRequestController::class)->group(function () {
    Route::get('/service-requests', 'index')->name('service-requests.index');
    Route::get('/service-requests/new', 'create')->name('service-requests.create');
    Route::post('/service-requests', 'store')->name('service-requests.store');
    Route::get('/service-requests/{id}', 'show')->name('service-requests.show');
    Route::post('/service-requests/{id}/start', 'start')->name('service-requests.start');
    Route::post('/service-requests/{id}/complete', 'complete')->name('service-requests.complete');
    Route::post('/service-requests/{id}/cancel', 'cancel')->name('service-requests.cancel');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
