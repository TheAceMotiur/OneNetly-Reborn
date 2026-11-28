<?php

namespace App\Models;

use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $code
 * @property string $type
 * @property string $value
 * @property int $min_order_cents
 * @property int|null $max_uses
 * @property int $used_count
 * @property Carbon|null $expires_at
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'profile_id', 'code', 'type', 'value', 'min_order_cents',
    'max_uses', 'used_count', 'expires_at', 'is_active',
])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    public const TYPE_PERCENT = 'percent';

    public const TYPE_FIXED = 'fixed';

    /** @var array<string, string> */
    public const TYPES = [
        self::TYPE_PERCENT => 'Percentage',
        self::TYPE_FIXED => 'Fixed amount',
    ];

    /**
     * The restaurant that owns the promo code.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Determine whether the code can be used against an order subtotal.
     */
    public function isUsableFor(int $subtotalCents): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return false;
        }

        if ($this->max_uses !== null && $this->used_count >= $this->max_uses) {
            return false;
        }

        return $subtotalCents >= $this->min_order_cents;
    }

    /**
     * Calculate the discount in cents for an order subtotal.
     */
    public function discountFor(int $subtotalCents): int
    {
        $discount = $this->type === self::TYPE_PERCENT
            ? (int) round($subtotalCents * ((float) $this->value) / 100)
            : (int) round(((float) $this->value) * 100);

        return max(0, min($discount, $subtotalCents));
    }

    /**
     * A short description of the discount.
     */
    public function label(): string
    {
        return $this->type === self::TYPE_PERCENT
            ? rtrim(rtrim(number_format((float) $this->value, 2), '0'), '.').'% off'
            : number_format((float) $this->value, 2).' off';
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'min_order_cents' => 'integer',
            'max_uses' => 'integer',
            'used_count' => 'integer',
            'expires_at' => 'date',
            'is_active' => 'boolean',
        ];
    }
}
