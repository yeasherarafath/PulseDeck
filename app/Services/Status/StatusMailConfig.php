<?php

namespace App\Services\Status;

use App\Models\Status\StatusSetting;

/**
 * Applies the database mail settings at runtime so changing SMTP
 * credentials in the admin UI needs no .env edit or restart.
 */
class StatusMailConfig
{
    public static function apply(): void
    {
        $mailer = (string) StatusSetting::get('mail_mailer', config('mail.default'));

        config(['mail.default' => $mailer]);

        config([
            'mail.mailers.smtp.host' => StatusSetting::get('mail_host', config('mail.mailers.smtp.host')),
            'mail.mailers.smtp.port' => (int) StatusSetting::get('mail_port', config('mail.mailers.smtp.port')),
            'mail.mailers.smtp.username' => StatusSetting::get('mail_username', config('mail.mailers.smtp.username')),
            'mail.mailers.smtp.password' => StatusSetting::get('mail_password', config('mail.mailers.smtp.password')),
            'mail.mailers.smtp.encryption' => static::encryption(),
        ]);

        config([
            'mail.from.address' => StatusSetting::get('mail_from_address', config('mail.from.address')),
            'mail.from.name' => StatusSetting::get('mail_from_name', config('mail.from.name') ?? config('app.name')),
        ]);
    }

    public static function isConfigured(): bool
    {
        if (! (bool) StatusSetting::get('mail_enabled', false)) {
            return false;
        }

        $mailer = (string) StatusSetting::get('mail_mailer', 'smtp');

        if (in_array($mailer, ['log', 'array'], true)) {
            return true;
        }

        return (bool) StatusSetting::get('mail_host');
    }

    private static function encryption(): ?string
    {
        $value = strtolower((string) StatusSetting::get('mail_encryption', 'tls'));

        return in_array($value, ['tls', 'ssl'], true) ? $value : null;
    }
}
