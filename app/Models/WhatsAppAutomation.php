<?php

namespace App\Models;

use Database\Factories\WhatsAppAutomationFactory;
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
 * @property bool $is_enabled
 * @property string|null $message
 * @property string|null $template_name
 * @property string $language
 */
#[Fillable(['profile_id', 'key', 'name', 'keywords', 'is_enabled', 'message', 'template_name', 'language'])]
class WhatsAppAutomation extends Model
{
    /** @use HasFactory<WhatsAppAutomationFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_automations';

    /**
     * The automations every page can configure.
     *
     * @var array<string, array{label: string, description: string, message: string}>
     */
    public const DEFAULTS = [
        'welcome' => [
            'label' => 'Welcome message',
            'description' => 'Sent when someone messages you for the first time.',
            'message' => 'Hi {customer}, thanks for messaging {restaurant}. How can we help?',
        ],
        'order_received' => [
            'label' => 'Order received',
            'description' => 'Sent when a guest places an order.',
            'message' => 'Hi {customer}, {restaurant} received order {reference}. Total: {total}. Track it at {track_url}',
        ],
        'order_status' => [
            'label' => 'Order status updates',
            'description' => 'Sent whenever an order moves to a new status.',
            'message' => 'Hi {customer}, order {reference} is now {status}. Track it at {track_url}',
        ],
        'reservation_received' => [
            'label' => 'Booking received',
            'description' => 'Sent when a guest requests a table.',
            'message' => 'Hi {customer}, {restaurant} received your table request for {party} on {date} at {time}.',
        ],
        'reservation_status' => [
            'label' => 'Booking status updates',
            'description' => 'Sent when a booking is confirmed or cancelled.',
            'message' => 'Hi {customer}, your table for {party} at {restaurant} on {date} at {time} is {status}.',
        ],
        'reservation_reminder' => [
            'label' => 'Booking reminder',
            'description' => 'Sent before an upcoming confirmed booking.',
            'message' => 'Reminder: your table for {party} at {restaurant} is on {date} at {time}.',
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
     *
     * Keyword rules have their own rows and are managed separately.
     */
    public static function ensureDefaults(Profile $profile): void
    {
        foreach (self::DEFAULTS as $key => $definition) {
            $profile->whatsappAutomations()->firstOrCreate(
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
