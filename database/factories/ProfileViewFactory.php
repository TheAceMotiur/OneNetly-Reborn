<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\ProfileView;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfileView>
 */
class ProfileViewFactory extends Factory
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
            'viewed_at' => now(),
            'referrer' => fake()->optional()->url(),
            'user_agent' => fake()->userAgent(),
        ];
    }
}
