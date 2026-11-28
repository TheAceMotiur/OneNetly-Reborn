<?php

namespace App\Models;

use Database\Factories\WhatsAppMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $contact_phone
 * @property string|null $contact_name
 * @property string $direction
 * @property string $message_type
 * @property string|null $body
 * @property string|null $template_name
 * @property string $status
 * @property string|null $external_id
 * @property string|null $error
 * @property array<string, mixed>|null $meta
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'profile_id', 'contact_phone', 'contact_name', 'direction', 'message_type',
    'body', 'template_name', 'status', 'external_id', 'error', 'meta',
])]
class WhatsAppMessage extends Model
{
    /** @use HasFactory<WhatsAppMessageFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_messages';

    /**
     * The page that owns this conversation.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Determine whether the message reached WhatsApp.
     */
    public function wasSent(): bool
    {
        return in_array($this->status, ['sent', 'delivered', 'read'], true);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta' => 'array',
        ];
    }
}
