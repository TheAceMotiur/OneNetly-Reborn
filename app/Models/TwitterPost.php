<?php

namespace App\Models;

use Database\Factories\TwitterPostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $body
 * @property string $status
 * @property Carbon|null $scheduled_at
 * @property Carbon|null $published_at
 * @property string|null $external_id
 * @property string|null $error
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'profile_id', 'body', 'status', 'scheduled_at', 'published_at',
    'external_id', 'error', 'meta',
])]
class TwitterPost extends Model
{
    /** @use HasFactory<TwitterPostFactory> */
    use HasFactory;

    /**
     * The page that owns this post.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Determine whether the post is waiting to be published.
     */
    public function isScheduled(): bool
    {
        return $this->status === 'scheduled';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'published_at' => 'datetime',
            'meta' => 'array',
        ];
    }
}
