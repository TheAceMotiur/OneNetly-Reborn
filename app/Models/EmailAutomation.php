<?php

namespace App\Models;

use Database\Factories\EmailAutomationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $key
 * @property bool $is_enabled
 * @property string|null $subject
 * @property string|null $body
 */
#[Fillable(['profile_id', 'key', 'is_enabled', 'subject', 'body'])]
class EmailAutomation extends Model
{
    /** @use HasFactory<EmailAutomationFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'email_automations';

    /**
     * The automations every page can configure.
     *
     * @var array<string, array{label: string, description: string, subject: string, body: string}>
     */
    public const DEFAULTS = [
        'welcome' => [
            'label' => 'Welcome email',
            'description' => 'Sent when someone joins your email list.',
            'subject' => 'Welcome to {page}',
            'body' => 'Hi there, thanks for subscribing to {page}. We will send you news, offers and updates. You can unsubscribe at any time.',
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
            $profile->emailAutomations()->firstOrCreate(
                ['key' => $key],
                [
                    'is_enabled' => true,
                    'subject' => $definition['subject'],
                    'body' => $definition['body'],
                ],
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
