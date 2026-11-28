<?php

namespace Database\Factories;

use App\Models\Link;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Link>
 */
class LinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'title' => fake()->words(3, true),
            'url' => fake()->url(),
            'icon' => null,
            'is_active' => true,
            'clicks' => 0,
            'position' => 0,
            'starts_at' => null,
            'ends_at' => null,
        ];
    }

    /**
     * Indicate that the link is hidden from visitors.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
