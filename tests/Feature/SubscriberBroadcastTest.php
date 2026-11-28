<?php

use App\Mail\SubscriberBroadcast;
use App\Models\Profile;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

test('owners can email every subscriber', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->create();

    Subscriber::factory()->for($profile)->count(2)->create();

    $this->actingAs($owner)
        ->post(route('subscribers.send', $profile), [
            'subject' => 'New menu',
            'message' => 'We added three new dishes this week.',
        ])
        ->assertRedirect();

    Mail::assertSent(SubscriberBroadcast::class, 2);
});

test('other users cannot email subscribers', function () {
    Mail::fake();

    $profile = Profile::factory()->create();
    Subscriber::factory()->for($profile)->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->post(route('subscribers.send', $profile), [
            'subject' => 'Spam',
            'message' => 'Buy this',
        ])
        ->assertForbidden();

    Mail::assertNothingSent();
});

test('a subscriber can unsubscribe with their token', function () {
    $subscriber = Subscriber::factory()->create();

    expect($subscriber->token)->not->toBeEmpty();

    $this->get(route('subscribers.unsubscribe', $subscriber->token))
        ->assertRedirect(route('home'));

    expect(Subscriber::find($subscriber->id))->toBeNull();
});
