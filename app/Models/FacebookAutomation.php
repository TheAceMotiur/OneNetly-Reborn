<?php

namespace App\Models;

use Database\Factories\FacebookAutomationFactory;
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
class FacebookAutomation extends Model
{
    /** @use HasFactory<FacebookAutomationFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'facebook_automations';

    /**
     * The singleton automations every page can configure.
     *
     * @var array<string, array{label: string, description: string, message: string}>
     */
    public const DEFAULTS = [
        'welcome' => [
            'label' => 'Welcome message',
            'description' => 'Sent the first time someone messages your Page.',
            'message' => 'Hi {customer}, thanks for messaging {page}. How can we help?',
        ],
        'comment' => [
            'label' => 'Comment auto-reply',
            'description' => 'Replies to new comments. Leave keywords blank to answer every comment.',
            'message' => 'Thanks for your comment, {customer}! We will get back to you shortly.',
        ],
        'comment_dm' => [
            'label' => 'Comment to DM',
            'description' => 'Sends a direct message when a comment contains one of your keywords.',
            'message' => 'Hi {customer}, thanks for your comment! Reply here and we will send you the details.',
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
            $profile->facebookAutomations()->firstOrCreate(
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
