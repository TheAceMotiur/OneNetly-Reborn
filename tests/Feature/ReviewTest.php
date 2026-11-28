<?php

use App\Models\Profile;
use App\Models\Review;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests can submit a review that waits for approval', function () {
    $profile = Profile::factory()->restaurant()->create(['is_published' => true]);

    $this->post(route('restaurant.review', $profile->username), [
        'name' => 'Ada',
        'rating' => 5,
        'comment' => 'Lovely meal.',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $review = Review::query()->sole();

    expect($review->profile_id)->toBe($profile->id)
        ->and($review->rating)->toBe(5)
        ->and($review->is_approved)->toBeFalse();
});

test('only approved reviews appear on the public menu', function () {
    $profile = Profile::factory()->restaurant()->create(['is_published' => true]);
    Review::factory()->for($profile)->approved()->create(['name' => 'Visible guest']);
    Review::factory()->for($profile)->create(['name' => 'Hidden guest']);

    $this->get(route('restaurant.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('restaurant/Show')
            ->has('reviews', 1)
            ->where('reviews.0.name', 'Visible guest')
            ->where('review_stats.count', 1));
});

test('owners can approve, hide and delete reviews for their restaurant', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->restaurant()->create();
    $review = Review::factory()->for($profile)->create();

    $this->actingAs($owner)
        ->patch(route('restaurant.reviews.update', [$profile, $review]), ['is_approved' => true])
        ->assertRedirect();

    expect($review->fresh()->is_approved)->toBeTrue();

    $this->actingAs($owner)
        ->delete(route('restaurant.reviews.destroy', [$profile, $review]))
        ->assertRedirect();

    expect(Review::query()->count())->toBe(0);
});
