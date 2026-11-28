<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\WhatsAppMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppMessage>
 */
class WhatsAppMessageFactory extends Factory
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
            'contact_phone' => fake()->numerify('1555######'),
            'contact_name' => fake()->name(),
            'direction' => 'outbound',
            'message_type' => 'text',
            'body' => fake()->sentence(),
            'template_name' => null,
            'status' => 'sent',
            'external_id' => fake()->uuid(),
            'error' => null,
            'meta' => null,
        ];
    }
}
