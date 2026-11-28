<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $name
 * @property array<int, string> $postcodes
 * @property int $fee_cents
 * @property int $min_order_cents
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'postcodes', 'fee_cents', 'min_order_cents', 'is_active'])]
class DeliveryZone extends Model
{
    /**
     * The restaurant that delivers to this zone.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Determine whether the zone covers the given postcode.
     */
    public function covers(string $postcode): bool
    {
        $postcode = self::normalisePostcode($postcode);

        if ($postcode === '') {
            return false;
        }

        foreach ($this->postcodes as $prefix) {
            if ($prefix !== '' && str_starts_with($postcode, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Clean a postcode for prefix comparison.
     */
    public static function normalisePostcode(string $postcode): string
    {
        return Str::upper(preg_replace('/\s+/', '', trim($postcode)) ?? '');
    }

    /**
     * Turn submitted postcodes into a clean, unique prefix list.
     *
     * @param  array<int, string>|string  $postcodes
     * @return array<int, string>
     */
    public static function normalisePostcodes(array|string $postcodes): array
    {
        $values = is_array($postcodes) ? $postcodes : explode(',', $postcodes);

        return collect($values)
            ->map(fn (mixed $value): string => self::normalisePostcode((string) $value))
            ->filter(fn (string $value): bool => $value !== '')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'postcodes' => 'array',
            'fee_cents' => 'integer',
            'min_order_cents' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
