<?php

use App\Models\Link;
use App\Models\Profile;
use App\Models\User;

test('a link can be duplicated', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $link = Link::factory()->for($profile)->create([
        'title' => 'My shop',
        'url' => 'https://shop.example.com',
    ]);

    $this->actingAs($user)
        ->post(route('links.duplicate', $link))
        ->assertRedirect();

    $copy = $profile->links()->where('title', 'My shop (copy)')->sole();

    expect($profile->links()->count())->toBe(2)
        ->and($copy->url)->toBe('https://shop.example.com')
        ->and($copy->clicks)->toBe(0);
});

test('users cannot duplicate a link on someone elses page', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->create();
    $link = Link::factory()->for($profile)->create();
    $other = User::factory()->create();

    $this->actingAs($other)
        ->post(route('links.duplicate', $link))
        ->assertForbidden();

    expect($profile->links()->count())->toBe(1);
});

test('duplicating respects the plan link limit', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $links = Link::factory()->for($profile)->count(5)->create();

    $this->actingAs($user)
        ->post(route('links.duplicate', $links->first()))
        ->assertSessionHasErrors('limit');

    expect($profile->links()->count())->toBe(5);
});
