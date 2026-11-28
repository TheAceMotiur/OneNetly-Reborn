<?php

namespace App\Models;

use Database\Factories\ProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $username
 * @property string|null $custom_domain
 * @property string|null $custom_domain_token
 * @property Carbon|null $custom_domain_verified_at
 * @property string|null $display_name
 * @property string|null $meta_title
 * @property string|null $meta_description
 * @property string|null $bio
 * @property string|null $access_password
 * @property string|null $avatar_path
 * @property string|null $avatar_url
 * @property string $theme
 * @property string $template
 * @property string $restaurant_template
 * @property string $business_type
 * @property string|null $company
 * @property string|null $job_title
 * @property string|null $contact_email
 * @property string|null $phone
 * @property string|null $contact_website
 * @property string|null $address
 * @property bool $is_published
 * @property bool $show_contact_form
 * @property bool $menu_ordering_enabled
 * @property bool $accepting_orders
 * @property bool $sms_notifications_enabled
 * @property bool $loyalty_enabled
 * @property int $reservation_slot_capacity
 * @property int $reservation_max_party
 * @property bool $reservation_auto_confirm
 * @property int $reservation_reminder_hours
 * @property bool $delivery_enabled
 * @property int $delivery_fee_cents
 * @property int $min_order_cents
 * @property float $tax_rate
 * @property float $service_rate
 * @property array<int, array{closed: bool, open: string, close: string}>|null $opening_hours
 * @property string $timezone
 * @property string $menu_currency
 * @property string|null $pos_webhook_url
 * @property string|null $pos_webhook_secret
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'username', 'custom_domain', 'custom_domain_token', 'custom_domain_verified_at',
    'display_name', 'meta_title', 'meta_description',
    'bio', 'access_password', 'avatar_path', 'theme', 'template', 'restaurant_template', 'business_type',
    'company', 'job_title', 'contact_email', 'phone', 'contact_website', 'address',
    'is_published', 'show_contact_form', 'menu_ordering_enabled', 'accepting_orders',
    'sms_notifications_enabled', 'loyalty_enabled', 'reservation_slot_capacity',
    'reservation_max_party', 'reservation_auto_confirm', 'reservation_reminder_hours',
    'delivery_enabled', 'delivery_fee_cents', 'min_order_cents', 'tax_rate', 'service_rate',
    'opening_hours', 'timezone', 'menu_currency', 'pos_webhook_url', 'pos_webhook_secret',
])]
#[Hidden(['access_password'])]
class Profile extends Model
{
    /** @use HasFactory<ProfileFactory> */
    use HasFactory;

    /**
     * The accent themes available for a bio page.
     *
     * @var list<string>
     */
    public const THEMES = ['indigo', 'emerald', 'rose', 'amber', 'sky', 'slate'];

    /**
     * The page templates available for a bio page.
     *
     * @var list<string>
     */
    public const TEMPLATES = ['classic', 'gradient', 'dark', 'soft', 'minimal', 'aurora', 'paper', 'glass'];

    /**
     * The layouts available for a restaurant's public menu page.
     *
     * @var array<string, string>
     */
    public const RESTAURANT_TEMPLATES = [
        'classic' => 'Classic',
        'modern' => 'Modern',
        'dark' => 'Midnight',
        'elegant' => 'Elegant',
        'fresh' => 'Fresh',
        'minimal' => 'Minimal',
    ];

    /**
     * The currencies a menu can use for prices and orders.
     *
     * @var array<string, string>
     */
    /**
     * Common timezones offered for opening hours.
     *
     * @var array<string, string>
     */
    public const TIMEZONES = [
        'UTC' => 'UTC',
        'America/New_York' => 'New York',
        'America/Chicago' => 'Chicago',
        'America/Denver' => 'Denver',
        'America/Los_Angeles' => 'Los Angeles',
        'America/Sao_Paulo' => 'São Paulo',
        'Europe/London' => 'London',
        'Europe/Paris' => 'Paris',
        'Europe/Berlin' => 'Berlin',
        'Europe/Madrid' => 'Madrid',
        'Europe/Rome' => 'Rome',
        'Africa/Lagos' => 'Lagos',
        'Africa/Nairobi' => 'Nairobi',
        'Africa/Johannesburg' => 'Johannesburg',
        'Asia/Dubai' => 'Dubai',
        'Asia/Kolkata' => 'Kolkata',
        'Asia/Singapore' => 'Singapore',
        'Asia/Tokyo' => 'Tokyo',
        'Australia/Sydney' => 'Sydney',
    ];

    /**
     * The days of the week used by the opening hours editor.
     *
     * @var array<int, string>
     */
    public const WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    /**
     * The currencies a menu can use for prices and orders.
     *
     * @var array<string, string>
     */
    public const MENU_CURRENCIES = [
        'USD' => 'US dollar (USD)',
        'EUR' => 'Euro (EUR)',
        'GBP' => 'British pound (GBP)',
        'CAD' => 'Canadian dollar (CAD)',
        'AUD' => 'Australian dollar (AUD)',
        'INR' => 'Indian rupee (INR)',
        'NGN' => 'Nigerian naira (NGN)',
        'KES' => 'Kenyan shilling (KES)',
        'ZAR' => 'South African rand (ZAR)',
        'AED' => 'UAE dirham (AED)',
    ];

    /**
     * The business type that marks a page as a restaurant rather than a biolink.
     */
    public const RESTAURANT_TYPE = 'restaurant';

    /**
     * The business types a biolink can identify as.
     *
     * @var array<string, string>
     */
    public const BUSINESS_TYPES = [
        'personal' => 'Personal',
        'creator' => 'Creator',
        'professional' => 'Professional',
        'retail' => 'Shop / retail',
        'service' => 'Service business',
        'nonprofit' => 'Nonprofit',
    ];

    /**
     * The allergens a menu item can be marked as containing.
     *
     * @var array<string, string>
     */
    public const ALLERGENS = [
        'gluten' => 'Gluten',
        'crustaceans' => 'Crustaceans',
        'eggs' => 'Eggs',
        'fish' => 'Fish',
        'peanuts' => 'Peanuts',
        'soybeans' => 'Soybeans',
        'milk' => 'Milk',
        'nuts' => 'Tree nuts',
        'celery' => 'Celery',
        'mustard' => 'Mustard',
        'sesame' => 'Sesame',
        'sulphites' => 'Sulphites',
        'lupin' => 'Lupin',
        'molluscs' => 'Molluscs',
    ];

    /**
     * The usernames that cannot be claimed because they conflict with application routes.
     *
     * @var list<string>
     */
    public const RESERVED_USERNAMES = [
        'admin', 'api', 'app', 'bio', 'dashboard', 'developer', 'developers', 'docs', 'email', 'home', 'links', 'login',
        'logout', 'l', 'menu', 'orders', 'password', 'pricing', 'privacy', 'profile', 'register',
        'facebook', 'instagram', 'twitter', 'restaurant', 'resumes', 'settings', 'short-links', 'storage', 'terms', 'up', 'user', 'users', 'verify', 'whatsapp', 'widgets',
    ];

    /**
     * Get the attributes that should be appended when the model is serialized.
     *
     * @var list<string>
     */
    protected $appends = ['avatar_url'];

    /**
     * The user that owns the bio page.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The links displayed on the bio page.
     *
     * @return HasMany<Link, $this>
     */
    public function links(): HasMany
    {
        return $this->hasMany(Link::class)->orderBy('position');
    }

    /**
     * Every recorded view of the bio page.
     *
     * @return HasMany<ProfileView, $this>
     */
    public function views(): HasMany
    {
        return $this->hasMany(ProfileView::class);
    }

    /**
     * The social icons displayed on the bio page.
     *
     * @return HasMany<SocialLink, $this>
     */
    public function socials(): HasMany
    {
        return $this->hasMany(SocialLink::class)->orderBy('position');
    }

    /**
     * The menu sections shown on the bio page.
     *
     * @return HasMany<MenuCategory, $this>
     */
    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class)->orderBy('position');
    }

    /**
     * Every dish and drink on the menu.
     *
     * @return HasManyThrough<MenuItem, MenuCategory, $this>
     */
    public function menuItems(): HasManyThrough
    {
        return $this->hasManyThrough(MenuItem::class, MenuCategory::class);
    }

    /**
     * The orders placed from the public menu.
     *
     * @return HasMany<Order, $this>
     */
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class)->latest('id');
    }

    /**
     * Messages sent through the public contact form.
     *
     * @return HasMany<ContactMessage, $this>
     */
    public function contactMessages(): HasMany
    {
        return $this->hasMany(ContactMessage::class)->latest('id');
    }

    /**
     * The managers and staff who can access this restaurant.
     *
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'profile_user')
            ->withPivot('role')
            ->withTimestamps()
            ->orderBy('users.name');
    }

    /**
     * The people who can edit or view this biolink page with its owner.
     *
     * @return BelongsToMany<User, $this>
     */
    public function collaborators(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'profile_user')
            ->withPivot('role')
            ->withTimestamps()
            ->wherePivotIn('role', ['editor', 'viewer'])
            ->orderBy('users.name');
    }

    /**
     * The role a user has for this restaurant, or null when they have none.
     */
    public function roleFor(User $user): ?string
    {
        if ($this->user_id === $user->id) {
            return 'owner';
        }

        $member = $this->members()->whereKey($user->id)->first();

        if ($member === null) {
            return null;
        }

        $pivot = $member->getRelation('pivot');
        $role = $pivot instanceof Pivot ? $pivot->getAttribute('role') : null;

        return is_string($role) ? $role : null;
    }

    /**
     * Determine whether the user may manage the restaurant and its settings.
     */
    public function canManage(User $user): bool
    {
        return in_array($this->roleFor($user), ['owner', 'manager'], true);
    }

    /**
     * Determine whether the user may handle day-to-day orders and bookings.
     */
    public function canOperate(User $user): bool
    {
        return $this->roleFor($user) !== null;
    }

    /**
     * Determine whether the user may edit this biolink page and its content.
     */
    public function canEditBio(User $user): bool
    {
        return in_array($this->roleFor($user), ['owner', 'editor'], true);
    }

    /**
     * Determine whether the user may open the management screens of this biolink.
     */
    public function canViewBio(User $user): bool
    {
        return in_array($this->roleFor($user), ['owner', 'editor', 'viewer'], true);
    }

    /**
     * The rich content blocks shown on the public page.
     *
     * @return HasMany<ProfileBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(ProfileBlock::class)->orderBy('position');
    }

    /**
     * Pending requests for people to help manage this page.
     *
     * @return HasMany<ProfileInvitation, $this>
     */
    public function invitations(): HasMany
    {
        return $this->hasMany(ProfileInvitation::class)->latest('id');
    }

    /**
     * The WhatsApp contacts collected by this page.
     *
     * @return HasMany<WhatsAppContact, $this>
     */
    public function whatsappContacts(): HasMany
    {
        return $this->hasMany(WhatsAppContact::class)->latest('id');
    }

    /**
     * The WhatsApp conversation history for this page.
     *
     * @return HasMany<WhatsAppMessage, $this>
     */
    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsAppMessage::class)->latest('id');
    }

    /**
     * The broadcast campaigns sent from this page.
     *
     * @return HasMany<WhatsAppCampaign, $this>
     */
    public function whatsappCampaigns(): HasMany
    {
        return $this->hasMany(WhatsAppCampaign::class)->latest('id');
    }

    /**
     * The editable automation messages for this page.
     *
     * @return HasMany<WhatsAppAutomation, $this>
     */
    public function whatsappAutomations(): HasMany
    {
        return $this->hasMany(WhatsAppAutomation::class);
    }

    /**
     * The Facebook automations configured for this page.
     *
     * @return HasMany<FacebookAutomation, $this>
     */
    public function facebookAutomations(): HasMany
    {
        return $this->hasMany(FacebookAutomation::class)->orderBy('id');
    }

    /**
     * The Facebook Messenger and comment history for this page.
     *
     * @return HasMany<FacebookMessage, $this>
     */
    public function facebookMessages(): HasMany
    {
        return $this->hasMany(FacebookMessage::class)->latest('id');
    }

    /**
     * The email marketing campaigns sent from this page.
     *
     * @return HasMany<EmailCampaign, $this>
     */
    public function emailCampaigns(): HasMany
    {
        return $this->hasMany(EmailCampaign::class)->latest('id');
    }

    /**
     * The email marketing automations for this page.
     *
     * @return HasMany<EmailAutomation, $this>
     */
    public function emailAutomations(): HasMany
    {
        return $this->hasMany(EmailAutomation::class);
    }

    /**
     * The Instagram automations configured for this page.
     *
     * @return HasMany<InstagramAutomation, $this>
     */
    public function instagramAutomations(): HasMany
    {
        return $this->hasMany(InstagramAutomation::class)->orderBy('id');
    }

    /**
     * The Instagram direct message and comment history for this page.
     *
     * @return HasMany<InstagramMessage, $this>
     */
    public function instagramMessages(): HasMany
    {
        return $this->hasMany(InstagramMessage::class)->latest('id');
    }

    /**
     * The Twitter automations configured for this page.
     *
     * @return HasMany<TwitterAutomation, $this>
     */
    public function twitterAutomations(): HasMany
    {
        return $this->hasMany(TwitterAutomation::class)->orderBy('id');
    }

    /**
     * The tweets drafted, scheduled or published by this page.
     *
     * @return HasMany<TwitterPost, $this>
     */
    public function twitterPosts(): HasMany
    {
        return $this->hasMany(TwitterPost::class)->latest('id');
    }

    /**
     * The automation runs recorded for this page.
     *
     * @return HasMany<AutomationActivity, $this>
     */
    public function automationActivities(): HasMany
    {
        return $this->hasMany(AutomationActivity::class)->latest('id');
    }

    /**
     * The Twitter mentions received by this page.
     *
     * @return HasMany<TwitterMention, $this>
     */
    public function twitterMentions(): HasMany
    {
        return $this->hasMany(TwitterMention::class)->latest('id');
    }

    /**
     * The delivery areas this restaurant serves.
     *
     * @return HasMany<DeliveryZone, $this>
     */
    public function deliveryZones(): HasMany
    {
        return $this->hasMany(DeliveryZone::class)->orderBy('name');
    }

    /**
     * The promo codes offered on the public menu.
     *
     * @return HasMany<Coupon, $this>
     */
    public function coupons(): HasMany
    {
        return $this->hasMany(Coupon::class);
    }

    /**
     * The loyalty accounts earned by guests of this restaurant.
     *
     * @return HasMany<LoyaltyAccount, $this>
     */
    public function loyaltyAccounts(): HasMany
    {
        return $this->hasMany(LoyaltyAccount::class)->orderByDesc('points');
    }

    /**
     * The SMS attempts sent for this restaurant.
     *
     * @return HasMany<SmsMessage, $this>
     */
    public function smsMessages(): HasMany
    {
        return $this->hasMany(SmsMessage::class)->latest('id');
    }

    /**
     * Email addresses collected from the public page.
     *
     * @return HasMany<Subscriber, $this>
     */
    public function subscribers(): HasMany
    {
        return $this->hasMany(Subscriber::class);
    }

    /**
     * Guest reviews submitted for this page.
     *
     * @return HasMany<Review, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * The table bookings requested from the public menu.
     *
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * The waiter, bill and cleaning requests sent from the tables.
     *
     * @return HasMany<ServiceRequest, $this>
     */
    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class)->latest('id');
    }

    /**
     * The API tokens a POS or integration uses for this restaurant.
     *
     * @return HasMany<PosToken, $this>
     */
    public function posTokens(): HasMany
    {
        return $this->hasMany(PosToken::class)->latest('id');
    }

    /**
     * The outbound webhook attempts for this restaurant.
     *
     * @return HasMany<WebhookDelivery, $this>
     */
    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class)->latest('id');
    }

    /**
     * Determine whether guests can place orders right now.
     */
    public function acceptsOrders(): bool
    {
        return $this->menu_ordering_enabled
            && $this->accepting_orders
            && $this->isOpenNow();
    }

    /**
     * Determine whether the restaurant is inside its opening hours.
     *
     * Restaurants without configured hours are always open. A close time
     * earlier than the open time means the shift runs past midnight.
     */
    public function isOpenNow(?Carbon $now = null): bool
    {
        $hours = $this->normalisedOpeningHours();

        if ($hours === []) {
            return true;
        }

        $local = ($now ?? now())->copy()->setTimezone($this->timezone ?: (string) config('app.timezone'));
        $minutes = ((int) $local->format('G') * 60) + (int) $local->format('i');
        $today = (int) $local->dayOfWeek;
        $yesterday = $hours[($today + 6) % 7];

        if (! $yesterday['closed']) {
            $open = $this->minutes($yesterday['open']);
            $close = $this->minutes($yesterday['close']);

            if ($open !== null && $close !== null && $close <= $open && $minutes < $close) {
                return true;
            }
        }

        $day = $hours[$today];

        if ($day['closed']) {
            return false;
        }

        $open = $this->minutes($day['open']);
        $close = $this->minutes($day['close']);

        if ($open === null || $close === null) {
            return false;
        }

        return $close <= $open
            ? $minutes >= $open
            : $minutes >= $open && $minutes < $close;
    }

    /**
     * A short label for today's opening hours.
     */
    public function todayHoursLabel(): ?string
    {
        $hours = $this->normalisedOpeningHours();

        if ($hours === []) {
            return null;
        }

        $day = $hours[(int) now()->setTimezone($this->timezone ?: (string) config('app.timezone'))->dayOfWeek];

        if ($day['closed']) {
            return 'Closed today';
        }

        return $day['open'].'–'.$day['close'];
    }

    /**
     * Normalize the stored opening hours into seven complete days.
     *
     * @return array<int, array{closed: bool, open: string, close: string}>
     */
    private function normalisedOpeningHours(): array
    {
        $stored = $this->opening_hours;

        if (! is_array($stored) || $stored === []) {
            return [];
        }

        $hours = [];

        for ($day = 0; $day < 7; $day++) {
            $row = $stored[$day] ?? $stored[(string) $day] ?? null;

            if (! is_array($row)) {
                return [];
            }

            $hours[$day] = [
                'closed' => (bool) $row['closed'],
                'open' => (string) $row['open'],
                'close' => (string) $row['close'],
            ];
        }

        return $hours;
    }

    /**
     * Convert a H:i time to minutes since midnight.
     */
    private function minutes(string $time): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', $time, $matches)) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }

    /**
     * The name shown for this page when it is managed as a restaurant.
     */
    public function restaurantName(): string
    {
        return $this->company ?: ($this->display_name ?: $this->username);
    }

    /**
     * Determine whether this page is a restaurant rather than a biolink.
     */
    public function isRestaurant(): bool
    {
        return $this->business_type === self::RESTAURANT_TYPE;
    }

    /**
     * The human readable business type, if one is set.
     */
    public function businessTypeLabel(): ?string
    {
        if ($this->business_type === 'personal') {
            return null;
        }

        return self::BUSINESS_TYPES[$this->business_type] ?? null;
    }

    /**
     * Determine whether this profile has enough details for a vCard.
     */
    public function hasVCardDetails(): bool
    {
        return filled($this->company)
            || filled($this->job_title)
            || filled($this->contact_email)
            || filled($this->phone)
            || filled($this->contact_website)
            || filled($this->address);
    }

    /**
     * Get the bio page for the user, creating it on first use.
     */
    public static function forUser(User $user): self
    {
        return $user->profile()->firstOrCreate([], [
            'username' => self::availableUsername($user->name),
            'display_name' => $user->name,
        ]);
    }

    /**
     * Build a username that is not yet taken.
     */
    public static function availableUsername(string $name): string
    {
        $base = Str::limit(Str::slug($name, ''), 25, '') ?: 'user';

        if (in_array($base, self::RESERVED_USERNAMES, true)) {
            $base .= 'page';
        }

        $candidate = $base;
        $suffix = 1;

        while (self::where('username', $candidate)->exists()) {
            $candidate = $base.$suffix++;
        }

        return $candidate;
    }

    /**
     * Get the public URL of the uploaded avatar.
     *
     * @return Attribute<string|null, never>
     */
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->avatar_path ? Storage::disk('public')->url($this->avatar_path) : null,
        );
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'show_contact_form' => 'boolean',
            'menu_ordering_enabled' => 'boolean',
            'accepting_orders' => 'boolean',
            'sms_notifications_enabled' => 'boolean',
            'loyalty_enabled' => 'boolean',
            'reservation_slot_capacity' => 'integer',
            'reservation_max_party' => 'integer',
            'reservation_auto_confirm' => 'boolean',
            'reservation_reminder_hours' => 'integer',
            'custom_domain_verified_at' => 'datetime',
            'delivery_enabled' => 'boolean',
            'delivery_fee_cents' => 'integer',
            'min_order_cents' => 'integer',
            'tax_rate' => 'float',
            'service_rate' => 'float',
            'opening_hours' => 'array',
        ];
    }
}
