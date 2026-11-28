<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\ServiceRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ServiceRequest>
 */
class ServiceRequestFactory extends Factory
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
            'table_number' => (string) fake()->numberBetween(1, 20),
            'type' => fake()->randomElement(array_keys(ServiceRequest::TYPES)),
            'status' => ServiceRequest::STATUS_PENDING,
        ];
    }

    /**
     * Indicate that the request has been handled.
     */
    public function done(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => ServiceRequest::STATUS_DONE,
        ]);
    }
}
