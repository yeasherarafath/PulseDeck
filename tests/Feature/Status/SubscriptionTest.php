<?php

namespace Tests\Feature\Status;

use App\Models\Status\StatusSubscriber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /** N-T8: subscribing twice is idempotent. */
    public function test_subscribe_is_idempotent(): void
    {
        $this->post('/status/subscribe', ['email' => 'fan@example.com'])->assertRedirect('/status');
        $this->post('/status/subscribe', ['email' => 'fan@example.com'])->assertRedirect('/status');

        $this->assertEquals(1, StatusSubscriber::where('email', 'fan@example.com')->count());
    }

    /** N-T9: verify consumes the verify token; persistent token unsubscribes. */
    public function test_verify_then_unsubscribe_with_persistent_token(): void
    {
        $this->post('/status/subscribe', ['email' => 'fan@example.com']);

        $subscriber = StatusSubscriber::where('email', 'fan@example.com')->first();
        $verifyToken = $subscriber->verification_token;
        $unsubscribeToken = $subscriber->unsubscribe_token;

        $this->assertNotNull($verifyToken);
        $this->assertNotNull($unsubscribeToken);

        $this->get("/status/verify/{$verifyToken}")->assertRedirect('/status');

        // One-time verify link is consumed.
        $this->get("/status/verify/{$verifyToken}")->assertNotFound();

        // Persistent token still unsubscribes exactly that row.
        $this->get("/status/unsubscribe/{$unsubscribeToken}")->assertRedirect('/status');

        $this->assertEquals(0, StatusSubscriber::where('email', 'fan@example.com')->count());
    }

    public function test_subscribe_requires_email(): void
    {
        $this->post('/status/subscribe', ['email' => 'not-an-email'])->assertSessionHasErrors('email');
    }
}
