<?php

namespace App\Models;

use Database\Factories\ShortLinkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property string|null $title
 * @property string $slug
 * @property string $destination
 * @property string|null $mobile_destination
 * @property string|null $password
 * @property Carbon|null $expires_at
 * @property int|null $max_clicks
 * @property int $clicks
 * @property Carbon|null $last_clicked_at
 * @property bool $is_active
 * @property string|null $utm_source
 * @property string|null $utm_medium
 * @property string|null $utm_campaign
 * @property string|null $utm_term
 * @property string|null $utm_content
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'title', 'slug', 'destination', 'mobile_destination', 'password', 'expires_at',
    'max_clicks', 'clicks', 'last_clicked_at', 'is_active',
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
])]
#[Hidden(['password'])]
class ShortLink extends Model
{
    /** @use HasFactory<ShortLinkFactory> */
    use HasFactory;

    /**
     * The status string for a link whose click limit has been reached.
     */
    public const STATUS_EXHAUSTED = 'exhausted';

    /**
     * The status string for a paused link.
     */
    public const STATUS_PAUSED = 'paused';

    /**
     * The status string for an expired link.
     */
    public const STATUS_EXPIRED = 'expired';

    /**
     * The status string for a link that visitors can open.
     */
    public const STATUS_LIVE = 'live';

    /**
     * The statuses shown in the dashboard filters.
     *
     * @var list<string>
     */
    public const STATUSES = [self::STATUS_LIVE, self::STATUS_PAUSED, self::STATUS_EXPIRED, self::STATUS_EXHAUSTED];

    /**
     * The campaign parameters a link can append to its destination.
     *
     * @var list<string>
     */
    public const CAMPAIGN_PARAMETERS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];

    /**
     * Agents that receive the mobile and tablet destination.
     */
    private const MOBILE_AGENT_PATTERN = '/(android|iphone|ipad|ipod|mobile|opera mini|iemobile)/i';

    /**
     * Character set for generated slugs. Ambiguous characters are omitted.
     */
    private const SLUG_ALPHABET = 'abcdefghijkmnopqrstuvwxyz23456789';

    /**
     * The user who owns the short link.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Every click recorded for the short link.
     *
     * @return HasMany<ShortLinkClick, $this>
     */
    public function clicks(): HasMany
    {
        return $this->hasMany(ShortLinkClick::class);
    }

    /**
     * Limit the query to the links of one user.
     *
     * @param  Builder<ShortLink>  $query
     */
    public function scopeOwnedBy(Builder $query, User|int $user): void
    {
        $query->where('user_id', $user instanceof User ? $user->id : $user);
    }

    /**
     * Determine whether visitors can open the link right now.
     */
    public function isLive(): bool
    {
        return $this->status() === self::STATUS_LIVE;
    }

    /**
     * Describe the current availability of the link.
     */
    public function status(): string
    {
        if (! $this->is_active) {
            return self::STATUS_PAUSED;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return self::STATUS_EXPIRED;
        }

        if ($this->max_clicks !== null && $this->clicks >= $this->max_clicks) {
            return self::STATUS_EXHAUSTED;
        }

        return self::STATUS_LIVE;
    }

    /**
     * Determine whether the link asks visitors for a password.
     */
    public function hasPassword(): bool
    {
        return filled($this->password);
    }

    /**
     * Get the public URL visitors share.
     */
    public function shortUrl(): string
    {
        return route('short-links.redirect', $this->slug);
    }

    /**
     * Resolve the destination for this request, honoring device targeting and
     * the campaign parameters configured on the link.
     */
    public function destinationFor(Request $request): string
    {
        $destination = $this->mobile_destination !== null && $this->isMobileVisitor($request)
            ? $this->mobile_destination
            : $this->destination;

        return $this->withCampaignParameters($destination);
    }

    /**
     * Determine whether the request comes from a phone or tablet.
     */
    public function isMobileVisitor(Request $request): bool
    {
        return preg_match(self::MOBILE_AGENT_PATTERN, (string) $request->userAgent()) === 1;
    }

    /**
     * Build a slug that is not taken yet.
     */
    public static function generateSlug(int $length = 7): string
    {
        do {
            $slug = '';

            for ($index = 0; $index < $length; $index++) {
                $slug .= self::SLUG_ALPHABET[random_int(0, strlen(self::SLUG_ALPHABET) - 1)];
            }
        } while (self::query()->where('slug', $slug)->exists());

        return $slug;
    }

    /**
     * Append the configured UTM parameters to the destination without losing
     * an existing query string or fragment.
     */
    private function withCampaignParameters(string $destination): string
    {
        /** @var array<string, string> $parameters */
        $parameters = [];

        foreach (self::CAMPAIGN_PARAMETERS as $parameter) {
            $value = $this->getAttribute($parameter);

            if (filled($value)) {
                $parameters[$parameter] = (string) $value;
            }
        }

        if ($parameters === []) {
            return $destination;
        }

        $fragment = '';

        if (str_contains($destination, '#')) {
            [$destination, $fragment] = explode('#', $destination, 2);
            $fragment = '#'.$fragment;
        }

        [$base, $queryString] = array_pad(explode('?', $destination, 2), 2, '');

        /** @var array<string, string> $query */
        $query = [];
        parse_str($queryString, $query);

        return $base.'?'.http_build_query(array_merge($query, $parameters)).$fragment;
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
            'max_clicks' => 'integer',
            'expires_at' => 'datetime',
            'last_clicked_at' => 'datetime',
        ];
    }
}
