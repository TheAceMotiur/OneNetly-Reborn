<?php

namespace Database\Factories;

use App\Models\InstagramMessage;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstagramMessage>
 */
class InstagramMessageFactory extends Factory
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
            'igsid' => fake()->numerify('##############'),
            'contact_name' => fake()->userName(),
            'channel' => 'messenger',
            'direction' => 'inbound',
            'body' => fake()->sentence(),
            'status' => 'received',
            'external_id' => fake()->uuid(),
            'error' => null,
            'meta' => null,
        ];
    }
}
