<?php

namespace App\Models;

use Database\Factories\PosTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $name
 * @property string $token_hash
 * @property string $token_prefix
 * @property Carbon|null $last_used_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'token_hash', 'token_prefix', 'last_used_at'])]
class PosToken extends Model
{
    /** @use HasFactory<PosTokenFactory> */
    use HasFactory;

    /**
     * The restaurant this token can read and update.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * Issue a token and return it together with the plain value shown once.
     *
     * @return array{0: self, 1: string}
     */
    public static function issue(Profile $profile, string $name): array
    {
        $plain = 'pos_'.Str::random(40);

        $token = $profile->posTokens()->create([
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'token_prefix' => substr($plain, 0, 12),
        ]);

        return [$token, $plain];
    }

    /**
     * Find the token matching a plain bearer value.
     */
    public static function findForToken(string $plain): ?self
    {
        return self::query()
            ->with('profile')
            ->where('token_hash', hash('sha256', $plain))
            ->first();
    }
}
