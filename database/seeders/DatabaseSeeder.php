<?php

namespace Database\Seeders;

use App\Models\Link;
use App\Models\LinkClick;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'is_admin' => true,
        ]);

        $profile = Profile::factory()->for($user)->create([
            'username' => 'testuser',
            'display_name' => 'Test User',
            'bio' => 'Designer and developer sharing everything in one place.',
            'theme' => 'indigo',
            'is_published' => true,
        ]);

        $links = [
            ['title' => 'My portfolio', 'url' => 'https://github.com/laravel/laravel', 'icon' => 'code', 'position' => 0, 'clicks' => 14],
            ['title' => 'Latest article', 'url' => 'https://example.com/blog', 'icon' => 'book', 'position' => 1, 'clicks' => 8],
            ['title' => 'Get in touch', 'url' => 'https://example.com/contact', 'icon' => 'mail', 'position' => 2, 'clicks' => 3],
        ];

        foreach ($links as $attributes) {
            $clickCount = $attributes['clicks'];

            $link = Link::factory()->for($profile)->create($attributes);

            $link->forceFill(['clicks' => $clickCount])->save();

            foreach (range(1, min($clickCount, 6)) as $offset) {
                LinkClick::factory()->for($link)->create([
                    'clicked_at' => now()->subDays($offset % 6),
                    'referrer' => $offset % 2 === 0 ? 'https://www.google.com/' : null,
                ]);
            }
        }
    }
}
