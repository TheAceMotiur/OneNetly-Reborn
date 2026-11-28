<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\ProfileInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProfileInvitation>
 */
class ProfileInvitationFactory extends Factory
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
            'email' => fake()->unique()->safeEmail(),
            'role' => fake()->randomElement(['staff', 'manager', 'editor', 'viewer']),
            'token' => Str::random(48),
            'invited_by' => User::factory(),
            'accepted_at' => null,
        ];
    }
}
