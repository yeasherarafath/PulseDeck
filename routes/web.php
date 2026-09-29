<?php

use App\Http\Controllers\Admin\Status\DashboardController;
use Illuminate\Support\Facades\Route;

Route::redirect('/', '/status');

Route::prefix('status')->name('status.')->group(function () {
    Route::get('/', fn () => view('status.index'))->name('index');
});

Route::prefix('admin/status')->name('admin.status.')->middleware(['auth', 'permission:status.view'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
});
