<?php

namespace App\Models;

use App\Enums\OrderStatus;
use Database\Factories\OrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $reference
 * @property string $customer_name
 * @property string $customer_phone
 * @property string|null $customer_email
 * @property string $order_type
 * @property string|null $table_number
 * @property string|null $delivery_address
 * @property string|null $delivery_postcode
 * @property Carbon|null $scheduled_for
 * @property string|null $coupon_code
 * @property string|null $notes
 * @property int $subtotal_cents
 * @property int $discount_cents
 * @property int $points_redeemed
 * @property int $loyalty_discount_cents
 * @property int $delivery_fee_cents
 * @property int $service_cents
 * @property int $tax_cents
 * @property int $tip_cents
 * @property int $total_cents
 * @property string $currency
 * @property OrderStatus $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'profile_id', 'reference', 'customer_name', 'customer_phone', 'customer_email',
    'order_type', 'table_number', 'delivery_address', 'delivery_postcode', 'scheduled_for',
    'coupon_code', 'notes', 'subtotal_cents',
    'discount_cents', 'points_redeemed', 'loyalty_discount_cents', 'delivery_fee_cents',
    'service_cents', 'tax_cents', 'tip_cents', 'total_cents',
    'currency', 'status',
])]
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    /** @var array<string, string> */
    public const TYPES = [
        'dine_in' => 'Dine in',
        'takeaway' => 'Takeaway',
        'delivery' => 'Delivery',
    ];

    /**
     * The page that received the order.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * The dishes and drinks in this order.
     *
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * The human readable order type.
     */
    public function typeLabel(): string
    {
        return self::TYPES[$this->order_type] ?? ucfirst(str_replace('_', ' ', $this->order_type));
    }

    /**
     * The formatted order total.
     */
    public function displayTotal(): string
    {
        return number_format($this->total_cents / 100, 2).' '.$this->currency;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'scheduled_for' => 'datetime',
            'subtotal_cents' => 'integer',
            'discount_cents' => 'integer',
            'points_redeemed' => 'integer',
            'loyalty_discount_cents' => 'integer',
            'delivery_fee_cents' => 'integer',
            'service_cents' => 'integer',
            'tax_cents' => 'integer',
            'tip_cents' => 'integer',
            'total_cents' => 'integer',
        ];
    }
}
