<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\TwitterMention;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TwitterMention>
 */
class TwitterMentionFactory extends Factory
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
            'tweet_id' => fake()->unique()->numerify('####################'),
            'author_id' => fake()->numerify('####################'),
            'author_username' => fake()->userName(),
            'body' => fake()->sentence(),
            'replied' => false,
            'reply_tweet_id' => null,
            'meta' => null,
        ];
    }
}
