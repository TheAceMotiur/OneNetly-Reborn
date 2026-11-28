<?php

namespace App\Models;

use Database\Factories\ProfileInvitationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $profile_id
 * @property string $email
 * @property string $role
 * @property string $token
 * @property int|null $invited_by
 * @property Carbon|null $accepted_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['profile_id', 'email', 'role', 'token', 'invited_by', 'accepted_at'])]
class ProfileInvitation extends Model
{
    /** @use HasFactory<ProfileInvitationFactory> */
    use HasFactory;

    /**
     * Add a token and normalise the email before the invitation is stored.
     */
    protected static function booted(): void
    {
        static::creating(function (self $invitation): void {
            $invitation->token ??= Str::random(48);
            $invitation->email = mb_strtolower($invitation->email);
        });
    }

    /**
     * The page the invitation belongs to.
     *
     * @return BelongsTo<Profile, $this>
     */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    /**
     * The person who sent the invitation.
     *
     * @return BelongsTo<User, $this>
     */
    public function inviter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invited_by');
    }

    /**
     * Determine whether the invitation is still waiting to be accepted.
     */
    public function isPending(): bool
    {
        return $this->accepted_at === null;
    }

    /**
     * Attach the invited user to the page with the invited role.
     */
    public function acceptFor(User $user): void
    {
        $profile = $this->profile;

        if ($profile instanceof Profile && $profile->user_id !== $user->id) {
            if (! $profile->members()->whereKey($user->id)->exists()) {
                $profile->members()->attach($user->id, ['role' => $this->role]);
            }
        }

        $this->forceFill(['accepted_at' => now()])->save();
    }

    /**
     * Accept every pending invitation addressed to the user's email.
     */
    public static function acceptPendingFor(User $user): int
    {
        $invitations = static::query()
            ->whereRaw('LOWER(email) = ?', [mb_strtolower($user->email)])
            ->whereNull('accepted_at')
            ->with('profile')
            ->get();

        foreach ($invitations as $invitation) {
            $invitation->acceptFor($user);
        }

        return $invitations->count();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'accepted_at' => 'datetime',
        ];
    }
}
