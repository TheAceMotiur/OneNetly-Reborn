<?php

namespace App\Http\Controllers;

use App\Models\ProfileInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class InvitationController extends Controller
{
    /**
     * Accept a page invitation for the signed-in user.
     */
    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = ProfileInvitation::query()
            ->where('token', $token)
            ->with('profile')
            ->firstOrFail();

        if (! $invitation->isPending()) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('This invitation has already been accepted.'),
            ]);

            return to_route('dashboard');
        }

        $user = $request->user();

        if ($user === null) {
            return redirect()->guest(route('login', ['email' => $invitation->email]));
        }

        if (mb_strtolower($user->email) !== mb_strtolower($invitation->email)) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This invitation was sent to :email. Sign in with that address to accept it.', [
                    'email' => $invitation->email,
                ]),
            ]);

            return to_route('dashboard');
        }

        $pageName = $invitation->profile->restaurantName();

        $invitation->acceptFor($user);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('You now have access to :name.', ['name' => $pageName]),
        ]);

        return to_route('dashboard');
    }
}
