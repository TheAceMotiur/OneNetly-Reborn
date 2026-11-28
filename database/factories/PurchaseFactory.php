<?php

namespace Database\Factories;

use App\Enums\PurchaseStatus;
use App\Models\Plan;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Purchase>
 */
class PurchaseFactory extends Factory
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
            'plan_id' => Plan::factory(),
            'paypal_transaction_id' => strtoupper(fake()->bothify('PAY-########')),
            'status' => PurchaseStatus::Completed,
            'amount' => 9.99,
            'currency' => 'USD',
            'payer_email' => fake()->safeEmail(),
            'payment_date' => now(),
        ];
    }

    /**
     * Indicate that the purchase is still pending review.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PurchaseStatus::Pending,
            'payment_date' => null,
        ]);
    }
}
