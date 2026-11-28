<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Mail\ProfileContactMessage;
use App\Models\Profile;
use App\Services\ProfileAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProfileContactController extends Controller
{
    public function __construct(private readonly ProfileAccess $access) {}

    /**
     * Send a visitor's message to the owner of the bio page.
     */
    public function store(Request $request, string $username): RedirectResponse
    {
        $profile = Profile::query()
            ->where('username', $username)
            ->where('is_published', true)
            ->with('user')
            ->firstOrFail();

        abort_unless($profile->show_contact_form, 404);

        if (! $this->access->isUnlocked($request, $profile)) {
            return redirect()->to(route('bio.show', $profile->username, false));
        }

        if (filled($request->input('website'))) {
            return back();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => ['required', 'email', 'max:120'],
            'message' => ['required', 'string', 'max:2000'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        $profile->contactMessages()->create([
            'name' => $data['name'],
            'email' => $data['email'],
            'message' => $data['message'],
        ]);

        try {
            Mail::to($profile->user->email)->send(new ProfileContactMessage(
                senderName: $data['name'],
                senderEmail: $data['email'],
                messageBody: $data['message'],
                profile: $profile,
            ));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withErrors([
                'message' => __('Your message could not be sent. Please try again later.'),
            ]);
        }

        return back();
    }
}
