<?php

namespace Tests\Feature\Status;

use App\Enums\Status\NotificationEvent;
use App\Jobs\Status\SendStatusNotification;
use App\Mail\StatusAlertMail;
use App\Models\Status\StatusNotificationChannel;
use App\Models\Status\StatusNotificationDelivery;
use App\Models\Status\StatusNotificationRule;
use App\Models\Status\StatusService;
use App\Models\Status\StatusSetting;
use App\Models\Status\StatusSubscriber;
use App\Services\Status\NotificationManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    use RefreshDatabase;

    /** N-T6: trusted recipients mailed; per-recipient tokens gate footers. */
    public function test_mail_recipients_resolve_correctly(): void
    {
        Mail::fake();

        $channel = StatusNotificationChannel::create([
            'name' => 'Ops mail',
            'type' => 'mail',
            'config' => ['to' => ['ops@example.com']],
            'is_active' => true,
        ]);

        SendStatusNotification::dispatchSync(
            $channel->id,
            'service.failed',
            null,
            'Subject',
            ['line'],
            null,
            ['ops@example.com', 'verified@example.com'],
            ['verified@example.com' => 'token-verified'],
        );

        Mail::assertSent(StatusAlertMail::class, fn ($mail) => $mail->hasTo('ops@example.com'));
        Mail::assertSent(StatusAlertMail::class, fn ($mail) => $mail->hasTo('verified@example.com'));

        $this->assertEquals(1, StatusNotificationDelivery::where('status', 'sent')->count());
    }

    /** N-T6b: unverified subscribers never reach a dispatched job. */
    public function test_unverified_subscribers_excluded_by_manager(): void
    {
        Queue::fake();

        foreach (['mail_enabled' => '1', 'subscriptions_enabled' => '1'] as $key => $value) {
            StatusSetting::updateOrCreate(['key' => $key], [
                'value' => $value, 'type' => 'boolean', 'group' => 'mail', 'is_encrypted' => false,
            ]);
        }

        StatusSetting::updateOrCreate(['key' => 'mail_mailer'], [
            'value' => 'log', 'type' => 'string', 'group' => 'mail', 'is_encrypted' => false,
        ]);

        Cache::flush();

        $channel = StatusNotificationChannel::create([
            'name' => 'Ops mail',
            'type' => 'mail',
            'config' => ['to' => []],
            'is_active' => true,
        ]);

        StatusNotificationRule::create([
            'channel_id' => $channel->id,
            'service_id' => null,
            'event' => NotificationEvent::ServiceFailed->value,
            'is_active' => true,
        ]);

        StatusSubscriber::create([
            'email' => 'pending@example.com',
            'verification_token' => 'token-pending',
            'unsubscribe_token' => 'token-pending-unsub',
            'is_active' => true,
        ]);

        app(NotificationManager::class)->notify(NotificationEvent::ServiceFailed, null, 'Subject');

        Queue::assertNotPushed(SendStatusNotification::class);
    }

    /** N-T7: webhook payload shape + signature behavior. */
    public function test_webhook_payload_and_signature(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $service = StatusService::factory()->create(['url' => 'https://example.com/']);

        $channel = StatusNotificationChannel::create([
            'name' => 'Hook',
            'type' => 'webhook',
            'config' => ['url' => 'https://hooks.example.com/x', 'secret' => 's3cret'],
            'is_active' => true,
        ]);

        SendStatusNotification::dispatchSync($channel->id, 'service.failed', $service->id, 'Subject', ['line'], null, []);

        Http::assertSent(function ($request) use ($service) {
            if ($request->url() !== 'https://hooks.example.com/x') {
                return false;
            }

            $signature = $request->header('X-Status-Signature')[0] ?? null;

            if (! $signature) {
                return false;
            }

            $expected = hash_hmac('sha256', json_encode([
                'event' => 'service.failed',
                'subject' => 'Subject',
                'lines' => ['line'],
                'url' => null,
                'service_id' => $service->id,
                'service_name' => $service->name,
                'service_slug' => $service->slug,
                'sent_at' => $request['sent_at'],
            ]), 's3cret');

            return hash_equals($expected, $signature);
        });

        $this->assertEquals(1, StatusNotificationDelivery::where('status', 'sent')->count());
    }

    /** N-T12: failed sends record a failed delivery without secrets. */
    public function test_failed_send_records_delivery_without_secrets(): void
    {
        Http::fake(['*' => Http::response('bad', 500)]);

        $channel = StatusNotificationChannel::create([
            'name' => 'Hook',
            'type' => 'webhook',
            'config' => ['url' => 'https://hooks.example.com/x', 'secret' => 's3cret'],
            'is_active' => true,
        ]);

        SendStatusNotification::dispatchSync($channel->id, 'service.failed', null, 'Subject', [], null, []);

        $delivery = StatusNotificationDelivery::first();

        $this->assertNotNull($delivery);
        $this->assertEquals('failed', $delivery->status);
        $this->assertStringNotContainsString('s3cret', (string) $delivery->error);
    }
}
