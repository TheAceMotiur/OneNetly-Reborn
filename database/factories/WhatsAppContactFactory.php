<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\WhatsAppContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppContact>
 */
class WhatsAppContactFactory extends Factory
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
            'phone' => fake()->unique()->numerify('1555######'),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'source' => 'manual',
            'opted_in' => true,
            'last_messaged_at' => null,
        ];
    }
}
