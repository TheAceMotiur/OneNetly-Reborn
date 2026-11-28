<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\Widget;
use App\Support\Widgets\WidgetCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Widget>
 */
class WidgetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(array_keys(WidgetCatalog::all()));
        $definition = WidgetCatalog::definition($type);

        return [
            'user_id' => User::factory(),
            'type' => $type,
            'name' => (string) ($definition['name'] ?? $type),
            'config' => WidgetCatalog::defaults($type),
        ];
    }

    /**
     * Indicate the widget type.
     */
    public function ofType(string $type): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => $type,
            'name' => (string) (WidgetCatalog::definition($type)['name'] ?? $type),
            'config' => WidgetCatalog::defaults($type),
        ]);
    }
}
