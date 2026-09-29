<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Root serves the public status home directly (subdomain-ready, no redirect).
     */
    public function test_the_application_returns_a_successful_response(): void
    {
        $this->get('/')->assertOk();

        $this->get('/status')->assertOk();
    }
}
