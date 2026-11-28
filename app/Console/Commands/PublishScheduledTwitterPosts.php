<?php

namespace App\Console\Commands;

use App\Models\TwitterPost;
use App\Services\TwitterPublisher;
use Illuminate\Console\Command;
use Throwable;

class PublishScheduledTwitterPosts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'twitter:publish-scheduled';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Publish Twitter posts whose scheduled time has arrived';

    /**
     * Execute the console command.
     */
    public function handle(TwitterPublisher $publisher): int
    {
        $posts = TwitterPost::query()
            ->where('status', 'scheduled')
            ->whereNotNull('scheduled_at')
            ->where('scheduled_at', '<=', now())
            ->with('profile')
            ->limit(50)
            ->get();

        $published = 0;

        foreach ($posts as $post) {
            try {
                if ($publisher->publish($post)) {
                    $published++;
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->error("Could not publish post #{$post->id}: {$exception->getMessage()}");
            }
        }

        $this->info("Published {$published} of {$posts->count()} due post(s).");

        return self::SUCCESS;
    }
}
