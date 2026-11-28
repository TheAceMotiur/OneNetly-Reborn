<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class BioPageAccess
{
    /**
     * Resolve the biolink page being managed, honouring a shared page selector.
     *
     * Without a `page` query parameter the user's own page is used. With one,
     * the page must be owned by or shared with the user.
     */
    public function resolve(Request $request, bool $write = false): Profile
    {
        $user = $request->user();

        abort_unless($user instanceof User, 403);

        $username = trim((string) $request->query('page', ''));

        if ($username === '') {
            return Profile::forUser($user);
        }

        $profile = Profile::query()
            ->where('username', $username)
            ->where(fn (Builder $query) => $query
                ->where('business_type', '!=', Profile::RESTAURANT_TYPE)
                ->orWhereNull('business_type'))
            ->firstOrFail();

        if ($write) {
            abort_unless($profile->canEditBio($user), 403);
        } else {
            abort_unless($profile->canViewBio($user), 403);
        }

        return $profile;
    }

    /**
     * Every biolink page the user owns or collaborates on.
     *
     * @return Collection<int, array{username: string, name: string, role: string}>
     */
    public function pagesFor(User $user): Collection
    {
        $owned = Profile::query()
            ->where('user_id', $user->id)
            ->where(fn (Builder $query) => $query
                ->where('business_type', '!=', Profile::RESTAURANT_TYPE)
                ->orWhereNull('business_type'))
            ->orderBy('id')
            ->get()
            ->map(fn (Profile $profile): array => [
                'username' => $profile->username,
                'name' => $profile->display_name ?: $profile->username,
                'role' => 'owner',
            ]);

        $shared = $user->sharedProfiles()
            ->get()
            ->reject(fn (Profile $profile): bool => $profile->isRestaurant())
            ->map(fn (Profile $profile): array => [
                'username' => $profile->username,
                'name' => $profile->display_name ?: $profile->username,
                'role' => $profile->roleFor($user) ?? 'viewer',
            ]);

        return $owned->concat($shared)->unique('username')->values();
    }
}
