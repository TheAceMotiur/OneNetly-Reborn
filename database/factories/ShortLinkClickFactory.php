<?php

namespace Database\Factories;

use App\Models\ShortLink;
use App\Models\ShortLinkClick;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShortLinkClick>
 */
class ShortLinkClickFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'short_link_id' => ShortLink::factory(),
            'clicked_at' => now(),
            'visitor_hash' => sha1((string) Str::uuid()),
            'ip_hash' => sha1((string) fake()->ipv4()),
            'referrer' => fake()->optional()->url(),
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0 Safari/537.36',
            'device' => 'desktop',
            'country' => fake()->optional()->countryCode(),
        ];
    }
}
