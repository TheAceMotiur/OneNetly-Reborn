<?php

namespace App\Models;

use Database\Factories\ServiceRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $profile_id
 * @property string|null $table_number
 * @property string $type
 * @property string $status
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['table_number', 'type', 'status'])]
class ServiceRequest extends Model
{
    /** @use HasFactory<ServiceRequestFactory> */
    use HasFactory;

    /**
     * The requests a guest can send from the table.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'waiter' => 'Call waiter',
        'bill' => 'Request bill',
        'cleaning' => 'Clean table',
    ];

    /**
     * A request that still needs attention.
     */
    public const STATUS_PENDING = 'pending';

    /**
     * A request the team has handled.
     */
    public const STATUS_DONE = 'done';

    /**
     * The restaurant the request belongs to.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * The human readable request type.
     */
    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? ucfirst($this->type);
    }
}
