<?php

use App\Mail\ProfileInvitationMail;
use App\Models\Profile;
use App\Models\ProfileInvitation;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Laravel\Fortify\Features;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::registration());
});

test('inviting an email without an account sends an invitation', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->restaurant()->create();

    $this->actingAs($owner)
        ->post(route('restaurant.team.store', $profile), [
            'email' => 'New-Staff@Example.com',
            'role' => 'staff',
        ])
        ->assertRedirect();

    $invitation = ProfileInvitation::query()->sole();

    expect($invitation->email)->toBe('new-staff@example.com')
        ->and($invitation->role)->toBe('staff')
        ->and($invitation->isPending())->toBeTrue();

    Mail::assertSent(
        ProfileInvitationMail::class,
        fn (ProfileInvitationMail $mail): bool => $mail->hasTo('new-staff@example.com'),
    );
});

test('registering with an invited email grants access automatically', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->restaurant()->create();

    ProfileInvitation::factory()->create([
        'profile_id' => $profile->id,
        'email' => 'invited@example.com',
        'role' => 'manager',
        'invited_by' => $owner->id,
    ]);

    $this->post(route('register.store'), [
        'name' => 'Invited User',
        'email' => 'invited@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'terms' => '1',
    ]);

    $user = User::query()->where('email', 'invited@example.com')->sole();

    expect($profile->members()->whereKey($user->id)->exists())->toBeTrue()
        ->and($profile->roleFor($user))->toBe('manager');
});

test('an existing user accepts an invitation link', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->restaurant()->create();
    $invitee = User::factory()->create(['email' => 'known@example.com']);

    $invitation = ProfileInvitation::factory()->create([
        'profile_id' => $profile->id,
        'email' => 'known@example.com',
        'role' => 'editor',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($invitee)
        ->get(route('invitations.accept', $invitation->token))
        ->assertRedirect(route('dashboard'));

    expect($profile->roleFor($invitee))->toBe('editor')
        ->and($invitation->refresh()->accepted_at)->not->toBeNull();
});

test('a different user cannot accept an invitation', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->restaurant()->create();
    $intruder = User::factory()->create(['email' => 'intruder@example.com']);

    $invitation = ProfileInvitation::factory()->create([
        'profile_id' => $profile->id,
        'email' => 'invited@example.com',
        'role' => 'manager',
        'invited_by' => $owner->id,
    ]);

    $this->actingAs($intruder)
        ->get(route('invitations.accept', $invitation->token))
        ->assertRedirect(route('dashboard'));

    expect($profile->members()->whereKey($intruder->id)->exists())->toBeFalse()
        ->and($invitation->refresh()->accepted_at)->toBeNull();
});

test('guests are sent to login with the invited email prefilled', function () {
    $invitation = ProfileInvitation::factory()->create(['email' => 'invited@example.com']);

    $this->get(route('invitations.accept', $invitation->token))
        ->assertRedirect(route('login', ['email' => 'invited@example.com']));
});

test('pages can invite collaborators by email', function () {
    Mail::fake();

    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->create();

    $this->actingAs($owner)
        ->post(route('bio.collaborators.store'), [
            'email' => 'editor@example.com',
            'role' => 'editor',
        ])
        ->assertRedirect();

    expect(ProfileInvitation::query()->sole()->role)->toBe('editor');

    Mail::assertSent(ProfileInvitationMail::class);
});
