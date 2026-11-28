<?php

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = fake()->randomElement(['free', 'pro', 'premium']);

        $prices = ['free' => '0', 'pro' => '9.99', 'premium' => '19.99'];

        $features = [
            'free' => ['basic links', 'click tracking'],
            'pro' => ['custom domain', 'analytics', 'priority support'],
            'premium' => ['unlimited links', 'advanced analytics', 'custom branding', 'api access'],
        ];

        return [
            'name' => ucfirst($type),
            'plan_type' => $type,
            'tool' => null,
            'price' => $prices[$type],
            'currency' => 'USD',
            'features' => $features[$type],
            'is_active' => true,
            'has_custom_domain' => $type !== 'free',
            'has_analytics' => $type !== 'free',
            'has_white_label' => $type !== 'free',
            'position' => array_search($type, ['free', 'pro', 'premium'], true) ?: 0,
            'max_restaurants' => $type === 'free' ? 1 : null,
            'duration_days' => $type === 'free' ? null : 30,
        ];
    }

    /**
     * Sell one automation tool on its own.
     */
    public function tool(string $tool): static
    {
        return $this->state(fn (): array => [
            'name' => Plan::TOOLS[$tool] ?? ucfirst($tool),
            'plan_type' => 'tool',
            'tool' => $tool,
            'price' => '14.99',
            'has_custom_domain' => false,
            'has_analytics' => false,
            'has_white_label' => false,
            'max_restaurants' => null,
            'duration_days' => 30,
        ]);
    }

    /**
     * Sell every automation tool as one bundle.
     */
    public function bundle(): static
    {
        return $this->state(fn (): array => [
            'name' => 'All tools bundle',
            'plan_type' => 'bundle',
            'tool' => null,
            'price' => '39.99',
            'has_custom_domain' => false,
            'has_analytics' => false,
            'has_white_label' => false,
            'max_restaurants' => null,
            'duration_days' => 30,
        ]);
    }
}
