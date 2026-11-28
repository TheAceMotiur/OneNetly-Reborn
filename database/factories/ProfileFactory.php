<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
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
            'username' => Str::slug(fake()->unique()->userName()),
            'display_name' => fake()->name(),
            'bio' => fake()->sentence(12),
            'avatar_path' => null,
            'theme' => fake()->randomElement(Profile::THEMES),
            'template' => fake()->randomElement(Profile::TEMPLATES),
            'is_published' => true,
            'show_contact_form' => false,
        ];
    }

    /**
     * Indicate that the page is a restaurant rather than a biolink.
     */
    public function restaurant(): static
    {
        return $this->state(fn (array $attributes) => [
            'business_type' => Profile::RESTAURANT_TYPE,
        ]);
    }

    /**
     * Indicate that the bio page is hidden from visitors.
     */
    public function unpublished(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_published' => false,
        ]);
    }
}
