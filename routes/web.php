<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ResidentController;
use App\Http\Controllers\ServiceRequestController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index']);

Route::get('/health', [HealthController::class, 'index']);

Route::controller(ResidentController::class)->group(function () {
    Route::get('/residents', 'index')->name('residents.index');
    Route::get('/residents/register', 'create')->name('residents.create');
    Route::post('/residents', 'store')->name('residents.store');
    Route::get('/residents/{id}/edit', 'edit')->whereNumber('id')->name('residents.edit');
    Route::put('/residents/{id}', 'update')->whereNumber('id')->name('residents.update');
    Route::post('/residents/{id}/deactivate', 'deactivate')->whereNumber('id')->name('residents.deactivate');
});

Route::controller(ServiceRequestController::class)->group(function () {
    Route::get('/service-requests', 'index')->name('service-requests.index');
    Route::get('/service-requests/new', 'create')->name('service-requests.create');
    Route::post('/service-requests', 'store')->name('service-requests.store');
    Route::get('/service-requests/{id}', 'show')->whereNumber('id')->name('service-requests.show');
    Route::post('/service-requests/{id}/start', 'start')->whereNumber('id')->name('service-requests.start');
    Route::post('/service-requests/{id}/complete', 'complete')->whereNumber('id')->name('service-requests.complete');
    Route::post('/service-requests/{id}/cancel', 'cancel')->whereNumber('id')->name('service-requests.cancel');
});
