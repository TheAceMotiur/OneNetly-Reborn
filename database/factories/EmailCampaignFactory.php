<?php

namespace Database\Factories;

use App\Models\EmailCampaign;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailCampaign>
 */
class EmailCampaignFactory extends Factory
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
            'subject' => fake()->sentence(5),
            'body' => fake()->paragraph(),
            'status' => 'draft',
            'sent_count' => 0,
            'failed_count' => 0,
            'sent_at' => null,
        ];
    }
}
