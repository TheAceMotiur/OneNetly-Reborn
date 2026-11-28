<?php

use App\Mail\ProfileContactMessage;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

test('the contact form is hidden unless enabled', function () {
    $profile = Profile::factory()->create(['show_contact_form' => false]);

    $this->get(route('bio.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bio/Show')
            ->where('profile.contact_enabled', false));

    $this->post(route('bio.contact', $profile->username), [
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'message' => 'Hello',
    ])->assertNotFound();
});

test('a visitor message is emailed to the page owner', function () {
    Mail::fake();

    $owner = User::factory()->create(['email' => 'owner@example.com']);
    $profile = Profile::factory()->for($owner)->create([
        'show_contact_form' => true,
    ]);

    $this->post(route('bio.contact', $profile->username), [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'message' => 'Hello there!',
        'website' => '',
    ])->assertRedirect();

    Mail::assertSent(ProfileContactMessage::class, function (ProfileContactMessage $mail) use ($owner): bool {
        return $mail->hasTo($owner->email)
            && $mail->senderEmail === 'jane@example.com'
            && $mail->messageBody === 'Hello there!';
    });
});

test('the contact email renders visitor content without raw html', function () {
    $profile = Profile::factory()->create(['show_contact_form' => true]);

    $mail = new ProfileContactMessage(
        senderName: 'Jane Doe',
        senderEmail: 'jane@example.com',
        messageBody: '<script>alert(1)</script>',
        profile: $profile,
    );

    $html = (string) $mail->render();

    expect($html)
        ->toContain('&lt;script&gt;alert(1)&lt;/script&gt;')
        ->not->toContain('<script>alert(1)</script>');
});

test('contact messages are validated', function () {
    Mail::fake();

    $profile = Profile::factory()->create(['show_contact_form' => true]);

    $this->post(route('bio.contact', $profile->username), [
        'name' => '',
        'email' => 'not-an-email',
        'message' => '',
    ])->assertSessionHasErrors(['name', 'email', 'message']);

    Mail::assertNothingSent();
});

test('honeypot submissions are silently ignored', function () {
    Mail::fake();

    $profile = Profile::factory()->create(['show_contact_form' => true]);

    $this->post(route('bio.contact', $profile->username), [
        'name' => 'Spam',
        'email' => 'spam@example.com',
        'message' => 'Buy now',
        'website' => 'http://spam.test',
    ])->assertRedirect();

    Mail::assertNothingSent();
});

test('hidden pages cannot receive messages', function () {
    Mail::fake();

    $profile = Profile::factory()->unpublished()->create([
        'show_contact_form' => true,
    ]);

    $this->post(route('bio.contact', $profile->username), [
        'name' => 'Jane',
        'email' => 'jane@example.com',
        'message' => 'Hello',
    ])->assertNotFound();

    Mail::assertNothingSent();
});

test('owners can enable the contact form from the page editor', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'show_contact_form' => false,
    ]);

    $this->actingAs($user)->patch(route('bio.update'), [
        'username' => $profile->username,
        'display_name' => $profile->display_name,
        'theme' => $profile->theme,
        'template' => $profile->template,
        'is_published' => true,
        'show_contact_form' => true,
    ])->assertRedirect(route('bio.edit'));

    expect($profile->refresh()->show_contact_form)->toBeTrue();
});
