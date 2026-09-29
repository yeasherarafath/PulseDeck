<?php

namespace App\Enums\Status;

enum NotificationEvent: string
{
    case ServiceFailed = 'service.failed';
    case ServiceDegraded = 'service.degraded';
    case ServiceRecovered = 'service.recovered';
    case IncidentCreated = 'incident.created';
    case IncidentUpdated = 'incident.updated';
    case IncidentResolved = 'incident.resolved';
    case MaintenanceStarted = 'maintenance.started';
    case MaintenanceEnded = 'maintenance.ended';

    public function label(): string
    {
        return match ($this) {
            self::ServiceFailed => 'Service failed',
            self::ServiceDegraded => 'Service degraded',
            self::ServiceRecovered => 'Service recovered',
            self::IncidentCreated => 'Incident created',
            self::IncidentUpdated => 'Incident updated',
            self::IncidentResolved => 'Incident resolved',
            self::MaintenanceStarted => 'Maintenance started',
            self::MaintenanceEnded => 'Maintenance ended',
        };
    }

    public function settingKey(): string
    {
        return match ($this) {
            self::ServiceFailed => 'notify_on_service_failed',
            self::ServiceDegraded => 'notify_on_service_degraded',
            self::ServiceRecovered => 'notify_on_service_recovered',
            self::IncidentCreated => 'notify_on_incident_created',
            self::IncidentUpdated => 'notify_on_incident_updated',
            self::IncidentResolved => 'notify_on_incident_resolved',
            self::MaintenanceStarted => 'notify_on_maintenance_started',
            self::MaintenanceEnded => 'notify_on_maintenance_ended',
        };
    }
}
