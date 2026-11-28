<?php

namespace App\Models;

use Database\Factories\WhatsAppContactFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $phone
 * @property string|null $name
 * @property string|null $email
 * @property string $source
 * @property bool $opted_in
 * @property Carbon|null $last_messaged_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['profile_id', 'phone', 'name', 'email', 'source', 'opted_in', 'last_messaged_at'])]
class WhatsAppContact extends Model
{
    /** @use HasFactory<WhatsAppContactFactory> */
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'whatsapp_contacts';

    /**
     * The page this contact belongs to.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Strip formatting so phone numbers match across orders and WhatsApp.
     */
    public static function normalisePhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        return str_starts_with($digits, '00') ? substr($digits, 2) : $digits;
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opted_in' => 'boolean',
            'last_messaged_at' => 'datetime',
        ];
    }
}
