<?php

namespace Database\Factories;

use App\Models\PosToken;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PosToken>
 */
class PosTokenFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $plain = 'pos_'.Str::random(40);

        return [
            'profile_id' => Profile::factory(),
            'name' => fake()->words(2, true),
            'token_hash' => hash('sha256', $plain),
            'token_prefix' => substr($plain, 0, 12),
            'last_used_at' => null,
        ];
    }
}
