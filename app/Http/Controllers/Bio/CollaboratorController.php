<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\BioPageAccess;
use App\Services\ProfileInviter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CollaboratorController extends Controller
{
    public function __construct(private readonly BioPageAccess $pages) {}

    /**
     * Add an existing user as a collaborator on the page.
     */
    public function store(Request $request): RedirectResponse
    {
        $profile = $this->pages->resolve($request, write: true);

        abort_unless($profile->user_id === $request->user()?->id, 403);

        $data = $request->validate([
            'email' => ['required', 'email', 'max:120'],
            'role' => ['required', 'string', Rule::in(['editor', 'viewer'])],
        ]);

        $email = mb_strtolower(trim($data['email']));

        if ($email === mb_strtolower((string) $profile->user->email)) {
            throw ValidationException::withMessages([
                'email' => __('You own this page already.'),
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

            Inertia::flash('toast', [
                'type' => 'success',
                'message' => __('Invitation sent to :email.', ['email' => $email]),
            ]);

            return back();
        }

        if ($profile->collaborators()->whereKey($user->id)->exists()) {
            throw ValidationException::withMessages([
                'email' => __('This person is already a collaborator.'),
            ]);
        }

        $profile->collaborators()->attach($user, ['role' => $data['role']]);

        $profile->invitations()->where('email', $email)->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name can now :role this page.', [
                'name' => $user->name,
                'role' => $data['role'] === 'editor' ? __('edit') : __('view'),
            ]),
        ]);

        return back();
    }

    /**
     * Remove a collaborator from the page.
     */
    public function destroy(Request $request, User $member): RedirectResponse
    {
        $profile = $this->pages->resolve($request, write: true);

        abort_unless($profile->user_id === $request->user()?->id, 403);
        abort_if($member->id === $profile->user_id, 404);

        $profile->collaborators()->detach($member->id);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name no longer collaborates on this page.', ['name' => $member->name]),
        ]);

        return back();
    }
}
