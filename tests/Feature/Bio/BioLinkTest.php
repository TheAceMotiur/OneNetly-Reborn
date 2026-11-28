<?php

use App\Models\Link;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot manage a bio page', function () {
    $this->get(route('bio.edit'))->assertRedirect(route('login'));
    $this->get(route('links.index'))->assertRedirect(route('login'));
});

test('a bio page is created the first time it is opened', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace']);

    $this->actingAs($user)->get(route('bio.edit'))->assertOk();

    expect($user->refresh()->profile)->not->toBeNull()
        ->and($user->profile->username)->toBe('adalovelace');
});

test('users can update their bio page', function () {
    Storage::fake('public');

    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create(['username' => 'old-name']);

    $this->actingAs($user)->patch(route('bio.update'), [
        'username' => 'new-name',
        'display_name' => 'New Name',
        'bio' => 'Hello there.',
        'theme' => 'emerald',
        'template' => 'gradient',
        'is_published' => true,
        'avatar' => UploadedFile::fake()->image('avatar.jpg', 400, 400),
    ])->assertRedirect(route('bio.edit'));

    $profile->refresh();

    expect($profile->username)->toBe('new-name')
        ->and($profile->display_name)->toBe('New Name')
        ->and($profile->theme)->toBe('emerald')
        ->and($profile->template)->toBe('gradient')
        ->and($profile->avatar_path)->not->toBeNull();

    Storage::disk('public')->assertExists($profile->avatar_path);
});

test('reserved usernames cannot be claimed', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)->patch(route('bio.update'), [
        'username' => 'admin',
        'theme' => 'indigo',
        'is_published' => true,
    ])->assertSessionHasErrors('username');
});

test('usernames of other users cannot be claimed', function () {
    Profile::factory()->create(['username' => 'taken']);

    $user = User::factory()->create();
    Profile::factory()->for($user)->create(['username' => 'mine']);

    $this->actingAs($user)->patch(route('bio.update'), [
        'username' => 'taken',
        'theme' => 'indigo',
        'is_published' => true,
    ])->assertSessionHasErrors('username');
});

test('users can add links to their bio page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('links.store'), [
        'title' => 'Portfolio',
        'url' => 'https://example.com',
    ])->assertRedirect(route('links.index'));

    $link = $user->refresh()->profile->links()->first();

    expect($link)->not->toBeNull()
        ->and($link->title)->toBe('Portfolio')
        ->and($link->is_active)->toBeTrue()
        ->and($link->position)->toBe(0);
});

test('link urls must use http or https', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('links.store'), [
        'title' => 'Unsafe',
        'url' => 'javascript:alert(1)',
    ])->assertSessionHasErrors('url');

    expect(Link::count())->toBe(0);
});

test('users can update their links', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $link = Link::factory()->for($profile)->create(['title' => 'Old']);

    $this->actingAs($user)->patch(route('links.update', $link), [
        'title' => 'New',
        'url' => 'https://example.org',
        'is_active' => false,
    ])->assertRedirect();

    $link->refresh();

    expect($link->title)->toBe('New')
        ->and($link->url)->toBe('https://example.org')
        ->and($link->is_active)->toBeFalse();
});

test('users can reorder their links', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $first = Link::factory()->for($profile)->create(['position' => 0]);
    $second = Link::factory()->for($profile)->create(['position' => 1]);

    $this->actingAs($user)->patch(route('links.reorder'), [
        'ids' => [$second->id, $first->id],
    ])->assertRedirect();

    expect($second->refresh()->position)->toBe(0)
        ->and($first->refresh()->position)->toBe(1);
});

test('users can delete their links', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $link = Link::factory()->for($profile)->create();

    $this->actingAs($user)->delete(route('links.destroy', $link))->assertRedirect();

    expect(Link::find($link->id))->toBeNull();
});

test('users cannot manage links belonging to another bio page', function () {
    $owner = User::factory()->create();
    $link = Link::factory()->for(Profile::factory()->for($owner))->create();

    $intruder = User::factory()->create();

    $this->actingAs($intruder)->patch(route('links.update', $link), [
        'title' => 'Hijacked',
        'url' => 'https://example.net',
        'is_active' => true,
    ])->assertForbidden();

    $this->actingAs($intruder)->delete(route('links.destroy', $link))->assertForbidden();

    $this->actingAs($intruder)->patch(route('links.reorder'), [
        'ids' => [$link->id],
    ])->assertForbidden();

    expect($link->refresh()->title)->not->toBe('Hijacked');
});

test('the public page lists the active links of a published profile', function () {
    $profile = Profile::factory()->create([
        'username' => 'janedoe',
        'is_published' => true,
    ]);

    Link::factory()->for($profile)->create(['title' => 'Visible', 'position' => 0]);
    Link::factory()->for($profile)->inactive()->create(['title' => 'Hidden']);

    $this->get(route('bio.show', 'janedoe'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bio/Show')
            ->where('profile.username', 'janedoe')
            ->has('links', 1)
            ->where('links.0.title', 'Visible'));
});

test('unpublished bio pages are not publicly available', function () {
    Profile::factory()->unpublished()->create(['username' => 'hidden']);

    $this->get(route('bio.show', 'hidden'))->assertNotFound();
});

test('clicking a link records the click and redirects to the destination', function () {
    $profile = Profile::factory()->create(['is_published' => true]);
    $link = Link::factory()->for($profile)->create([
        'url' => 'https://example.com/landing',
    ]);

    $this->get(route('links.redirect', $link))
        ->assertRedirect('https://example.com/landing');

    expect($link->refresh()->clicks)->toBe(1);
});

test('paused links and unpublished pages do not redirect', function () {
    $profile = Profile::factory()->create(['is_published' => true]);
    $paused = Link::factory()->for($profile)->inactive()->create();

    $this->get(route('links.redirect', $paused))->assertNotFound();

    $hiddenProfile = Profile::factory()->unpublished()->create();
    $visibleLink = Link::factory()->for($hiddenProfile)->create();

    $this->get(route('links.redirect', $visibleLink))->assertNotFound();

    expect($paused->refresh()->clicks)->toBe(0)
        ->and($visibleLink->refresh()->clicks)->toBe(0);
});
