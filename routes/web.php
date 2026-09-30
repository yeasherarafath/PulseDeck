<?php

use App\Http\Controllers\Admin\Status\ApiDocsController;
use App\Http\Controllers\Admin\Status\AuditLogController;
use App\Http\Controllers\Admin\Status\DashboardController;
use App\Http\Controllers\Admin\Status\DeliveryController;
use App\Http\Controllers\Admin\Status\GroupController;
use App\Http\Controllers\Admin\Status\IncidentController;
use App\Http\Controllers\Admin\Status\MaintenanceController;
use App\Http\Controllers\Admin\Status\MonitoringController;
use App\Http\Controllers\Admin\Status\NotificationChannelController;
use App\Http\Controllers\Admin\Status\NotificationRuleController;
use App\Http\Controllers\Admin\Status\ServiceController;
use App\Http\Controllers\Admin\Status\SettingsController;
use App\Http\Controllers\Admin\Status\SubscriberController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\Status\StatusPageController;
use Illuminate\Support\Facades\Route;

// Public home is served directly (subdomain-ready): no redirect to /status.
Route::get('/', [StatusPageController::class, 'index'])->name('home');

// Core session auth (no starter-kit packages).
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login'])->middleware('throttle:5,1');
    Route::get('forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email')->middleware('throttle:5,1');
    Route::get('reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('reset-password', [ResetPasswordController::class, 'reset'])->name('password.update')->middleware('throttle:5,1');
});

Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

Route::prefix('status')->name('status.')->group(function () {
    Route::get('/', [StatusPageController::class, 'index'])->name('index');
    Route::get('refresh', [StatusPageController::class, 'refresh'])->name('refresh')->middleware('throttle:60,1');
    Route::get('badge.svg', [StatusPageController::class, 'badge'])->name('badge');
    Route::get('services/{service:slug}', [StatusPageController::class, 'showService'])->name('services.show');
    Route::get('incidents/{incident:slug}', [StatusPageController::class, 'showIncident'])->name('incidents.show');
    Route::post('subscribe', [StatusPageController::class, 'subscribe'])->name('subscribe')->middleware('throttle:10,1');
    Route::get('verify/{token}', [StatusPageController::class, 'verify'])->name('verify');
    Route::get('unsubscribe/{token}', [StatusPageController::class, 'confirmUnsubscribe'])->name('unsubscribe');
    Route::post('unsubscribe/{token}', [StatusPageController::class, 'unsubscribe'])->name('unsubscribe.confirm')->middleware('throttle:10,1');
});

Route::prefix(trim((string) config('status.admin_prefix', 'admin'), '/').'/status')->name('admin.status.')->middleware(['auth', 'permission:status.view'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('api-docs', ApiDocsController::class)->name('api-docs');

    Route::get('monitoring', MonitoringController::class)->name('monitoring')->middleware('permission:status.monitoring.view');

    Route::prefix('groups')->name('groups.')->group(function () {
        Route::get('/', [GroupController::class, 'index'])->name('index')->middleware('permission:status.services.view');
        Route::post('/', [GroupController::class, 'store'])->name('store')->middleware('permission:status.services.create');
        Route::put('{group}', [GroupController::class, 'update'])->name('update')->middleware('permission:status.services.update');
        Route::delete('{group}', [GroupController::class, 'destroy'])->name('destroy')->middleware('permission:status.services.delete');
    });

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

    Route::prefix('incidents')->name('incidents.')->group(function () {
        Route::get('/', [IncidentController::class, 'index'])->name('index')->middleware('permission:status.incidents.view');
        Route::get('create', [IncidentController::class, 'create'])->name('create')->middleware('permission:status.incidents.create');
        Route::post('/', [IncidentController::class, 'store'])->name('store')->middleware('permission:status.incidents.create');
        Route::get('{incident}', [IncidentController::class, 'show'])->name('show')->middleware('permission:status.incidents.view');
        Route::get('{incident}/edit', [IncidentController::class, 'edit'])->name('edit')->middleware('permission:status.incidents.update');
        Route::put('{incident}', [IncidentController::class, 'update'])->name('update')->middleware('permission:status.incidents.update');
        Route::delete('{incident}', [IncidentController::class, 'destroy'])->name('destroy')->middleware('permission:status.incidents.delete');
        Route::post('{incident}/updates', [IncidentController::class, 'storeUpdate'])->name('updates.store')->middleware('permission:status.incidents.update');
    });

    Route::prefix('maintenances')->name('maintenances.')->group(function () {
        Route::get('/', [MaintenanceController::class, 'index'])->name('index')->middleware('permission:status.maintenance.view');
        Route::get('create', [MaintenanceController::class, 'create'])->name('create')->middleware('permission:status.maintenance.create');
        Route::post('/', [MaintenanceController::class, 'store'])->name('store')->middleware('permission:status.maintenance.create');
        Route::get('{maintenance}/edit', [MaintenanceController::class, 'edit'])->name('edit')->middleware('permission:status.maintenance.update');
        Route::put('{maintenance}', [MaintenanceController::class, 'update'])->name('update')->middleware('permission:status.maintenance.update');
        Route::delete('{maintenance}', [MaintenanceController::class, 'destroy'])->name('destroy')->middleware('permission:status.maintenance.delete');
        Route::post('{maintenance}/cancel', [MaintenanceController::class, 'cancel'])->name('cancel')->middleware('permission:status.maintenance.update');
    });

    Route::prefix('notifications')->name('notifications.')->middleware('permission:status.notifications.manage')->group(function () {
        Route::get('channels', [NotificationChannelController::class, 'index'])->name('channels');
        Route::post('channels', [NotificationChannelController::class, 'store'])->name('channels.store');
        Route::put('channels/{channel}', [NotificationChannelController::class, 'update'])->name('channels.update');
        Route::delete('channels/{channel}', [NotificationChannelController::class, 'destroy'])->name('channels.destroy');
        Route::get('rules', [NotificationRuleController::class, 'index'])->name('rules');
        Route::post('rules', [NotificationRuleController::class, 'store'])->name('rules.store');
        Route::delete('rules/{rule}', [NotificationRuleController::class, 'destroy'])->name('rules.destroy');
        Route::get('subscribers', [SubscriberController::class, 'index'])->name('subscribers');
        Route::get('deliveries', [DeliveryController::class, 'index'])->name('deliveries');
        Route::post('subscribers/{subscriber}/toggle', [SubscriberController::class, 'toggle'])->name('subscribers.toggle');
        Route::delete('subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->name('subscribers.destroy');
    });

    Route::prefix('settings')->name('settings')->middleware('permission:status.settings.manage')->group(function () {
        Route::get('/', [SettingsController::class, 'index']);
        Route::put('/', [SettingsController::class, 'update'])->name('.update');
        Route::post('test-mail', [SettingsController::class, 'testMail'])->name('.test-mail')->middleware('throttle:5,1');
        Route::post('test-webhook', [SettingsController::class, 'testWebhook'])->name('.test-webhook')->middleware('throttle:5,1');
    });

    Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit-logs')->middleware('permission:status.settings.manage');
});
