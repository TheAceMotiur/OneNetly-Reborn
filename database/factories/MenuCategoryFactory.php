<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuCategory>
 */
class MenuCategoryFactory extends Factory
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
            'name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'daypart' => 'all_day',
            'position' => 0,
            'is_active' => true,
        ];
    }

    /**
     * Indicate that the section is hidden from visitors.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
