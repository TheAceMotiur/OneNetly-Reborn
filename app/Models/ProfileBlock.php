<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $type
 * @property string|null $content
 * @property string|null $media_url
 * @property int $position
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['type', 'content', 'media_url', 'position', 'is_active'])]
class ProfileBlock extends Model
{
    public const TYPE_HEADING = 'heading';

    public const TYPE_TEXT = 'text';

    public const TYPE_IMAGE = 'image';

    public const TYPE_VIDEO = 'video';

    public const TYPE_DIVIDER = 'divider';

    /**
     * The block types a page can contain.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        self::TYPE_HEADING => 'Heading',
        self::TYPE_TEXT => 'Text',
        self::TYPE_IMAGE => 'Image',
        self::TYPE_VIDEO => 'Video',
        self::TYPE_DIVIDER => 'Divider',
    ];

    /**
     * The bio page the block belongs to.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * The embeddable player URL for a video block, when the provider is supported.
     */
    public function embedUrl(): ?string
    {
        if ($this->type !== self::TYPE_VIDEO || blank($this->media_url)) {
            return null;
        }

        $url = (string) $this->media_url;
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $host = preg_replace('/^www\./', '', $host) ?? $host;

        if ($host === 'youtu.be') {
            $id = trim((string) parse_url($url, PHP_URL_PATH), '/');

            return $id !== '' ? 'https://www.youtube.com/embed/'.$id : null;
        }

        if (in_array($host, ['youtube.com', 'm.youtube.com'], true)) {
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

            if (isset($query['v']) && is_string($query['v'])) {
                return 'https://www.youtube.com/embed/'.$query['v'];
            }

            if (preg_match('#^/(?:embed|shorts)/([A-Za-z0-9_-]+)#', (string) parse_url($url, PHP_URL_PATH), $matches)) {
                return 'https://www.youtube.com/embed/'.$matches[1];
            }

            return null;
        }

        if ($host === 'vimeo.com') {
            if (preg_match('#^/(\d+)#', (string) parse_url($url, PHP_URL_PATH), $matches)) {
                return 'https://player.vimeo.com/video/'.$matches[1];
            }

            return null;
        }

        return null;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
