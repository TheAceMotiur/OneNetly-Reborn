<?php

namespace App\Services;

use App\Mail\ProfileInvitationMail;
use App\Models\Profile;
use App\Models\ProfileInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

class ProfileInviter
{
    /**
     * Invite an email address to help with a page, emailing them a link.
     */
    public function invite(Profile $profile, string $email, string $role, User $inviter): ProfileInvitation
    {
        $email = mb_strtolower(trim($email));

        $invitation = $profile->invitations()->updateOrCreate(
            ['email' => $email],
            [
                'role' => $role,
                'invited_by' => $inviter->id,
                'accepted_at' => null,
                'token' => Str::random(48),
            ],
        );

        $invitation->loadMissing('profile');

        try {
            Mail::to($email)->send(new ProfileInvitationMail($invitation, $inviter));
        } catch (Throwable $exception) {
            report($exception);
        }

        return $invitation;
    }
}
