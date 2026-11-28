<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\Review;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'name' => fake()->firstName(),
            'rating' => fake()->numberBetween(3, 5),
            'comment' => fake()->sentence(12),
            'is_approved' => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => ['is_approved' => true]);
    }
}
