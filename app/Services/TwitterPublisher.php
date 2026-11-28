<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\TwitterPost;

class TwitterPublisher
{
    public function __construct(private TwitterClient $client) {}

    /**
     * Publish one stored post and record the result.
     */
    public function publish(TwitterPost $post): bool
    {
        if ($post->status === 'published') {
            return true;
        }

        $profile = $post->profile;

        if (! $profile instanceof Profile) {
            $post->update([
                'status' => 'failed',
                'error' => __('The page for this post no longer exists.'),
            ]);

            return false;
        }

        $result = $this->client->createTweet($profile, $post->body);

        if ($result['ok']) {
            $post->update([
                'status' => 'published',
                'published_at' => now(),
                'external_id' => $result['id'],
                'error' => null,
                'meta' => $result['data'],
            ]);

            return true;
        }

        $post->update([
            'status' => 'failed',
            'error' => $result['error'],
            'meta' => $result['data'],
        ]);

        return false;
    }
}
