<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
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
            'name' => fake()->name(),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->safeEmail(),
            'party_size' => fake()->numberBetween(1, 8),
            'reservation_date' => now()->addDay()->toDateString(),
            'reservation_time' => '19:00',
            'notes' => null,
            'status' => Reservation::STATUS_PENDING,
        ];
    }
}
