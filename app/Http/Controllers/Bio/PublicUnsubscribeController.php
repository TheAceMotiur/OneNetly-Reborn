<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PublicUnsubscribeController extends Controller
{
    /**
     * Remove the subscriber that owns the token.
     */
    public function __invoke(string $token): RedirectResponse
    {
        $subscriber = Subscriber::query()->where('token', $token)->first();

        if ($subscriber === null) {
            Inertia::flash('toast', [
                'type' => 'info',
                'message' => __('This email address is already unsubscribed.'),
            ]);

            return to_route('home');
        }

        $subscriber->delete();

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('You have been unsubscribed.'),
        ]);

        return to_route('home');
    }
}
