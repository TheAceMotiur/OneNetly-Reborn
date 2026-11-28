<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\Review;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReviewController extends Controller
{
    /**
     * Review moderation queue.
     */
    public function index(Request $request, Profile $profile): Response
    {
        $this->authorizeAccess($request, $profile);

        $reviews = $profile->reviews()->latest()->get();

        return Inertia::render('reviews/Index', [
            'restaurant' => ['username' => $profile->username, 'name' => $profile->restaurantName()],
            'reviews' => $reviews->map(fn (Review $review): array => [
                'id' => $review->id,
                'name' => $review->name,
                'rating' => $review->rating,
                'comment' => $review->comment,
                'is_approved' => $review->is_approved,
                'created_at' => $review->created_at?->toDayDateTimeString(),
            ])->all(),
            'stats' => [
                'total' => $reviews->count(),
                'pending' => $reviews->where('is_approved', false)->count(),
                'average' => round((float) $reviews->where('is_approved', true)->avg('rating'), 1),
            ],
        ]);
    }

    /**
     * Publish or hide a review.
     */
    public function update(Request $request, Profile $profile, Review $review): RedirectResponse
    {
        $this->authorizeAccess($request, $profile);
        abort_unless($review->profile_id === $profile->id, 404);

        $data = $request->validate(['is_approved' => ['required', 'boolean']]);
        $review->update(['is_approved' => (bool) $data['is_approved']]);

        app(AuditLogger::class)->log($profile, 'review.moderated', __($review->is_approved
            ? 'Review from :name approved.'
            : 'Review from :name hidden.', ['name' => $review->name]), $review);

        return back();
    }

    /**
     * Remove a review permanently.
     */
    public function destroy(Request $request, Profile $profile, Review $review): RedirectResponse
    {
        $this->authorizeAccess($request, $profile);
        abort_unless($review->profile_id === $profile->id, 404);

        $name = $review->name;

        $review->delete();

        app(AuditLogger::class)->log($profile, 'review.deleted', __('Review from :name deleted.', ['name' => $name]));

        return back();
    }

    private function authorizeAccess(Request $request, Profile $profile): void
    {
        $user = $request->user();

        abort_unless(
            $user !== null
            && ($profile->user_id === $user->id
                || $user->sharedRestaurants()->whereKey($profile->id)->exists()),
            403,
        );
    }
}
