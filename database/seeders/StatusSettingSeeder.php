<?php

namespace Database\Seeders;

use App\Enums\Status\SettingGroup;
use App\Enums\Status\SettingType;
use App\Models\Status\StatusSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

class StatusSettingSeeder extends Seeder
{
    /**
     * @var list<array{key: string, value: string|null, type: SettingType, group: SettingGroup, encrypted: bool, description: string}>
     */
    private const DEFAULTS = [
        // General
        ['key' => 'app_name', 'value' => 'Status', 'type' => SettingType::String, 'group' => SettingGroup::General, 'encrypted' => false, 'description' => 'Status page name shown in headers and titles.'],
        ['key' => 'app_tagline', 'value' => 'Service status & uptime', 'type' => SettingType::String, 'group' => SettingGroup::General, 'encrypted' => false, 'description' => 'Short tagline under the page name.'],
        ['key' => 'app_description', 'value' => 'Live service status and uptime history.', 'type' => SettingType::String, 'group' => SettingGroup::General, 'encrypted' => false, 'description' => 'SEO meta description for the public page.'],
        ['key' => 'base_url', 'value' => null, 'type' => SettingType::String, 'group' => SettingGroup::General, 'encrypted' => false, 'description' => 'Canonical base URL (used for links in notifications).'],
        ['key' => 'contact_email', 'value' => null, 'type' => SettingType::String, 'group' => SettingGroup::General, 'encrypted' => false, 'description' => 'Public contact email.'],
        ['key' => 'timezone', 'value' => 'UTC', 'type' => SettingType::String, 'group' => SettingGroup::General, 'encrypted' => false, 'description' => 'Display timezone for dates.'],
        ['key' => 'theme_default', 'value' => 'light', 'type' => SettingType::String, 'group' => SettingGroup::General, 'encrypted' => false, 'description' => 'Default theme for admin and public pages.'],
        ['key' => 'theme_allow_user_toggle', 'value' => '1', 'type' => SettingType::Boolean, 'group' => SettingGroup::General, 'encrypted' => false, 'description' => 'Allow visitors to switch dark/light mode.'],
        ['key' => 'admin_prefix', 'value' => 'admin', 'type' => SettingType::String, 'group' => SettingGroup::General, 'encrypted' => false, 'description' => 'Admin URL prefix (e.g. admin → /admin/status). Changing it moves the whole admin panel; clear route cache afterwards.'],
        // Branding
        ['key' => 'logo_path', 'value' => null, 'type' => SettingType::String, 'group' => SettingGroup::Branding, 'encrypted' => false, 'description' => 'Light-mode logo path.'],
        ['key' => 'logo_dark_path', 'value' => null, 'type' => SettingType::String, 'group' => SettingGroup::Branding, 'encrypted' => false, 'description' => 'Dark-mode logo path.'],
        ['key' => 'favicon_path', 'value' => null, 'type' => SettingType::String, 'group' => SettingGroup::Branding, 'encrypted' => false, 'description' => 'Favicon path.'],
        ['key' => 'footer_text', 'value' => null, 'type' => SettingType::String, 'group' => SettingGroup::Branding, 'encrypted' => false, 'description' => 'Custom footer text for the public page.'],
        // Monitoring defaults
        ['key' => 'default_check_interval', 'value' => '300', 'type' => SettingType::Integer, 'group' => SettingGroup::Monitoring, 'encrypted' => false, 'description' => 'Default check interval in seconds for new services.'],
        ['key' => 'default_timeout', 'value' => '15', 'type' => SettingType::Integer, 'group' => SettingGroup::Monitoring, 'encrypted' => false, 'description' => 'Default request timeout in seconds.'],
        ['key' => 'default_connect_timeout', 'value' => '5', 'type' => SettingType::Integer, 'group' => SettingGroup::Monitoring, 'encrypted' => false, 'description' => 'Default connection timeout in seconds.'],
        ['key' => 'failure_threshold', 'value' => '3', 'type' => SettingType::Integer, 'group' => SettingGroup::Monitoring, 'encrypted' => false, 'description' => 'Consecutive failures before an incident opens.'],
        ['key' => 'recovery_threshold', 'value' => '2', 'type' => SettingType::Integer, 'group' => SettingGroup::Monitoring, 'encrypted' => false, 'description' => 'Consecutive successes before an incident resolves.'],
        ['key' => 'stale_after_multiplier', 'value' => '3', 'type' => SettingType::Integer, 'group' => SettingGroup::Monitoring, 'encrypted' => false, 'description' => 'Intervals without a check before a service shows Unknown.'],
        // Public page
        ['key' => 'public_page_enabled', 'value' => '1', 'type' => SettingType::Boolean, 'group' => SettingGroup::Public, 'encrypted' => false, 'description' => 'Enable the public status page.'],
        ['key' => 'public_api_enabled', 'value' => '1', 'type' => SettingType::Boolean, 'group' => SettingGroup::Public, 'encrypted' => false, 'description' => 'Enable the public status API.'],
        ['key' => 'subscriptions_enabled', 'value' => '1', 'type' => SettingType::Boolean, 'group' => SettingGroup::Public, 'encrypted' => false, 'description' => 'Allow visitors to subscribe to incident emails.'],
        ['key' => 'badge_enabled', 'value' => '1', 'type' => SettingType::Boolean, 'group' => SettingGroup::Public, 'encrypted' => false, 'description' => 'Enable the embeddable status badge.'],
        ['key' => 'public_refresh_seconds', 'value' => '45', 'type' => SettingType::Integer, 'group' => SettingGroup::Public, 'encrypted' => false, 'description' => 'Public page polling interval in seconds.'],
        ['key' => 'uptime_window_days', 'value' => '90', 'type' => SettingType::Integer, 'group' => SettingGroup::Public, 'encrypted' => false, 'description' => 'Days of uptime history shown publicly.'],
        // Mail credentials
        ['key' => 'mail_enabled', 'value' => '0', 'type' => SettingType::Boolean, 'group' => SettingGroup::Mail, 'encrypted' => false, 'description' => 'Enable outgoing mail.'],
        ['key' => 'mail_mailer', 'value' => 'smtp', 'type' => SettingType::String, 'group' => SettingGroup::Mail, 'encrypted' => false, 'description' => 'Mail transport (smtp, sendmail, log).'],
        ['key' => 'mail_host', 'value' => null, 'type' => SettingType::String, 'group' => SettingGroup::Mail, 'encrypted' => false, 'description' => 'SMTP host.'],
        ['key' => 'mail_port', 'value' => '587', 'type' => SettingType::Integer, 'group' => SettingGroup::Mail, 'encrypted' => false, 'description' => 'SMTP port.'],
        ['key' => 'mail_username', 'value' => null, 'type' => SettingType::String, 'group' => SettingGroup::Mail, 'encrypted' => false, 'description' => 'SMTP username.'],
        ['key' => 'mail_password', 'value' => null, 'type' => SettingType::String, 'group' => SettingGroup::Mail, 'encrypted' => true, 'description' => 'SMTP password (encrypted).'],
        ['key' => 'mail_encryption', 'value' => 'tls', 'type' => SettingType::String, 'group' => SettingGroup::Mail, 'encrypted' => false, 'description' => 'SMTP encryption (tls, ssl, none).'],
        ['key' => 'mail_from_address', 'value' => null, 'type' => SettingType::String, 'group' => SettingGroup::Mail, 'encrypted' => false, 'description' => 'Default sender address.'],
        ['key' => 'mail_from_name', 'value' => null, 'type' => SettingType::String, 'group' => SettingGroup::Mail, 'encrypted' => false, 'description' => 'Default sender name.'],
        // Alert switches
        ['key' => 'email_alerts_enabled', 'value' => '1', 'type' => SettingType::Boolean, 'group' => SettingGroup::Alerts, 'encrypted' => false, 'description' => 'Master switch for email alerts.'],
        ['key' => 'webhook_alerts_enabled', 'value' => '1', 'type' => SettingType::Boolean, 'group' => SettingGroup::Alerts, 'encrypted' => false, 'description' => 'Master switch for webhook alerts.'],
        ['key' => 'notify_on_service_failed', 'value' => '1', 'type' => SettingType::Boolean, 'group' => SettingGroup::Alerts, 'encrypted' => false, 'description' => 'Notify when a service fails.'],
        ['key' => 'notify_on_service_recovered', 'value' => '1', 'type' => SettingType::Boolean, 'group' => SettingGroup::Alerts, 'encrypted' => false, 'description' => 'Notify when a service recovers.'],
        ['key' => 'notify_on_incident_created', 'value' => '1', 'type' => SettingType::Boolean, 'group' => SettingGroup::Alerts, 'encrypted' => false, 'description' => 'Notify when an incident opens.'],
        ['key' => 'notify_on_incident_updated', 'value' => '1', 'type' => SettingType::Boolean, 'group' => SettingGroup::Alerts, 'encrypted' => false, 'description' => 'Notify on incident updates.'],
        ['key' => 'notify_on_incident_resolved', 'value' => '1', 'type' => SettingType::Boolean, 'group' => SettingGroup::Alerts, 'encrypted' => false, 'description' => 'Notify when an incident resolves.'],
        ['key' => 'notify_on_maintenance_started', 'value' => '1', 'type' => SettingType::Boolean, 'group' => SettingGroup::Alerts, 'encrypted' => false, 'description' => 'Notify when maintenance starts.'],
        ['key' => 'notify_on_maintenance_ended', 'value' => '0', 'type' => SettingType::Boolean, 'group' => SettingGroup::Alerts, 'encrypted' => false, 'description' => 'Notify when maintenance ends.'],
        // Webhook
        ['key' => 'webhook_default_url', 'value' => null, 'type' => SettingType::String, 'group' => SettingGroup::Webhook, 'encrypted' => false, 'description' => 'Fallback webhook endpoint.'],
        ['key' => 'webhook_secret', 'value' => null, 'type' => SettingType::String, 'group' => SettingGroup::Webhook, 'encrypted' => true, 'description' => 'Signing secret sent as X-Status-Signature (encrypted).'],
        ['key' => 'webhook_timeout', 'value' => '10', 'type' => SettingType::Integer, 'group' => SettingGroup::Webhook, 'encrypted' => false, 'description' => 'Webhook request timeout in seconds.'],
        // Retention
        ['key' => 'raw_checks_retention_days', 'value' => '60', 'type' => SettingType::Integer, 'group' => SettingGroup::Retention, 'encrypted' => false, 'description' => 'Days to keep raw check rows.'],
        ['key' => 'daily_stats_retention_days', 'value' => '540', 'type' => SettingType::Integer, 'group' => SettingGroup::Retention, 'encrypted' => false, 'description' => 'Days to keep daily statistics.'],
        ['key' => 'audit_retention_days', 'value' => '365', 'type' => SettingType::Integer, 'group' => SettingGroup::Retention, 'encrypted' => false, 'description' => 'Days to keep audit logs.'],
    ];

    public function run(): void
    {
        foreach (self::DEFAULTS as $default) {
            StatusSetting::updateOrCreate(
                ['key' => $default['key']],
                [
                    'value' => $default['encrypted'] && $default['value'] !== null
                        ? encrypt($default['value'])
                        : $default['value'],
                    'type' => $default['type'],
                    'group' => $default['group'],
                    'is_encrypted' => $default['encrypted'],
                    'description' => $default['description'],
                ],
            );

            Cache::forget('status-setting-v1:'.$default['key']);
        }
    }
}
