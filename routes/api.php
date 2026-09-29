<?php

use App\Http\Controllers\Api\StatusApiController;
use Illuminate\Support\Facades\Route;

Route::prefix('status')->name('api.status.')->middleware('throttle:60,1')->group(function () {
    Route::get('/', [StatusApiController::class, 'index'])->name('index');
    Route::get('services', [StatusApiController::class, 'services'])->name('services');
    Route::get('services/{slug}', [StatusApiController::class, 'show'])->name('services.show');
    Route::get('incidents', [StatusApiController::class, 'incidents'])->name('incidents');
});
