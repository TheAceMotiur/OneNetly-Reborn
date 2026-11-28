<?php

namespace App\Models;

use Database\Factories\LoyaltyAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $phone
 * @property string|null $name
 * @property string|null $email
 * @property int $points
 * @property int $lifetime_points
 * @property int $visits
 * @property int $total_spent_cents
 * @property string|null $notes
 * @property Carbon|null $last_order_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'profile_id', 'phone', 'name', 'email', 'points', 'lifetime_points',
    'visits', 'total_spent_cents', 'notes', 'last_order_at',
])]
class LoyaltyAccount extends Model
{
    /** @use HasFactory<LoyaltyAccountFactory> */
    use HasFactory;

    /**
     * The restaurant the account belongs to.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'points' => 'integer',
            'lifetime_points' => 'integer',
            'visits' => 'integer',
            'total_spent_cents' => 'integer',
            'last_order_at' => 'datetime',
        ];
    }
}
