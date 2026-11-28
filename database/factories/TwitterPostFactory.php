<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\TwitterPost;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TwitterPost>
 */
class TwitterPostFactory extends Factory
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
            'body' => fake()->sentence(),
            'status' => 'draft',
            'scheduled_at' => null,
            'published_at' => null,
            'external_id' => null,
            'error' => null,
            'meta' => null,
        ];
    }

    /**
     * Mark the post as scheduled.
     */
    public function scheduled(?string $at = null): static
    {
        return $this->state(fn (): array => [
            'status' => 'scheduled',
            'scheduled_at' => $at !== null ? now()->parse($at) : now()->addHour(),
        ]);
    }
}
