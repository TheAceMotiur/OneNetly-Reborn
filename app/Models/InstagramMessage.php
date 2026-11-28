<?php

namespace App\Models;

use Database\Factories\InstagramMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $profile_id
 * @property string|null $igsid
 * @property string|null $contact_name
 * @property string $channel
 * @property string $direction
 * @property string|null $body
 * @property string $status
 * @property string|null $external_id
 * @property string|null $error
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'profile_id', 'igsid', 'contact_name', 'channel', 'direction', 'body',
    'status', 'external_id', 'error', 'meta',
])]
class InstagramMessage extends Model
{
    /** @use HasFactory<InstagramMessageFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'instagram_messages';

    /**
     * The page that owns this conversation.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Determine whether the message reached Instagram.
     */
    public function wasSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'read'], true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
