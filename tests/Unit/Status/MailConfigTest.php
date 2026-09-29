<?php

namespace Tests\Unit\Status;

use App\Models\Status\StatusSetting;
use App\Services\Status\StatusMailConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MailConfigTest extends TestCase
{
    use RefreshDatabase;

    /** N-T13: runtime mail config reflects settings. */
    public function test_apply_reads_settings(): void
    {
        StatusSetting::create([
            'key' => 'mail_mailer', 'value' => 'smtp', 'type' => 'string', 'group' => 'mail', 'is_encrypted' => false,
        ]);
        StatusSetting::create([
            'key' => 'mail_host', 'value' => 'mail.example.com', 'type' => 'string', 'group' => 'mail', 'is_encrypted' => false,
        ]);
        StatusSetting::create([
            'key' => 'mail_port', 'value' => '2525', 'type' => 'integer', 'group' => 'mail', 'is_encrypted' => false,
        ]);
        StatusSetting::create([
            'key' => 'mail_from_address', 'value' => 'status@example.com', 'type' => 'string', 'group' => 'mail', 'is_encrypted' => false,
        ]);

        StatusMailConfig::apply();

        $this->assertEquals('mail.example.com', config('mail.mailers.smtp.host'));
        $this->assertEquals(2525, config('mail.mailers.smtp.port'));
        $this->assertEquals('status@example.com', config('mail.from.address'));
    }

    public function test_is_configured_requires_enabled_and_host(): void
    {
        $this->assertFalse(StatusMailConfig::isConfigured());

        StatusSetting::create([
            'key' => 'mail_enabled', 'value' => '1', 'type' => 'boolean', 'group' => 'mail', 'is_encrypted' => false,
        ]);

        $this->assertFalse(StatusMailConfig::isConfigured());

        StatusSetting::create([
            'key' => 'mail_host', 'value' => 'mail.example.com', 'type' => 'string', 'group' => 'mail', 'is_encrypted' => false,
        ]);

        Cache::flush();

        $this->assertTrue(StatusMailConfig::isConfigured());
    }
}
