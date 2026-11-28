<?php

namespace App\Models;

use Database\Factories\LinkClickFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $link_id
 * @property int|null $variant_id
 * @property Carbon $clicked_at
 * @property string|null $referrer
 * @property string|null $user_agent
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['link_id', 'variant_id', 'clicked_at', 'referrer', 'user_agent'])]
class LinkClick extends Model
{
    /** @use HasFactory<LinkClickFactory> */
    use HasFactory;

    /**
     * The link that was clicked.
     *
     * @return BelongsTo<Link, $this>
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }

    /**
     * The A/B variant that was clicked, when the link is being tested.
     *
     * @return BelongsTo<LinkVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(LinkVariant::class, 'variant_id');
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
