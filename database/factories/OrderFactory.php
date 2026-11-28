<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subtotal = fake()->numberBetween(500, 9000);

        return [
            'profile_id' => Profile::factory(),
            'reference' => 'ORD-'.Str::upper(Str::random(6)),
            'customer_name' => fake()->name(),
            'customer_phone' => fake()->phoneNumber(),
            'customer_email' => fake()->safeEmail(),
            'order_type' => fake()->randomElement(array_keys(Order::TYPES)),
            'table_number' => (string) fake()->numberBetween(1, 20),
            'notes' => null,
            'subtotal_cents' => $subtotal,
            'total_cents' => $subtotal,
            'currency' => 'USD',
            'status' => OrderStatus::New,
        ];
    }
}
