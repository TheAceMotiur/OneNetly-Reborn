<?php

namespace App\Models;

use Database\Factories\MenuOptionGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $menu_item_id
 * @property string $name
 * @property bool $is_required
 * @property bool $allow_multiple
 * @property int $position
 */
#[Fillable(['name', 'is_required', 'allow_multiple', 'position'])]
class MenuOptionGroup extends Model
{
    /** @use HasFactory<MenuOptionGroupFactory> */
    use HasFactory;

    /**
     * The menu item this group belongs to.
     *
     * @return BelongsTo<MenuItem, $this>
     */
    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    /**
     * The choices inside this group.
     *
     * @return HasMany<MenuOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(MenuOption::class)->orderBy('position');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'allow_multiple' => 'boolean',
            'position' => 'integer',
        ];
    }
}
