<?php

namespace Tests\Feature\Status;

use App\Models\Status\StatusSetting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\StatusSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SettingsSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed([RolesAndPermissionsSeeder::class, StatusSettingSeeder::class]);

        $user = User::factory()->create();
        $user->assignRole('super-admin');

        return $user;
    }

    public function test_uploaded_svg_is_sanitized_before_storage(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script><a href="javascript:alert(1)"><rect width="1" height="1"/></a></svg>';

        $this->actingAs($admin)->put('/admin/status/settings', [
            'branding' => ['logo_path' => UploadedFile::fake()->createWithContent('logo.svg', $svg)],
        ])->assertRedirect();

        $path = StatusSetting::get('logo_path');

        $this->assertNotNull($path);
        $stored = Storage::disk('public')->get($path);
        $this->assertStringNotContainsString('script', $stored);
        $this->assertStringNotContainsString('javascript', $stored);
    }

    public function test_rejected_svg_keeps_the_existing_logo(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        Storage::disk('public')->put('branding/old.png', 'x');
        StatusSetting::set('logo_path', 'branding/old.png');

        $xxe = '<?xml version="1.0"?><!DOCTYPE svg [<!ENTITY x SYSTEM "file:///etc/passwd">]><svg xmlns="http://www.w3.org/2000/svg"><text>&x;</text></svg>';

        $this->actingAs($admin)->put('/admin/status/settings', [
            'branding' => ['logo_path' => UploadedFile::fake()->createWithContent('logo.svg', $xxe)],
        ])->assertSessionHasErrors('branding.logo_path');

        $this->assertSame('branding/old.png', StatusSetting::get('logo_path'));
        Storage::disk('public')->assertExists('branding/old.png');
    }

    public function test_connect_timeout_default_may_not_exceed_timeout_default(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put('/admin/status/settings', [
            'settings' => ['default_timeout' => 5, 'default_connect_timeout' => 30],
        ])->assertSessionHasErrors('settings.default_connect_timeout');
    }

    public function test_secret_can_be_cleared_explicitly(): void
    {
        $admin = $this->admin();
        StatusSetting::set('mail_password', 'hunter2');

        $this->actingAs($admin)->put('/admin/status/settings', [
            'settings' => ['mail_password' => ''],
        ])->assertRedirect();
        $this->assertSame('hunter2', StatusSetting::get('mail_password'));

        $this->actingAs($admin)->put('/admin/status/settings', [
            'settings' => ['mail_password' => ''],
            'clear_secrets' => ['mail_password' => '1'],
        ])->assertRedirect();
        $this->assertNull(StatusSetting::get('mail_password'));
    }

    public function test_webhook_to_private_address_is_blocked(): void
    {
        Http::fake();
        $admin = $this->admin();

        $this->actingAs($admin)->post('/admin/status/settings/test-webhook', [
            'url' => 'http://169.254.169.254/latest',
        ])->assertRedirect()->assertSessionHas('error');

        Http::assertNothingSent();
    }
}
