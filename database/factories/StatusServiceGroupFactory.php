<?php

namespace Database\Factories;

use App\Models\Status\StatusServiceGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StatusServiceGroup>
 */
class StatusServiceGroupFactory extends Factory
{
    protected $model = StatusServiceGroup::class;

    public function definition(): array
    {
        return [
            'name' => $name = ucfirst($this->faker->unique()->word()).' Services',
            'slug' => str($name)->slug().'-'.$this->faker->unique()->randomNumber(5),
            'description' => $this->faker->sentence(),
            'sort_order' => $this->faker->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
