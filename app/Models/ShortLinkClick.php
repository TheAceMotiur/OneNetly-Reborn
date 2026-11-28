<?php

namespace App\Models;

use Database\Factories\ShortLinkClickFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $short_link_id
 * @property Carbon $clicked_at
 * @property string|null $visitor_hash
 * @property string|null $ip_hash
 * @property string|null $referrer
 * @property string|null $user_agent
 * @property string|null $device
 * @property string|null $country
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'short_link_id', 'clicked_at', 'visitor_hash', 'ip_hash',
    'referrer', 'user_agent', 'device', 'country',
])]
class ShortLinkClick extends Model
{
    /** @use HasFactory<ShortLinkClickFactory> */
    use HasFactory;

    /**
     * The short link that was opened.
     *
     * @return BelongsTo<ShortLink, $this>
     */
    public function shortLink(): BelongsTo
    {
        return $this->belongsTo(ShortLink::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'clicked_at' => 'datetime',
        ];
    }
}
