<?php

namespace Tests\Feature\Status;

use App\Models\Status\StatusService;
use App\Models\Status\StatusSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicMetaTest extends TestCase
{
    use RefreshDatabase;

    private function putSetting(string $key, ?string $value, string $group = 'general'): void
    {
        StatusSetting::updateOrCreate(['key' => $key], [
            'value' => $value,
            'type' => 'string',
            'group' => $group,
            'is_encrypted' => false,
        ]);

        Cache::forget('status-setting-v1:'.$key);
    }

    public function test_index_has_standard_meta_with_defaults(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('<meta name="description"', false);
        $response->assertSee('<meta name="author"', false);
        $response->assertSee('<meta name="robots" content="index, follow"', false);
        $response->assertSee('rel="canonical"', false);
        $response->assertSee('<meta property="og:title"', false);
        $response->assertSee('<meta property="og:description"', false);
        $response->assertSee('<meta property="og:type" content="website"', false);
        $response->assertSee('<meta property="og:url"', false);
        $response->assertSee('<meta property="og:site_name"', false);
        $response->assertSee('<meta property="og:locale"', false);
        $response->assertSee('<meta name="twitter:card" content="summary"', false);
        $response->assertSee('<meta name="twitter:title"', false);
        $response->assertSee('<meta name="twitter:description"', false);
        // No image configured: no image tags, plain summary card.
        $response->assertDontSee('og:image', false);
        $response->assertDontSee('twitter:image', false);
        $response->assertDontSee('<meta name="keywords"', false);
    }

    public function test_custom_settings_are_reflected(): void
    {
        $this->putSetting('app_name', 'Acme');
        $this->putSetting('app_description', 'Custom status description.');
        $this->putSetting('meta_keywords', 'status, uptime, acme');
        $this->putSetting('meta_robots', 'noindex, nofollow', 'public');
        $this->putSetting('og_image_path', 'https://example.com/og.png', 'branding');

        $response = $this->get('/')->assertOk();

        $response->assertSee('<title>Acme Status</title>', false);
        $response->assertSee('<meta name="description" content="Custom status description."', false);
        $response->assertSee('<meta name="author" content="Acme"', false);
        $response->assertSee('<meta name="keywords" content="status, uptime, acme"', false);
        $response->assertSee('<meta name="robots" content="noindex, nofollow"', false);
        $response->assertSee('<meta property="og:site_name" content="Acme"', false);
        $response->assertSee('<meta property="og:title" content="Acme Status"', false);
        $response->assertSee('<meta property="og:image" content="https://example.com/og.png"', false);
        $response->assertSee('<meta name="twitter:card" content="summary_large_image"', false);
        $response->assertSee('<meta name="twitter:image" content="https://example.com/og.png"', false);
    }

    public function test_og_image_falls_back_to_logo(): void
    {
        $this->putSetting('logo_path', 'https://example.com/logo.png', 'branding');

        $response = $this->get('/')->assertOk();

        $response->assertSee('<meta property="og:image" content="https://example.com/logo.png"', false);
        $response->assertSee('<meta name="twitter:card" content="summary_large_image"', false);
    }

    public function test_service_page_has_dynamic_meta(): void
    {
        $this->putSetting('app_name', 'Acme');

        $service = StatusService::factory()->create(['is_public' => true, 'name' => 'Website']);

        $response = $this->get("/status/services/{$service->slug}")->assertOk();

        $response->assertSee('<title>Website status | Acme</title>', false);
        $response->assertSee('<meta property="og:title" content="Website status | Acme"', false);
        $response->assertSee('<meta name="twitter:title" content="Website status | Acme"', false);
    }

    public function test_meta_values_are_escaped(): void
    {
        $evil = '"><script>alert(1)</script>';
        $this->putSetting('app_name', $evil);

        $response = $this->get('/')->assertOk();

        $response->assertDontSee($evil, false);
        $response->assertSee(e($evil.' Status'), false);
    }
}
