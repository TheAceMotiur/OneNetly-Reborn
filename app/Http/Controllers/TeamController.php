<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\ProfileInviter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TeamController extends Controller
{
    /**
     * Add an existing user to a restaurant team.
     */
    public function store(Request $request, Profile $profile): RedirectResponse
    {
        $this->authorizeOwner($request, $profile);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:120'],
            'role' => ['required', 'string', Rule::in(['manager', 'staff'])],
        ]);

        $email = mb_strtolower(trim($data['email']));

        if ($email === mb_strtolower((string) $profile->user->email)) {
            throw ValidationException::withMessages([
                'email' => __('The owner already has full access.'),
            ]);
        }

        $user = User::query()->whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user instanceof User) {
            if ($profile->invitations()->where('email', $email)->whereNull('accepted_at')->exists()) {
                throw ValidationException::withMessages([
                    'email' => __('This person has already been invited.'),
                ]);
            }

            app(ProfileInviter::class)->invite($profile, $email, $data['role'], $request->user());

            app(AuditLogger::class)->log($profile, 'team.invited', __(':email invited as :role.', [
                'email' => $email,
                'role' => $data['role'],
            ]));

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Invitation sent to :email.', ['email' => $email]),
            ]);

            return back();
        }

        if ($profile->members()->whereKey($user->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('This person is already on the team.'),
            ]);
        }

        $profile->members()->attach($user, ['role' => $data['role']]);

        $profile->invitations()->where('email', $email)->delete();

        app(AuditLogger::class)->log($profile, 'team.added', __(':name added as :role.', [
            'name' => $user->name,
            'role' => $data['role'],
        ]), $user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name added as :role.', [
                'name' => $user->name,
                'role' => $data['role'],
            ]),
        ]);

        return back();
    }

    /**
     * Remove a team member.
     */
    public function destroy(Request $request, Profile $profile, User $member): RedirectResponse
    {
        $this->authorizeOwner($request, $profile);

        abort_if($member->id === $profile->user_id, 404);

        $profile->members()->detach($member->id);

        app(AuditLogger::class)->log($profile, 'team.removed', __(':name removed from the team.', [
            'name' => $member->name,
        ]), $member);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name removed from the team.', ['name' => $member->name]),
        ]);

        return back();
    }

    /**
     * Only the restaurant owner may manage the team.
     */
    private function authorizeOwner(Request $request, Profile $profile): void
    {
        abort_unless($profile->user_id === $request->user()->id, 403);
    }
}
