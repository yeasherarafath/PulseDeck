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
