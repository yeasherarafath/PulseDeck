<?php

namespace App\Enums\Status;

enum SettingGroup: string
{
    case General = 'general';
    case Branding = 'branding';
    case Monitoring = 'monitoring';
    case Public = 'public';
    case Mail = 'mail';
    case Alerts = 'alerts';
    case Webhook = 'webhook';
    case Retention = 'retention';

    public function label(): string
    {
        return match ($this) {
            self::General => 'General',
            self::Branding => 'Branding',
            self::Monitoring => 'Monitoring',
            self::Public => 'Public page',
            self::Mail => 'Email',
            self::Alerts => 'Alerts',
            self::Webhook => 'Webhook',
            self::Retention => 'Retention',
        };
    }
}
