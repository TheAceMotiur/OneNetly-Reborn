<?php

namespace App\Models;

use Database\Factories\MenuOptionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $menu_option_group_id
 * @property string $name
 * @property string|null $price
 * @property bool $is_available
 * @property int $position
 */
#[Fillable(['name', 'price', 'is_available', 'position'])]
class MenuOption extends Model
{
    /** @use HasFactory<MenuOptionFactory> */
    use HasFactory;

    /**
     * The group this choice belongs to.
     *
     * @return BelongsTo<MenuOptionGroup, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(MenuOptionGroup::class, 'menu_option_group_id');
    }

    /**
     * Convert the price difference to cents.
     */
    public function priceInCents(): int
    {
        $price = trim((string) $this->price);

        if ($price === '') {
            return 0;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $price);

        if (! is_numeric($normalized) || (float) $normalized < 0) {
            return 0;
        }

        return (int) round((float) $normalized * 100);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
            'position' => 'integer',
        ];
    }
}
