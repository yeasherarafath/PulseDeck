<?php

namespace Tests\Feature\Status;

use App\Models\Status\StatusHeaderPreset;
use App\Models\Status\StatusHeaderTemplate;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Database\Seeders\StatusSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Header presets/templates CRUD, operations buttons, and app version display.
 */
class HeaderManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolesAndPermissionsSeeder::class, StatusSettingSeeder::class]);

        $this->admin = User::factory()->create();
        $this->admin->assignRole('super-admin');
    }

    private function presetData(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Accept',
            'header_name' => 'Accept',
            'description' => 'Media types the client accepts.',
            'category' => 'common',
            'input_type' => 'select',
            'options_text' => "application/json\ntext/html",
            'is_sensitive' => '0',
            'is_active' => '1',
            'sort_order' => 0,
        ];
    }

    public function test_preset_crud_lifecycle(): void
    {
        $this->actingAs($this->admin)->get('/admin/status/header-presets')->assertOk();

        $this->actingAs($this->admin)->post('/admin/status/header-presets', $this->presetData())
            ->assertSessionHasNoErrors()->assertRedirect();

        $preset = StatusHeaderPreset::where('header_name', 'Accept')->firstOrFail();
        $this->assertSame(['application/json', 'text/html'], $preset->options);
        $this->assertSame('common', $preset->category->value);

        $this->actingAs($this->admin)->put("/admin/status/header-presets/{$preset->id}", $this->presetData([
            'name' => 'Accept Updated',
            'options_text' => "application/json\n*/*\napplication/json\n",
        ]))->assertSessionHasNoErrors();

        $this->assertSame(['application/json', '*/*'], $preset->fresh()->options);

        $this->actingAs($this->admin)->delete("/admin/status/header-presets/{$preset->id}")->assertRedirect();
        $this->assertNull(StatusHeaderPreset::find($preset->id));
    }

    public function test_preset_validation_rejects_bad_input(): void
    {
        $this->actingAs($this->admin)->post('/admin/status/header-presets', $this->presetData(['header_name' => '']))
            ->assertSessionHasErrors('header_name');

        $this->actingAs($this->admin)->post('/admin/status/header-presets', $this->presetData(['category' => 'nope']))
            ->assertSessionHasErrors('category');

        $this->actingAs($this->admin)->post('/admin/status/header-presets', $this->presetData(['input_type' => 'radio']))
            ->assertSessionHasErrors('input_type');

        StatusHeaderPreset::create([
            'name' => 'Accept', 'header_name' => 'Accept', 'category' => 'common',
            'input_type' => 'select', 'options' => ['application/json'],
            'is_sensitive' => false, 'is_active' => true, 'sort_order' => 0,
        ]);

        $this->actingAs($this->admin)->post('/admin/status/header-presets', $this->presetData())
            ->assertSessionHasErrors('header_name');
    }

    public function test_template_crud_lifecycle_with_encrypted_headers(): void
    {
        $this->actingAs($this->admin)->get('/admin/status/header-templates')->assertOk();

        $this->actingAs($this->admin)->post('/admin/status/header-templates', [
            'name' => 'JSON API',
            'description' => 'Standard JSON API request.',
            'headers' => [
                ['name' => 'Accept', 'value' => 'application/json'],
                ['name' => 'X-Secret', 'value' => 's3cret'],
                ['name' => '', 'value' => 'dropped'],
            ],
            'is_active' => '1',
        ])->assertSessionHasNoErrors()->assertRedirect();

        $template = StatusHeaderTemplate::where('name', 'JSON API')->firstOrFail();
        $this->assertSame(['Accept' => 'application/json', 'X-Secret' => 's3cret'], $template->headers);

        // Encrypted at rest: raw column must not contain plaintext.
        $raw = (string) $template->getAttributes()['headers'];
        $this->assertStringNotContainsString('s3cret', $raw);

        $this->actingAs($this->admin)->put("/admin/status/header-templates/{$template->id}", [
            'name' => 'JSON API',
            'description' => 'Updated.',
            'headers' => [['name' => 'Accept', 'value' => 'text/html']],
        ])->assertSessionHasNoErrors();

        $this->assertSame(['Accept' => 'text/html'], $template->fresh()->headers);

        $this->actingAs($this->admin)->delete("/admin/status/header-templates/{$template->id}")->assertRedirect();
        $this->assertNull(StatusHeaderTemplate::find($template->id));
    }

    public function test_viewers_cannot_manage_headers_or_operations(): void
    {
        $viewer = User::factory()->create();
        $viewer->assignRole('status-viewer');

        $this->actingAs($viewer)->get('/admin/status/header-presets')->assertOk();
        $this->actingAs($viewer)->post('/admin/status/header-presets', $this->presetData())->assertForbidden();
        $this->actingAs($viewer)->get('/admin/status/header-templates')->assertOk();
        $this->actingAs($viewer)->post('/admin/status/header-templates', ['name' => 'X'])->assertForbidden();
        $this->actingAs($viewer)->post('/admin/status/settings/clear-cache')->assertForbidden();
        $this->actingAs($viewer)->post('/admin/status/settings/queue-work')->assertForbidden();
    }

    public function test_clear_cache_button_runs_optimize_clear(): void
    {
        $this->actingAs($this->admin)->post('/admin/status/settings/clear-cache')
            ->assertRedirect()->assertSessionHas('status');
    }

    public function test_queue_work_button_drains_queue(): void
    {
        $this->actingAs($this->admin)->post('/admin/status/settings/queue-work')
            ->assertRedirect()->assertSessionHas('status');
    }

    public function test_app_version_is_configured_and_displayed(): void
    {
        $this->assertNotEmpty(config('app.version'));

        $this->actingAs($this->admin)->get('/admin/status/settings')
            ->assertOk()->assertSee('v'.config('app.version'), false);
    }
}
