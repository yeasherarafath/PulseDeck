<?php

namespace Database\Factories;

use App\Models\Status\StatusService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusService>
 */
class StatusServiceFactory extends Factory
{
    protected $model = StatusService::class;

    public function definition(): array
    {
        return [
            'name' => $name = ucfirst($this->faker->unique()->word()).' Service',
            'slug' => str($name)->slug().'-'.$this->faker->unique()->randomNumber(5),
            'description' => $this->faker->sentence(),
            'url' => 'https://example.com/'.$this->faker->slug(),
            'method' => 'GET',
            'check_interval' => 300,
            'timeout' => 15,
            'connect_timeout' => 5,
            'follow_redirects' => true,
            'max_redirects' => 5,
            'verify_ssl' => true,
            'http_version' => 'auto',
            'expected_status_codes' => [200],
            'current_status' => 'unknown',
            'failure_threshold' => 3,
            'recovery_threshold' => 2,
            'auto_create_incidents' => true,
            'auto_resolve_incidents' => true,
            'notify_on_failure' => true,
            'notify_on_recovery' => true,
            'is_active' => true,
            'is_public' => true,
            'sort_order' => 0,
        ];
    }
}
