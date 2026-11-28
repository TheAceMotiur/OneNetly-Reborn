<?php

namespace Database\Factories;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Resume>
 */
class ResumeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->jobTitle();

        return [
            'user_id' => User::factory(),
            'title' => $title.' resume',
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'template' => fake()->randomElement(array_keys(Resume::TEMPLATES)),
            'accent' => fake()->randomElement(array_keys(Resume::ACCENTS)),
            'font' => fake()->randomElement(array_keys(Resume::FONTS)),
            'content' => Resume::sampleContent(),
            'is_public' => false,
        ];
    }

    /**
     * Indicate that the resume is available through its share link.
     */
    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_public' => true,
        ]);
    }
}
