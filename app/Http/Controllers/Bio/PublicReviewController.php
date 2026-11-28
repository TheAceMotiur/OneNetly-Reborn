<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicReviewController extends Controller
{
    /**
     * Accept a guest review for moderation.
     */
    public function store(Request $request, string $username): RedirectResponse
    {
        $profile = Profile::query()
            ->where('username', $username)
            ->where('is_published', true)
            ->firstOrFail();

        if (filled($request->input('website'))) {
            return back();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'rating' => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        unset($data['website']);

        $profile->reviews()->create([
            ...$data,
            'is_approved' => false,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Thanks for your review. It will appear once approved.'),
        ]);

        return back();
    }
}
