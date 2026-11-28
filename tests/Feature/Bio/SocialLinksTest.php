<?php

use App\Models\Profile;
use App\Models\SocialLink;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('users can add social links to their page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('socials.store'), [
        'platform' => 'github',
        'url' => 'https://github.com/laravel',
    ])->assertRedirect();

    $social = $user->refresh()->profile->socials()->sole();

    expect($social->platform)->toBe('github')
        ->and($social->url)->toBe('https://github.com/laravel')
        ->and($social->position)->toBe(0);
});

test('social links are appended in the order they are added', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('socials.store'), [
        'platform' => 'instagram',
        'url' => 'https://instagram.com/laravel',
    ]);

    $this->actingAs($user)->post(route('socials.store'), [
        'platform' => 'x',
        'url' => 'https://x.com/laravel',
    ]);

    $positions = $user->refresh()->profile->socials()->pluck('position', 'platform');

    expect($positions->all())->toBe(['instagram' => 0, 'x' => 1]);
});

test('unknown social platforms are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('socials.store'), [
        'platform' => 'myspace',
        'url' => 'https://myspace.com/laravel',
    ])->assertSessionHasErrors('platform');

    expect(SocialLink::count())->toBe(0);
});

test('social urls must use http or https', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('socials.store'), [
        'platform' => 'website',
        'url' => 'javascript:alert(1)',
    ])->assertSessionHasErrors('url');

    expect(SocialLink::count())->toBe(0);
});

test('the email platform requires an email address and links to mailto', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'username' => 'mailme',
        'is_published' => true,
    ]);

    $this->actingAs($user)->post(route('socials.store'), [
        'platform' => 'email',
        'url' => 'hello@example.com',
    ])->assertRedirect();

    $this->get(route('bio.show', 'mailme'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('socials', 1)
            ->where('socials.0.platform', 'email')
            ->where('socials.0.label', 'Email')
            ->where('socials.0.href', 'mailto:hello@example.com'));

    $this->actingAs($user)->post(route('socials.store'), [
        'platform' => 'email',
        'url' => 'not-an-email',
    ])->assertSessionHasErrors('url');
});

test('users can edit their social links', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $social = $profile->socials()->create([
        'platform' => 'instagram',
        'url' => 'https://instagram.com/old',
    ]);

    $this->actingAs($user)->patch(route('socials.update', $social), [
        'platform' => 'threads',
        'url' => 'https://threads.net/@new',
    ])->assertRedirect();

    $social->refresh();

    expect($social->platform)->toBe('threads')
        ->and($social->url)->toBe('https://threads.net/@new')
        ->and($social->label())->toBe('Threads');
});

test('editing a social link validates the platform and url', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $social = $profile->socials()->create([
        'platform' => 'github',
        'url' => 'https://github.com/laravel',
    ]);

    $this->actingAs($user)->patch(route('socials.update', $social), [
        'platform' => 'github',
        'url' => 'javascript:alert(1)',
    ])->assertSessionHasErrors('url');

    $this->actingAs($user)->patch(route('socials.update', $social), [
        'platform' => 'myspace',
        'url' => 'https://github.com/laravel',
    ])->assertSessionHasErrors('platform');

    expect($social->refresh()->url)->toBe('https://github.com/laravel');
});

test('users cannot edit social links on another page', function () {
    $profile = Profile::factory()->create();
    $social = $profile->socials()->create([
        'platform' => 'reddit',
        'url' => 'https://reddit.com/r/laravel',
    ]);

    $intruder = User::factory()->create();

    $this->actingAs($intruder)->patch(route('socials.update', $social), [
        'platform' => 'reddit',
        'url' => 'https://reddit.com/r/hijacked',
    ])->assertForbidden();

    expect($social->refresh()->url)->toBe('https://reddit.com/r/laravel');
});

test('users can delete their own social links', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $social = $profile->socials()->create([
        'platform' => 'tiktok',
        'url' => 'https://tiktok.com/@laravel',
    ]);

    $this->actingAs($user)->delete(route('socials.destroy', $social))->assertRedirect();

    expect(SocialLink::find($social->id))->toBeNull();
});

test('users cannot delete social links on another page', function () {
    $profile = Profile::factory()->create();
    $social = $profile->socials()->create([
        'platform' => 'twitch',
        'url' => 'https://twitch.tv/laravel',
    ]);

    $intruder = User::factory()->create();

    $this->actingAs($intruder)
        ->delete(route('socials.destroy', $social))
        ->assertForbidden();

    expect(SocialLink::find($social->id))->not->toBeNull();
});

test('the page editor lists the platforms and the saved social links', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();
    $profile->socials()->create([
        'platform' => 'youtube',
        'url' => 'https://youtube.com/@laravel',
    ]);

    $this->actingAs($user)->get(route('bio.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('bio/Edit')
            ->has('socials', 1)
            ->where('socials.0.platform', 'youtube')
            ->has('platforms.instagram')
            ->where('platforms.email', 'Email'));
});

test('the public page only shows social links of published profiles', function () {
    Profile::factory()->unpublished()->create(['username' => 'private-page']);

    $this->get(route('bio.show', 'private-page'))->assertNotFound();
});
