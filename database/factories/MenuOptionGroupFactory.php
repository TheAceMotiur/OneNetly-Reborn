<?php

namespace Database\Factories;

use App\Models\MenuOptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuOptionGroup>
 */
class MenuOptionGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Size', 'Milk', 'Extras', 'Spice level']),
            'is_required' => false,
            'allow_multiple' => false,
            'position' => 0,
        ];
    }

    /**
     * A group the guest must choose from.
     */
    public function required(): static
    {
        return $this->state(fn (): array => ['is_required' => true]);
    }

    /**
     * A group that accepts more than one choice.
     */
    public function multiple(): static
    {
        return $this->state(fn (): array => ['allow_multiple' => true]);
    }
}
