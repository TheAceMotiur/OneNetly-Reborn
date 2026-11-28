<?php

use App\Mail\ProfileContactMessage;
use App\Models\ContactMessage;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

test('contact form messages are stored and emailed', function () {
    Mail::fake();

    $profile = Profile::factory()->create([
        'is_published' => true,
        'show_contact_form' => true,
    ]);

    $this->post(route('bio.contact', $profile->username), [
        'name' => 'Maya Patel',
        'email' => 'maya@example.com',
        'message' => 'Do you cater for birthdays?',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $message = ContactMessage::query()->sole();

    expect($message->name)->toBe('Maya Patel')
        ->and($message->message)->toBe('Do you cater for birthdays?')
        ->and($message->isRead())->toBeFalse();

    Mail::assertSent(ProfileContactMessage::class);
});

test('owners can read and delete inbox messages', function () {
    $owner = User::factory()->create();
    $profile = Profile::factory()->for($owner)->create();
    $message = ContactMessage::factory()->for($profile)->create();

    $this->actingAs($owner)
        ->get(route('inbox.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('inbox/Index')
            ->has('messages', 1)
            ->where('unread', 1));

    $this->actingAs($owner)
        ->patch(route('inbox.update', $message))
        ->assertRedirect();

    expect($message->refresh()->isRead())->toBeTrue();

    $this->actingAs($owner)
        ->delete(route('inbox.destroy', $message))
        ->assertRedirect();

    expect(ContactMessage::query()->count())->toBe(0);
});

test('other users cannot read a message', function () {
    $message = ContactMessage::factory()->create();

    $this->actingAs(User::factory()->create())
        ->patch(route('inbox.update', $message))
        ->assertForbidden();
});
