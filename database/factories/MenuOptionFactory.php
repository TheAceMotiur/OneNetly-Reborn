<?php

namespace Database\Factories;

use App\Models\MenuOption;
use App\Models\MenuOptionGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MenuOption>
 */
class MenuOptionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'menu_option_group_id' => MenuOptionGroup::factory(),
            'name' => fake()->randomElement(['Small', 'Large', 'Oat milk', 'Extra cheese']),
            'price' => fake()->randomElement(['0', '0.50', '1.00', '2.50']),
            'is_available' => true,
            'position' => 0,
        ];
    }
}
