<?php

namespace Database\Factories;

use App\Models\InstagramAutomation;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstagramAutomation>
 */
class InstagramAutomationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->randomElement(array_keys(InstagramAutomation::DEFAULTS));

        return [
            'profile_id' => Profile::factory(),
            'key' => $key,
            'name' => null,
            'keywords' => null,
            'message' => InstagramAutomation::DEFAULTS[$key]['message'],
            'is_enabled' => true,
        ];
    }

    /**
     * Mark the automation as a keyword reply.
     */
    public function keyword(): static
    {
        return $this->state(fn (): array => [
            'key' => 'keyword',
            'name' => 'Menu questions',
            'keywords' => 'menu, food',
            'message' => 'Here is our menu: {account}',
        ]);
    }
}
