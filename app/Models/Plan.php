<?php

namespace App\Models;

use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string $plan_type
 * @property string|null $tool
 * @property string $price
 * @property string $currency
 * @property array<int, string>|null $features
 * @property bool $is_active
 * @property bool $has_custom_domain
 * @property bool $has_analytics
 * @property bool $has_white_label
 * @property int $position
 * @property int|null $max_links
 * @property int|null $max_restaurants
 * @property int|null $duration_days
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    /** @var list<string> */
    public const PLAN_TYPES = ['free', 'pro', 'premium', 'tool', 'bundle'];

    /**
     * The standalone automation tools that can be sold separately.
     *
     * @var array<string, string>
     */
    public const TOOLS = [
        'whatsapp' => 'WhatsApp Automation',
        'facebook' => 'Facebook Automation',
        'instagram' => 'Instagram Automation',
        'twitter' => 'Twitter Automation',
        'email' => 'Email Marketing',
    ];

    /** @var list<string> */
    public const FEATURE_FLAGS = ['has_custom_domain', 'has_analytics', 'has_white_label'];

    /** @var list<string> */
    protected $fillable = [
        'name',
        'plan_type',
        'tool',
        'price',
        'currency',
        'features',
        'is_active',
        'has_custom_domain',
        'has_analytics',
        'has_white_label',
        'position',
        'max_links',
        'max_restaurants',
        'duration_days',
    ];

    /**
     * The purchases that used this plan.
     *
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /** Get the formatted price shown to visitors. */
    public function displayPrice(): string
    {
        if ((float) $this->price <= 0) {
            return 'Free';
        }

        return number_format((float) $this->price, 2).' '.$this->currency;
    }

    /** Get the automation tool this plan unlocks, if any. */
    public function toolLabel(): ?string
    {
        return $this->tool === null ? null : (self::TOOLS[$this->tool] ?? null);
    }

    /** Determine whether this plan sells a single automation tool. */
    public function isToolPlan(): bool
    {
        return $this->plan_type === 'tool' && $this->tool !== null;
    }

    /** Determine whether this plan bundles every automation tool. */
    public function isBundlePlan(): bool
    {
        return $this->plan_type === 'bundle';
    }

    /** Get the human readable plan type. */
    public function typeDisplayName(): string
    {
        return match ($this->plan_type) {
            'free' => 'Free',
            'pro' => 'Pro',
            'premium' => 'Premium',
            'tool' => $this->toolLabel() ?? 'Tool',
            'bundle' => 'All tools bundle',
            default => ucfirst($this->plan_type),
        };
    }

    /** Determine whether the plan allows any number of links. */
    public function hasUnlimitedLinks(): bool
    {
        return $this->max_links === null;
    }

    /** Describe the link limit for visitors. */
    public function linkLimitLabel(): string
    {
        return $this->hasUnlimitedLinks()
            ? 'Unlimited links'
            : 'Up to '.$this->max_links.' links';
    }

    /** Determine whether the plan allows any number of restaurants. */
    public function hasUnlimitedRestaurants(): bool
    {
        return $this->max_restaurants === null;
    }

    /** Describe the restaurant limit for visitors. */
    public function restaurantLimitLabel(): string
    {
        return $this->hasUnlimitedRestaurants()
            ? 'Unlimited restaurants'
            : 'Up to '.$this->max_restaurants.' restaurants';
    }

    /** Determine whether the plan grants time-limited access. */
    public function hasExpiry(): bool
    {
        return $this->duration_days !== null && $this->duration_days > 0;
    }

    /** Describe how long the plan stays active after purchase. */
    public function durationLabel(): string
    {
        return $this->hasExpiry()
            ? __(':days days of access', ['days' => $this->duration_days])
            : __('Lifetime access');
    }

    /**
     * The list of features for display.
     *
     * @return list<string>
     */
    public function featureList(): array
    {
        return array_values($this->features ?? []);
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
            'has_custom_domain' => 'boolean',
            'has_analytics' => 'boolean',
            'has_white_label' => 'boolean',
            'features' => 'array',
            'position' => 'integer',
            'max_links' => 'integer',
            'max_restaurants' => 'integer',
            'duration_days' => 'integer',
        ];
    }
}
