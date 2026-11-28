<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $link_id
 * @property string $label
 * @property string $url
 * @property int $weight
 * @property int $clicks
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['label', 'url', 'weight', 'is_active'])]
class LinkVariant extends Model
{
    /**
     * The share of traffic a variant receives when no weight is set.
     */
    public const DEFAULT_WEIGHT = 50;

    /**
     * The link the variant belongs to.
     *
     * @return BelongsTo<Link, $this>
     */
    public function link(): BelongsTo
    {
        return $this->belongsTo(Link::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'clicks' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
