<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\SmsMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SmsMessage>
 */
class SmsMessageFactory extends Factory
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
            'recipient' => '+1555'.fake()->numerify('#######'),
            'body' => fake()->sentence(),
            'status' => 'queued',
            'sms_batch_id' => fake()->uuid(),
            'sent_at' => now(),
        ];
    }
}
