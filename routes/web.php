<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ResidentController;
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
