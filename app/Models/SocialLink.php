<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $platform
 * @property string $url
 * @property int $position
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['platform', 'url', 'position'])]
class SocialLink extends Model
{
    /**
     * The social platforms a profile can link to, keyed by their label source.
     *
     * @var array<string, string>
     */
    public const PLATFORMS = [
        'instagram' => 'Instagram',
        'x' => 'X (Twitter)',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
        'facebook' => 'Facebook',
        'linkedin' => 'LinkedIn',
        'github' => 'GitHub',
        'threads' => 'Threads',
        'snapchat' => 'Snapchat',
        'pinterest' => 'Pinterest',
        'twitch' => 'Twitch',
        'spotify' => 'Spotify',
        'soundcloud' => 'SoundCloud',
        'discord' => 'Discord',
        'telegram' => 'Telegram',
        'whatsapp' => 'WhatsApp',
        'vimeo' => 'Vimeo',
        'reddit' => 'Reddit',
        'medium' => 'Medium',
        'substack' => 'Substack',
        'behance' => 'Behance',
        'dribbble' => 'Dribbble',
        'email' => 'Email',
        'website' => 'Website',
    ];

    /**
     * The bio page the social link belongs to.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Get the label shown to visitors for the platform.
     */
    public function label(): string
    {
        return self::PLATFORMS[$this->platform] ?? ucfirst($this->platform);
    }

    /**
     * Get the destination the social icon points at.
     */
    public function href(): string
    {
        return $this->platform === 'email' ? 'mailto:'.$this->url : $this->url;
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
        ];
    }
}
