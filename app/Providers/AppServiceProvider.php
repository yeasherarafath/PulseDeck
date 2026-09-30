<?php

namespace App\Providers;

use App\Enums\Status\NotificationEvent;
use App\Events\Status\IncidentCreated;
use App\Events\Status\IncidentResolved;
use App\Events\Status\IncidentUpdated;
use App\Events\Status\MaintenanceEnded;
use App\Events\Status\MaintenanceStarted;
use App\Events\Status\ServiceBecameDegraded;
use App\Events\Status\ServiceRecovered;
use App\Events\Status\ServiceWentDown;
use App\Models\Status\StatusSetting;
use App\Services\Status\NotificationManager;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

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
        // Generated URLs follow APP_URL: force https only when APP_URL is https,
        // so plain-http local setups keep working.
        if (str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
        // One Tabler-styled pagination element across every index page.
        Paginator::defaultView('vendor.pagination.status');
        Paginator::defaultSimpleView('vendor.pagination.status');
        $this->applyAdminPrefix();
        $this->registerNotificationListeners();
    }

    /**
     * The admin URL prefix is editable via settings (key: admin_prefix).
     * Guarded so artisan commands keep working on a fresh database
     * where the settings table does not exist yet.
     */
    private function applyAdminPrefix(): void
    {
        try {
            $prefix = trim((string) StatusSetting::get('admin_prefix', 'admin'), '/') ?: 'admin';
        } catch (\Throwable) {
            $prefix = 'admin';
        }

        config(['status.admin_prefix' => $prefix]);
    }

    /**
     * Monitoring events fan out through the NotificationManager, which
     * honors the settings kill-switches and per-channel rules.
     */
    private function registerNotificationListeners(): void
    {
        Event::listen(ServiceWentDown::class, function (ServiceWentDown $event): void {
            app(NotificationManager::class)->notify(
                NotificationEvent::ServiceFailed,
                $event->service,
                "{$event->service->name} is down",
                array_filter([
                    $event->outcome?->errorMessage,
                    $event->outcome?->httpStatus ? "HTTP {$event->outcome->httpStatus}" : null,
                    'Checked '.$event->service->last_checked_at?->diffForHumans(),
                ]),
                url('/status'),
            );
        });

        Event::listen(ServiceRecovered::class, function (ServiceRecovered $event): void {
            app(NotificationManager::class)->notify(
                NotificationEvent::ServiceRecovered,
                $event->service,
                "{$event->service->name} recovered",
                ['The service is responding normally again.'],
                url('/status'),
            );
        });

        Event::listen(ServiceBecameDegraded::class, function (ServiceBecameDegraded $event): void {
            app(NotificationManager::class)->notify(
                NotificationEvent::ServiceDegraded,
                $event->service,
                "{$event->service->name} is degraded",
                array_filter([
                    'Responses are slower than the warning threshold.',
                    $event->outcome?->responseTimeMs !== null ? "Last response: {$event->outcome->responseTimeMs} ms" : null,
                ]),
                url('/status'),
            );
        });

        $incidentUrl = fn ($incident) => url('/status/incidents/'.$incident->slug);

        Event::listen(IncidentCreated::class, function (IncidentCreated $event) use ($incidentUrl): void {
            app(NotificationManager::class)->notify(
                NotificationEvent::IncidentCreated,
                $event->incident->service,
                "Incident opened: {$event->incident->title}",
                ["Impact: {$event->incident->impact->label()}"],
                $incidentUrl($event->incident),
            );
        });

        Event::listen(IncidentUpdated::class, function (IncidentUpdated $event) use ($incidentUrl): void {
            app(NotificationManager::class)->notify(
                NotificationEvent::IncidentUpdated,
                $event->incident->service,
                "Incident update: {$event->incident->title}",
                ["Status: {$event->incident->status->label()}"],
                $incidentUrl($event->incident),
            );
        });

        Event::listen(IncidentResolved::class, function (IncidentResolved $event) use ($incidentUrl): void {
            app(NotificationManager::class)->notify(
                NotificationEvent::IncidentResolved,
                $event->incident->service,
                "Incident resolved: {$event->incident->title}",
                ['The service is operating normally.'],
                $incidentUrl($event->incident),
            );
        });

        Event::listen(MaintenanceStarted::class, function (MaintenanceStarted $event): void {
            app(NotificationManager::class)->notify(
                NotificationEvent::MaintenanceStarted,
                null,
                "Maintenance started: {$event->maintenance->title}",
                ["Until {$event->maintenance->ends_at->format('M j, H:i')}."],
                url('/status'),
            );
        });

        Event::listen(MaintenanceEnded::class, function (MaintenanceEnded $event): void {
            app(NotificationManager::class)->notify(
                NotificationEvent::MaintenanceEnded,
                null,
                "Maintenance ended: {$event->maintenance->title}",
                [],
                url('/status'),
            );
        });
    }
}
