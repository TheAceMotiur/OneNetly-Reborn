<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $user_id
 * @property int $plan_id
 * @property string|null $paypal_transaction_id
 * @property PurchaseStatus $status
 * @property float $amount
 * @property string $currency
 * @property string|null $payer_email
 * @property Carbon|null $payment_date
 * @property Carbon|null $expires_at
 * @property Carbon|null $reminder_sent_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'user_id',
        'plan_id',
        'paypal_transaction_id',
        'status',
        'amount',
        'currency',
        'payer_email',
        'payment_date',
        'expires_at',
    ];

    /**
     * The account that made the purchase.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The plan that was purchased.
     *
     * @return BelongsTo<Plan, $this>
     */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    /** Get the formatted amount for display. */
    public function displayAmount(): string
    {
        return number_format($this->amount, 2).' '.$this->currency;
    }

    /** Get the status label for display. */
    public function statusDisplay(): string
    {
        return $this->status->label();
    }

    /** Get the status colour classes for display. */
    public function statusClass(): string
    {
        return $this->status->colorClass();
    }

    /** Determine whether the paid access has run out. */
    public function isExpired(): bool
    {
        return $this->status === PurchaseStatus::Completed
            && $this->expires_at !== null
            && $this->expires_at->isPast();
    }

    /** Determine whether this purchase currently grants its plan. */
    public function isActive(): bool
    {
        return $this->status === PurchaseStatus::Completed && ! $this->isExpired();
    }

    /** Describe when the purchased access ends. */
    public function accessLabel(): string
    {
        if ($this->expires_at === null) {
            return __('Lifetime access');
        }

        return $this->isExpired()
            ? __('Expired :date', ['date' => $this->expires_at->toFormattedDateString()])
            : __('Until :date', ['date' => $this->expires_at->toFormattedDateString()]);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'amount' => 'float',
            'payment_date' => 'datetime',
            'expires_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
        ];
    }
}
