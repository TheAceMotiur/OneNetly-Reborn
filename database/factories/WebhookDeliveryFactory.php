<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
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
            'order_id' => null,
            'event' => 'order.created',
            'url' => fake()->url(),
            'response_status' => 200,
            'success' => true,
            'error' => null,
            'attempts' => 1,
        ];
    }

    /**
     * Indicate that the delivery failed.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'response_status' => 500,
            'success' => false,
            'error' => 'HTTP 500',
            'attempts' => 2,
        ]);
    }
}
