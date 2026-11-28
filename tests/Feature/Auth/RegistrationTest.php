<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('registration screen can be rendered', function () {
    $response = $this->get(route('register'));

    $response->assertOk();
});

test('new users can register', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
    ]);

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('registration requires accepting the terms', function () {
    $response = $this->post(route('register.store'), [
        'name' => 'No Consent',
        'email' => 'no-consent@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertSessionHasErrors('terms');

    $this->assertGuest();
});

test('registration records when the terms were accepted', function () {
    $this->post(route('register.store'), [
        'name' => 'Consenting User',
        'email' => 'consenting@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
    ]);

    expect(User::where('email', 'consenting@example.com')->sole()->terms_accepted_at)->not->toBeNull();
});

test('registration sends an email verification notification', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Verify Me',
        'email' => 'verify-me@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
    ]);

    $user = User::where('email', 'verify-me@example.com')->sole();

    Notification::assertSentTo($user, VerifyEmail::class);
});
