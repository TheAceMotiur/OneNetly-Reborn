<?php

namespace Database\Factories;

use App\Models\ShortLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShortLink>
 */
class ShortLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => fake()->words(3, true),
            'slug' => Str::lower(Str::random(8)),
            'destination' => fake()->url(),
            'mobile_destination' => null,
            'password' => null,
            'expires_at' => null,
            'max_clicks' => null,
            'clicks' => 0,
            'last_clicked_at' => null,
            'is_active' => true,
            'utm_source' => null,
            'utm_medium' => null,
            'utm_campaign' => null,
            'utm_term' => null,
            'utm_content' => null,
        ];
    }

    /**
     * Indicate that the link is paused.
     */
    public function paused(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the link has expired.
     */
    public function expired(): static
    {
        return $this->state(fn (array $attributes): array => [
            'expires_at' => now()->subHour(),
        ]);
    }

    /**
     * Indicate that the link asks visitors for a password.
     */
    public function passwordProtected(string $password = 'secret'): static
    {
        return $this->state(fn (array $attributes): array => [
            'password' => Hash::make($password),
        ]);
    }
}
