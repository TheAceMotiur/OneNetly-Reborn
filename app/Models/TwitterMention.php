<?php

namespace App\Models;

use Database\Factories\TwitterMentionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $tweet_id
 * @property string|null $author_id
 * @property string|null $author_username
 * @property string|null $body
 * @property bool $replied
 * @property string|null $reply_tweet_id
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'profile_id', 'tweet_id', 'author_id', 'author_username', 'body',
    'replied', 'reply_tweet_id', 'meta',
])]
class TwitterMention extends Model
{
    /** @use HasFactory<TwitterMentionFactory> */
    use HasFactory;

    /**
     * The page that received the mention.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'replied' => 'boolean',
            'meta' => 'array',
        ];
    }
}
