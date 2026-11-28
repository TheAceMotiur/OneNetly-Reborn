<?php

namespace App\Models;

use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $menu_category_id
 * @property string $name
 * @property string|null $description
 * @property string|null $price
 * @property string|null $sale_price
 * @property string|null $sale_starts_at
 * @property string|null $sale_ends_at
 * @property string|null $dietary
 * @property array<int, string>|null $allergens
 * @property string|null $image_path
 * @property string|null $image_url
 * @property int $position
 * @property bool $is_available
 * @property bool $is_featured
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'name', 'description', 'price', 'sale_price', 'sale_starts_at', 'sale_ends_at',
    'dietary', 'allergens', 'image_path', 'position', 'is_available', 'is_featured',
])]
class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    /**
     * The image URL appended when the item is serialized.
     *
     * @var list<string>
     */
    protected $appends = ['image_url'];

    /**
     * The menu section this item belongs to.
     *
     * @return BelongsTo<MenuCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    /**
     * The choice groups (size, extras, milk) shown for this item.
     *
     * @return HasMany<MenuOptionGroup, $this>
     */
    public function optionGroups(): HasMany
    {
        return $this->hasMany(MenuOptionGroup::class)->orderBy('position');
    }

    /**
     * Serialize the option groups shown in the builder, API and public menu.
     *
     * @return array<int, array{
     *     id: int,
     *     name: string,
     *     is_required: bool,
     *     allow_multiple: bool,
     *     options: array<int, array{id: int, name: string, price: string|null, price_cents: int, is_available: bool}>
     * }>
     */
    public function optionGroupsPayload(bool $onlyAvailable = false): array
    {
        $groups = $this->optionGroups->map(function (MenuOptionGroup $group) use ($onlyAvailable): array {
            $options = $onlyAvailable
                ? $group->options->where('is_available', true)
                : $group->options;

            return [
                'id' => $group->id,
                'name' => $group->name,
                'is_required' => $group->is_required,
                'allow_multiple' => $group->allow_multiple,
                'options' => $options->map(fn (MenuOption $option): array => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'price' => $option->price,
                    'price_cents' => $option->priceInCents(),
                    'is_available' => $option->is_available,
                ])->values()->all(),
            ];
        });

        if ($onlyAvailable) {
            $groups = $groups->filter(fn (array $group): bool => $group['options'] !== [])->values();
        }

        return $groups->all();
    }

    /**
     * Determine whether the item is inside an active sale window.
     */
    public function isOnSale(?Carbon $now = null): bool
    {
        if (blank($this->sale_price)) {
            return false;
        }

        $now = $now ?? now();
        $minutes = ((int) $now->format('G') * 60) + (int) $now->format('i');
        $start = $this->timeInMinutes($this->sale_starts_at);
        $end = $this->timeInMinutes($this->sale_ends_at);

        if ($start === null || $end === null) {
            return true;
        }

        return $end <= $start
            ? $minutes >= $start || $minutes < $end
            : $minutes >= $start && $minutes < $end;
    }

    /**
     * Convert the free-form menu price to cents for ordering.
     */
    public function priceInCents(): ?int
    {
        $price = trim((string) ($this->isOnSale() ? $this->sale_price : $this->price));

        if ($price === '') {
            return null;
        }

        $normalized = str_replace([' ', ','], ['', '.'], $price);

        if (! is_numeric($normalized) || (float) $normalized < 0) {
            return null;
        }

        return (int) round((float) $normalized * 100);
    }

    /**
     * Convert a H:i time to minutes since midnight.
     */
    private function timeInMinutes(?string $time): ?int
    {
        if (! preg_match('/^(\d{1,2}):(\d{2})$/', (string) $time, $matches)) {
            return null;
        }

        return ((int) $matches[1] * 60) + (int) $matches[2];
    }

    /**
     * Get the public URL of the menu item image.
     *
     * @return Attribute<string|null, never>
     */
    protected function imageUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->image_path ? Storage::disk('public')->url($this->image_path) : null,
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
            'allergens' => 'array',
            'is_available' => 'boolean',
            'is_featured' => 'boolean',
            'position' => 'integer',
        ];
    }
}
