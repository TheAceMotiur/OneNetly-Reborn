<?php

namespace App\Models;

use Database\Factories\LinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $title
 * @property string $url
 * @property string|null $icon
 * @property bool $is_active
 * @property int $clicks
 * @property int $position
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['title', 'url', 'icon', 'is_active', 'position', 'starts_at', 'ends_at'])]
class Link extends Model
{
    /** @use HasFactory<LinkFactory> */
    use HasFactory;

    /**
     * The icons a link can display.
     *
     * @var list<string>
     */
    public const ICONS = [
        'link', 'globe', 'mail', 'calendar', 'video', 'music', 'camera', 'store',
        'briefcase', 'book', 'mic', 'podcast', 'gamepad', 'heart', 'star',
        'download', 'code',
    ];

    /**
     * Host names mapped to the icon that represents them.
     *
     * @var array<string, string>
     */
    private const HOST_ICONS = [
        'github.com' => 'code',
        'gitlab.com' => 'code',
        'bitbucket.org' => 'code',
        'youtube.com' => 'video',
        'youtu.be' => 'video',
        'vimeo.com' => 'video',
        'tiktok.com' => 'video',
        'instagram.com' => 'camera',
        'flickr.com' => 'camera',
        'spotify.com' => 'music',
        'soundcloud.com' => 'music',
        'music.apple.com' => 'music',
        'podcasts.apple.com' => 'podcast',
        'twitch.tv' => 'gamepad',
        'linkedin.com' => 'briefcase',
        'calendly.com' => 'calendar',
        'cal.com' => 'calendar',
        'medium.com' => 'book',
        'substack.com' => 'book',
        'notion.so' => 'book',
        'amazon.com' => 'store',
        'etsy.com' => 'store',
        'shopify.com' => 'store',
        'buymeacoffee.com' => 'heart',
        'patreon.com' => 'heart',
    ];

    /**
     * The bio page the link belongs to.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Every recorded click for the link.
     *
     * @return HasMany<LinkClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(LinkClick::class);
    }

    /**
     * The A/B variants that share the traffic of this link.
     *
     * @return HasMany<LinkVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(LinkVariant::class)->orderBy('id');
    }

    /**
     * Limit the query to links visitors are currently able to open.
     *
     * @param  Builder<Link>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $now = now();

        $query
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn ($query) => $query->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }

    /**
     * Determine whether visitors can currently open the link.
     */
    public function isLive(): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return false;
        }

        return ! ($this->ends_at !== null && $this->ends_at->isPast());
    }

    /**
     * Describe the current visibility of the link.
     */
    public function status(): string
    {
        if (! $this->is_active) {
            return 'paused';
        }

        if ($this->starts_at !== null && $this->starts_at->isFuture()) {
            return 'scheduled';
        }

        if ($this->ends_at !== null && $this->ends_at->isPast()) {
            return 'expired';
        }

        return 'live';
    }

    /**
     * Get the icon key the link displays, detecting one from the URL when unset.
     */
    public function resolvedIcon(): string
    {
        return $this->icon ?: self::detectIcon($this->url);
    }

    /**
     * Guess the icon that best represents the given URL.
     */
    public static function detectIcon(string $url): string
    {
        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        if ($scheme === 'mailto') {
            return 'mail';
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        if ($host === '') {
            return 'globe';
        }

        $host = preg_replace('/^www\./', '', $host) ?? $host;

        foreach (self::HOST_ICONS as $domain => $icon) {
            if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                return $icon;
            }
        }

        return 'globe';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'clicks' => 'integer',
            'position' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }
}
