<?php

use App\Http\Controllers\Admin\Status\DashboardController;
use App\Http\Controllers\Admin\Status\MonitoringController;
use App\Http\Controllers\Admin\Status\ServiceController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/status');

Route::prefix('status')->name('status.')->group(function () {
    Route::get('/', fn () => view('status.index'))->name('index');
});

Route::prefix('admin/status')->name('admin.status.')->middleware(['auth', 'permission:status.view'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('monitoring', MonitoringController::class)->name('monitoring')->middleware('permission:status.monitoring.view');

    Route::prefix('services')->name('services.')->group(function () {
        Route::get('/', [ServiceController::class, 'index'])->name('index')->middleware('permission:status.services.view');
        Route::get('create', [ServiceController::class, 'create'])->name('create')->middleware('permission:status.services.create');
        Route::post('/', [ServiceController::class, 'store'])->name('store')->middleware('permission:status.services.create');
        Route::post('test', [ServiceController::class, 'testUnsaved'])->name('test-unsaved')->middleware(['permission:status.monitoring.run', 'throttle:10,1']);
        Route::get('{service}', [ServiceController::class, 'show'])->name('show')->middleware('permission:status.services.view');
        Route::get('{service}/edit', [ServiceController::class, 'edit'])->name('edit')->middleware('permission:status.services.update');
        Route::put('{service}', [ServiceController::class, 'update'])->name('update')->middleware('permission:status.services.update');
        Route::delete('{service}', [ServiceController::class, 'destroy'])->name('destroy')->middleware('permission:status.services.delete');
        Route::post('{service}/pause', [ServiceController::class, 'pause'])->name('pause')->middleware('permission:status.services.update');
        Route::post('{service}/check', [ServiceController::class, 'check'])->name('check')->middleware(['permission:status.monitoring.run', 'throttle:30,1']);
        Route::post('{service}/test', [ServiceController::class, 'test'])->name('test')->middleware(['permission:status.monitoring.run', 'throttle:10,1']);
    });
});
