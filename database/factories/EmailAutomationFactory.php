<?php

namespace Database\Factories;

use App\Models\EmailAutomation;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmailAutomation>
 */
class EmailAutomationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->randomElement(array_keys(EmailAutomation::DEFAULTS));

        return [
            'profile_id' => Profile::factory(),
            'key' => $key,
            'is_enabled' => true,
            'subject' => EmailAutomation::DEFAULTS[$key]['subject'],
            'body' => EmailAutomation::DEFAULTS[$key]['body'],
        ];
    }
}
