<?php

namespace App\Models;

use Database\Factories\TwitterAutomationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $key
 * @property string|null $name
 * @property string|null $keywords
 * @property string|null $message
 * @property bool $is_enabled
 */
#[Fillable(['profile_id', 'key', 'name', 'keywords', 'message', 'is_enabled'])]
class TwitterAutomation extends Model
{
    /** @use HasFactory<TwitterAutomationFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'twitter_automations';

    /**
     * The singleton automations every page can configure.
     *
     * @var array<string, array{label: string, description: string, message: string}>
     */
    public const DEFAULTS = [
        'mention' => [
            'label' => 'Mention auto-reply',
            'description' => 'Replies when someone mentions your account and no keyword rule matches.',
            'message' => 'Thanks for the mention, @{username}! How can we help?',
        ],
    ];

    /**
     * The page this automation belongs to.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Create the default rows for a page that has none yet.
     */
    public static function ensureDefaults(Profile $profile): void
    {
        foreach (self::DEFAULTS as $key => $definition) {
            $profile->twitterAutomations()->firstOrCreate(
                ['key' => $key],
                ['is_enabled' => true, 'message' => $definition['message']],
            );
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
        ];
    }
}
