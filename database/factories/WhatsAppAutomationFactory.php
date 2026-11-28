<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\WhatsAppAutomation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsAppAutomation>
 */
class WhatsAppAutomationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->randomElement(array_keys(WhatsAppAutomation::DEFAULTS));

        return [
            'profile_id' => Profile::factory(),
            'key' => $key,
            'is_enabled' => true,
            'message' => WhatsAppAutomation::DEFAULTS[$key]['message'],
            'template_name' => null,
            'language' => 'en_US',
        ];
    }
}
