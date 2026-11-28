<?php

use App\Models\Profile;
use App\Models\Subscriber;
use App\Models\User;

test('guests can subscribe once with a valid email', function () {
    $profile = Profile::factory()->create(['is_published' => true]);

    $this->post(route('subscribe', $profile->username), ['email' => 'Guest@Example.com'])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->post(route('subscribe', $profile->username), ['email' => 'guest@example.com'])
        ->assertRedirect();

    expect(Subscriber::query()->sole())
        ->profile_id->toBe($profile->id)
        ->email->toBe('guest@example.com');
});

test('owners can list, export and remove subscribers', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->restaurant()->create();
    $subscriber = Subscriber::factory()->for($profile)->create();

    $this->actingAs($owner)
        ->get(route('subscribers.index', $profile))
        ->assertOk()
        ->assertSee($subscriber->email);

    $this->actingAs($owner)
        ->get(route('subscribers.export', $profile))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $this->actingAs($owner)
        ->delete(route('subscribers.destroy', [$profile, $subscriber]))
        ->assertRedirect();

    expect(Subscriber::query()->count())->toBe(0);
});

test('other owners cannot read a page audience', function () {
    $profile = Profile::factory()->restaurant()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('subscribers.index', $profile))
        ->assertForbidden();
});
