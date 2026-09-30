<?php

use App\Models\Status\StatusSetting;

if (! function_exists('setting')) {
    /**
     * Read an application setting from the status_settings table.
     *
     * Usage: setting('app_name'), setting('mail_enabled', false).
     */
    function setting(string $key, mixed $default = null): mixed
    {
        return StatusSetting::get($key, $default);
    }
}

if (! function_exists('setting_bool')) {
    /**
     * Read a setting cast to boolean.
     */
    function setting_bool(string $key, bool $default = false): bool
    {
        return (bool) StatusSetting::get($key, $default);
    }
}

if (! function_exists('setting_int')) {
    /**
     * Read a setting cast to integer.
     */
    function setting_int(string $key, int $default = 0): int
    {
        return (int) StatusSetting::get($key, $default);
    }
}

if (! function_exists('admin_base_path')) {
    /**
     * Configured admin URL prefix (e.g. "admin"), editable via settings.
     */
    function admin_base_path(): string
    {
        return trim((string) config('status.admin_prefix', 'admin'), '/') ?: 'admin';
    }
}

if (! function_exists('setting_timezone')) {
    /**
     * Configured display timezone (validated PHP identifier, UTC fallback).
     * Monitoring internals stay on app time; this is for display only.
     */
    function setting_timezone(): string
    {
        $tz = (string) setting('timezone', 'UTC');

        try {
            new DateTimeZone($tz);

            return $tz;
        } catch (Throwable) {
            return 'UTC';
        }
    }
}

if (! function_exists('setting_time')) {
    /**
     * Format a date/time in the configured display timezone.
     */
    function setting_time(DateTimeInterface|string|null $value, string $format = 'M j, H:i'): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $dt = $value instanceof DateTimeInterface
            ? Carbon\Carbon::parse($value)
            : Carbon\Carbon::parse((string) $value);

        return $dt->setTimezone(setting_timezone())->format($format);
    }
}

if (! function_exists('branding_asset')) {
    /**
     * Public URL for an uploaded branding file (logo/favicon), or null.
     * Stored paths are relative to the public disk (e.g. "branding/logo.png").
     */
    function branding_asset(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return asset('storage/'.ltrim($path, '/'));
    }
}

if (! function_exists('setting_input_to_utc')) {
    /**
     * Interpret a datetime-local form value in the configured display
     * timezone and convert it to the app (UTC) timezone for storage.
     */
    function setting_input_to_utc(string $value): Carbon\Carbon
    {
        return Carbon\Carbon::parse($value, setting_timezone())->utc();
    }
}

if (! function_exists('setting_utc_to_input')) {
    /**
     * Format a stored timestamp as a datetime-local value in the display timezone.
     */
    function setting_utc_to_input(DateTimeInterface|string|null $value): string
    {
        return (string) setting_time($value, 'Y-m-d\TH:i');
    }
}
