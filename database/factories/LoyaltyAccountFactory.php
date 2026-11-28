<?php

namespace Database\Factories;

use App\Models\LoyaltyAccount;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoyaltyAccount>
 */
class LoyaltyAccountFactory extends Factory
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
            'phone' => '+1555'.fake()->numerify('#######'),
            'name' => fake()->name(),
            'points' => fake()->numberBetween(0, 500),
            'lifetime_points' => fake()->numberBetween(0, 1000),
            'visits' => fake()->numberBetween(0, 20),
            'last_order_at' => now(),
        ];
    }
}
