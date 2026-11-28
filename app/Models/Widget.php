<?php

namespace App\Models;

use App\Support\Widgets\WidgetCatalog;
use App\Support\Widgets\WidgetSnippets;
use Database\Factories\WidgetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $user_id
 * @property string $type
 * @property string $name
 * @property string|null $public_key
 * @property array<string, mixed>|null $config
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User $user
 */
#[Fillable(['type', 'name', 'config'])]
class Widget extends Model
{
    /** @use HasFactory<WidgetFactory> */
    use HasFactory;

    /**
     * Give every widget its own unguessable embed key.
     */
    protected static function booted(): void
    {
        static::creating(function (Widget $widget): void {
            $widget->public_key = $widget->public_key ?? Str::random(40);
        });
    }

    /**
     * The user that owns the widget.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The catalogue definition of this widget type.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return WidgetCatalog::definition($this->type);
    }

    /**
     * The saved configuration with every field present.
     *
     * @return array<string, mixed>
     */
    public function configuration(): array
    {
        return WidgetCatalog::normalise($this->type, is_array($this->config) ? $this->config : []);
    }

    /**
     * The public URL of the hosted embed script, versioned so saved changes
     * are picked up immediately instead of from a browser cache.
     */
    public function embedUrl(): string
    {
        return route('widgets.embed', [
            'key' => (string) $this->public_key,
            'v' => $this->updated_at?->getTimestamp() ?? 0,
        ]);
    }

    /**
     * The one line embed code visitors paste into their website.
     */
    public function snippet(): string
    {
        return WidgetSnippets::embedTag($this->embedUrl());
    }

    /**
     * A demo page used by the live preview iframe.
     */
    public function previewDocument(): string
    {
        return WidgetSnippets::preview($this->type, $this->configuration(), ! $this->user->canRemoveBranding());
    }

    /**
     * The widget as the frontend expects it.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        $definition = $this->definition();
        $category = (string) ($definition['category'] ?? '');
        $watermark = ! $this->user->canRemoveBranding();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'type' => $this->type,
            'config' => $this->configuration(),
            'snippet' => $this->snippet(),
            'preview' => WidgetSnippets::preview($this->type, $this->configuration(), $watermark),
            'watermark' => $watermark,
            'updated_at' => $this->updated_at?->toIso8601String(),
            'definition' => [
                'key' => $this->type,
                'name' => (string) ($definition['name'] ?? $this->type),
                'description' => (string) ($definition['description'] ?? ''),
                'category' => $category,
                'category_label' => WidgetCatalog::CATEGORIES[$category] ?? ucfirst($category),
                'icon' => (string) ($definition['icon'] ?? 'sparkles'),
                'placement' => (string) ($definition['placement'] ?? ''),
            ],
        ];
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'config' => 'array',
        ];
    }
}
