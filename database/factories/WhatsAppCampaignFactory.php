<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\WhatsAppCampaign;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppCampaign>
 */
class WhatsAppCampaignFactory extends Factory
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
            'name' => fake()->words(3, true),
            'message' => fake()->sentence(),
            'template_name' => null,
            'language' => 'en_US',
            'audience' => 'all',
            'status' => 'draft',
            'sent_count' => 0,
            'failed_count' => 0,
            'sent_at' => null,
        ];
    }
}
