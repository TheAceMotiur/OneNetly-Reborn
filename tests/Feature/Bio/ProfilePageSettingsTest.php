<?php

use App\Models\Link;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

function profilePagePayload(User $user, array $overrides = []): array
{
    return array_merge([
        'username' => $user->profile->username,
        'display_name' => 'Page Settings',
        'theme' => 'indigo',
        'template' => 'classic',
        'is_published' => true,
        'show_contact_form' => false,
    ], $overrides);
}

test('owners can protect their page with a password', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->patch(route('bio.update'), profilePagePayload($user, [
            'page_password' => 'open-sesame',
        ]))
        ->assertRedirect(route('bio.edit'))
        ->assertSessionHasNoErrors();

    $profile->refresh();

    expect(Hash::check('open-sesame', $profile->access_password))->toBeTrue()
        ->and($profile->access_password)->not->toBe('open-sesame');

    $this->actingAs($user)
        ->get(route('bio.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.has_password', true)
            ->missing('profile.access_password'));
});

test('visitors see a password prompt instead of a protected page', function () {
    $profile = Profile::factory()->create([
        'access_password' => Hash::make('secret-page'),
        'is_published' => true,
    ]);

    $this->get(route('bio.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bio/Password')
            ->where('profile.username', $profile->username));
});

test('an incorrect page password is rejected', function () {
    $profile = Profile::factory()->create([
        'access_password' => Hash::make('secret-page'),
        'is_published' => true,
    ]);

    $this->post(route('bio.unlock', $profile->username), [
        'password' => 'not-the-password',
    ])->assertSessionHasErrors('password');

    $this->get(route('bio.show', $profile->username))
        ->assertInertia(fn (Assert $page) => $page->component('bio/Password'));
});

test('the owner can preview their protected page without the password', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'access_password' => Hash::make('secret-page'),
        'is_published' => true,
    ]);

    $this->actingAs($user)
        ->get(route('bio.show', $profile->username))
        ->assertInertia(fn (Assert $page) => $page->component('bio/Show'));
});

test('the correct page password unlocks the page for the session', function () {
    $profile = Profile::factory()->create([
        'access_password' => Hash::make('secret-page'),
        'is_published' => true,
    ]);

    $this->post(route('bio.unlock', $profile->username), [
        'password' => 'secret-page',
    ])->assertRedirect(route('bio.show', $profile->username));

    $this->get(route('bio.show', $profile->username))
        ->assertInertia(fn (Assert $page) => $page->component('bio/Show'));
});

test('protected link redirects require the page password', function () {
    $profile = Profile::factory()->create([
        'access_password' => Hash::make('secret-page'),
        'is_published' => true,
    ]);
    $link = Link::factory()->for($profile)->create([
        'url' => 'https://example.com/destination',
    ]);

    $this->get(route('links.redirect', $link))
        ->assertRedirect(route('bio.show', $profile->username));

    $this->post(route('bio.unlock', $profile->username), [
        'password' => 'secret-page',
    ]);

    $this->get(route('links.redirect', $link))
        ->assertRedirect('https://example.com/destination');
});

test('protected pages reject contact messages before unlock', function () {
    Mail::fake();

    $profile = Profile::factory()->create([
        'access_password' => Hash::make('secret-page'),
        'show_contact_form' => true,
        'is_published' => true,
    ]);

    $this->post(route('bio.contact', $profile->username), [
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'message' => 'Hello',
    ])->assertRedirect(route('bio.show', $profile->username));

    Mail::assertNothingSent();
});

test('owners can remove page protection', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'access_password' => Hash::make('secret-page'),
    ]);

    $this->actingAs($user)
        ->patch(route('bio.update'), profilePagePayload($user, [
            'remove_page_password' => true,
        ]))
        ->assertRedirect(route('bio.edit'))
        ->assertSessionHasNoErrors();

    expect($profile->refresh()->access_password)->toBeNull();
});

test('owners can identify their page as a business type', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create(['is_published' => true]);

    $this->actingAs($user)
        ->patch(route('bio.update'), profilePagePayload($user, [
            'business_type' => 'service',
            'company' => 'The Blue Table',
            'job_title' => 'Head chef',
            'contact_email' => 'hello@bluetable.example',
            'phone' => '+1 555 0101',
            'contact_website' => 'https://bluetable.example',
            'address' => '1 Market Street',
        ]))
        ->assertRedirect(route('bio.edit'))
        ->assertSessionHasNoErrors();

    expect($profile->refresh()->business_type)->toBe('service')
        ->and($profile->company)->toBe('The Blue Table')
        ->and($profile->hasVCardDetails())->toBeTrue();

    $this->get(route('bio.show', $profile->username))
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.business_type', 'Service business')
            ->where('profile.has_vcard', true));
});

test('owners can set seo metadata for their page', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create(['is_published' => true]);

    $this->actingAs($user)
        ->patch(route('bio.update'), profilePagePayload($user, [
            'meta_title' => 'Ada Lovelace — Links and work',
            'meta_description' => 'Find Ada online: portfolio, writing and social profiles.',
        ]))
        ->assertRedirect(route('bio.edit'))
        ->assertSessionHasNoErrors();

    $this->get(route('bio.show', $profile->username))
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.seo_title', 'Ada Lovelace — Links and work')
            ->where('profile.seo_description', 'Find Ada online: portfolio, writing and social profiles.'))
        ->assertSee('<meta name="description" content="Find Ada online: portfolio, writing and social profiles.">', false)
        ->assertSee('<meta property="og:title" content="Ada Lovelace — Links and work">', false);
});
