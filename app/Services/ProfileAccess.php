<?php

namespace App\Services;

use App\Models\Profile;
use Illuminate\Http\Request;

class ProfileAccess
{
    /**
     * Determine whether the page requires a password.
     */
    public function requiresPassword(Profile $profile): bool
    {
        return filled($profile->access_password);
    }

    /**
     * Determine whether this session may view the page.
     */
    public function isUnlocked(Request $request, Profile $profile): bool
    {
        if ($request->user()?->getAuthIdentifier() === $profile->user_id) {
            return true;
        }

        return ! $this->requiresPassword($profile)
            || $request->session()->has($this->sessionKey($profile));
    }

    /**
     * Mark the page as unlocked for this session.
     */
    public function unlock(Request $request, Profile $profile): void
    {
        $request->session()->put($this->sessionKey($profile), true);
    }

    /**
     * Build a session key that changes whenever the password changes.
     */
    private function sessionKey(Profile $profile): string
    {
        return 'profile-access:'.$profile->id.':'.hash('sha256', (string) $profile->access_password);
    }
}
