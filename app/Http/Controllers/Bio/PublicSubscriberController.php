<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Services\EmailMarketingNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicSubscriberController extends Controller
{
    /**
     * Add an email address to the page's audience.
     */
    public function store(Request $request, string $username): RedirectResponse
    {
        $profile = Profile::query()
            ->where('username', $username)
            ->where('is_published', true)
            ->firstOrFail();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:120'],
        ]);

        $subscriber = $profile->subscribers()->firstOrCreate([
            'email' => mb_strtolower($data['email']),
        ], [
            'source' => 'public_page',
        ]);

        if ($subscriber->wasRecentlyCreated) {
            app(EmailMarketingNotifier::class)->welcome($subscriber);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('You are on the list. Thanks for subscribing!'),
        ]);

        return back();
    }
}
