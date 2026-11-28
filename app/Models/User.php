<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property string|null $locale
 * @property bool $is_admin
 * @property bool $whatsapp_enabled
 * @property string|null $whatsapp_display_number
 * @property string|null $whatsapp_phone_number_id
 * @property string|null $whatsapp_business_account_id
 * @property string|null $whatsapp_access_token
 * @property bool $whatsapp_order_updates
 * @property bool $whatsapp_reservation_updates
 * @property bool $facebook_enabled
 * @property string|null $facebook_page_name
 * @property string|null $facebook_page_id
 * @property string|null $facebook_page_access_token
 * @property bool $email_marketing_enabled
 * @property string|null $email_reply_to
 * @property string|null $email_footer
 * @property bool $instagram_enabled
 * @property string|null $instagram_username
 * @property string|null $instagram_account_id
 * @property string|null $instagram_access_token
 * @property bool $twitter_enabled
 * @property string|null $twitter_username
 * @property string|null $twitter_user_id
 * @property string|null $twitter_access_token
 * @property string|null $twitter_refresh_token
 * @property Carbon|null $twitter_token_expires_at
 * @property Profile|null $profile
 * @property Carbon|null $email_verified_at
 * @property Carbon|null $terms_accepted_at
 * @property string $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'email', 'password', 'locale', 'terms_accepted_at',
    'whatsapp_enabled', 'whatsapp_display_number', 'whatsapp_phone_number_id',
    'whatsapp_business_account_id', 'whatsapp_access_token', 'whatsapp_order_updates',
    'whatsapp_reservation_updates',
    'facebook_enabled', 'facebook_page_name', 'facebook_page_id', 'facebook_page_access_token',
    'email_marketing_enabled', 'email_reply_to', 'email_footer',
    'instagram_enabled', 'instagram_username', 'instagram_account_id', 'instagram_access_token',
    'twitter_enabled', 'twitter_username', 'twitter_user_id', 'twitter_access_token',
    'twitter_refresh_token', 'twitter_token_expires_at',
])]
#[Hidden([
    'password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token',
    'whatsapp_access_token', 'facebook_page_access_token', 'instagram_access_token',
    'twitter_access_token', 'twitter_refresh_token',
])]
class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * Get the public biolink belonging to the user.
     *
     * @return HasOne<Profile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(Profile::class)
            ->where('business_type', '!=', Profile::RESTAURANT_TYPE)
            ->orderBy('id');
    }

    /**
     * Every page or restaurant the user runs.
     *
     * @return HasMany<Profile, $this>
     */
    public function profiles(): HasMany
    {
        return $this->hasMany(Profile::class);
    }

    /**
     * The single workspace the user's automation tools operate on.
     */
    public function workspaceProfile(): Profile
    {
        return $this->profiles()->orderBy('id')->first() ?? Profile::forUser($this);
    }

    /**
     * Every restaurant the user runs, separate from their biolink.
     *
     * @return HasMany<Profile, $this>
     */
    public function restaurants(): HasMany
    {
        return $this->hasMany(Profile::class)
            ->where(fn (Builder $query) => $query
                ->where('business_type', Profile::RESTAURANT_TYPE)
                ->orWhereHas('menuCategories'))
            ->orderBy('id');
    }

    /**
     * Every page shared with the user as a collaborator.
     *
     * @return BelongsToMany<Profile, $this>
     */
    public function sharedProfiles(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'profile_user')
            ->withPivot('role')
            ->withTimestamps()
            ->orderBy('profiles.id');
    }

    /**
     * Restaurants shared with the user as a manager or staff member.
     *
     * @return BelongsToMany<Profile, $this>
     */
    public function sharedRestaurants(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'profile_user')
            ->withPivot('role')
            ->withTimestamps()
            ->where(fn (Builder $query) => $query
                ->where('business_type', Profile::RESTAURANT_TYPE)
                ->orWhereHas('menuCategories'))
            ->orderBy('profiles.id');
    }

    /**
     * Every restaurant the user owns or helps run.
     *
     * @return Collection<int, Profile>
     */
    public function accessibleRestaurants(): Collection
    {
        return $this->restaurants()
            ->get()
            ->merge($this->sharedRestaurants()->get())
            ->unique('id')
            ->sortBy('id')
            ->values();
    }

    /**
     * The resumes the user has built.
     *
     * @return HasMany<Resume, $this>
     */
    public function resumes(): HasMany
    {
        return $this->hasMany(Resume::class)->latest('updated_at');
    }

    /**
     * The embeddable widgets the user has configured.
     *
     * @return HasMany<Widget, $this>
     */
    public function widgets(): HasMany
    {
        return $this->hasMany(Widget::class)->latest('updated_at');
    }

    /**
     * The short links the user has created.
     *
     * @return HasMany<ShortLink, $this>
     */
    public function shortLinks(): HasMany
    {
        return $this->hasMany(ShortLink::class)->latest('id');
    }

    /**
     * Every purchase made by the user.
     *
     * @return HasMany<Purchase, $this>
     */
    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    /**
     * Every plan the user has active access to right now.
     *
     * @return Collection<int, Plan>
     */
    public function activePlans(): Collection
    {
        return $this->purchases()
            ->where('status', PurchaseStatus::Completed)
            ->where(fn (Builder $query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->with('plan')
            ->get()
            ->pluck('plan')
            ->filter()
            ->unique('id')
            ->values();
    }

    /**
     * Get the website plan the user is currently subscribed to, if any.
     *
     * Automation tool plans and bundles do not change page limits.
     */
    public function currentPlan(): ?Plan
    {
        $purchase = $this->purchases()
            ->where('status', PurchaseStatus::Completed)
            ->where(fn (Builder $query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->whereHas('plan', fn (Builder $query) => $query
                ->whereNotIn('plan_type', ['tool', 'bundle']))
            ->with('plan')
            ->latest('id')
            ->first();

        return $purchase?->plan;
    }

    /**
     * The automation tool keys the user can use right now.
     *
     * @return list<string>
     */
    public function subscribedTools(): array
    {
        $plans = $this->activePlans();

        if ($plans->contains(fn (Plan $plan): bool => $plan->isBundlePlan())) {
            return array_keys(Plan::TOOLS);
        }

        /** @var list<string> $tools */
        $tools = $plans
            ->filter(fn (Plan $plan): bool => $plan->isToolPlan())
            ->map(fn (Plan $plan): string => (string) $plan->tool)
            ->unique()
            ->values()
            ->all();

        return $tools;
    }

    /**
     * The automation tools the user may open, every tool for administrators.
     *
     * @return list<string>
     */
    public function availableTools(): array
    {
        if ($this->is_admin) {
            return array_keys(Plan::TOOLS);
        }

        return $this->subscribedTools();
    }

    /**
     * Determine whether the user has an active subscription for a tool.
     */
    public function hasToolAccess(string $tool): bool
    {
        return in_array($tool, $this->availableTools(), true);
    }

    /**
     * The number of links the current plan allows, or null for unlimited.
     */
    public function linkLimit(): ?int
    {
        if ($this->is_admin) {
            return null;
        }

        $plan = $this->currentPlan();

        if ($plan !== null) {
            return $plan->max_links;
        }

        return (int) (Plan::query()->where('plan_type', 'free')->value('max_links') ?? 5);
    }

    /**
     * The number of restaurants the current plan allows, or null for unlimited.
     */
    public function restaurantLimit(): ?int
    {
        if ($this->is_admin) {
            return null;
        }

        $plan = $this->currentPlan();

        if ($plan !== null) {
            return $plan->max_restaurants;
        }

        return Plan::query()->where('plan_type', 'free')->value('max_restaurants');
    }

    /**
     * Determine whether public output may hide the OneNetly branding.
     */
    public function canRemoveBranding(): bool
    {
        return $this->hasPlanFeature('has_white_label');
    }

    /**
     * Determine whether the user's current plan includes the given feature.
     */
    public function hasPlanFeature(string $feature): bool
    {
        if ($this->is_admin) {
            return true;
        }

        return (bool) $this->currentPlan()?->{$feature};
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'terms_accepted_at' => 'datetime',
            'is_admin' => 'boolean',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'whatsapp_enabled' => 'boolean',
            'whatsapp_access_token' => 'encrypted',
            'whatsapp_order_updates' => 'boolean',
            'whatsapp_reservation_updates' => 'boolean',
            'facebook_enabled' => 'boolean',
            'facebook_page_access_token' => 'encrypted',
            'email_marketing_enabled' => 'boolean',
            'instagram_enabled' => 'boolean',
            'instagram_access_token' => 'encrypted',
            'twitter_enabled' => 'boolean',
            'twitter_access_token' => 'encrypted',
            'twitter_refresh_token' => 'encrypted',
            'twitter_token_expires_at' => 'datetime',
        ];
    }
}
