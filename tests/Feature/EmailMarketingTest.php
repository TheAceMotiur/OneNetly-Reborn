<?php

use App\Enums\PurchaseStatus;
use App\Mail\SubscriberBroadcast;
use App\Models\AutomationActivity;
use App\Models\EmailCampaign;
use App\Models\Order;
use App\Models\Plan;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\Reservation;
use App\Models\Subscriber;
use App\Models\User;
use Illuminate\Support\Facades\Mail;

/**
 * @param  array<string, mixed>  $attributes
 */
function emailMarketingProfile(array $attributes = []): Profile
{
    $credentialKeys = ['email_marketing_enabled', 'email_reply_to', 'email_footer'];

    $profile = Profile::factory()->create([
        'business_type' => Profile::RESTAURANT_TYPE,
        'is_published' => true,
        ...array_diff_key($attributes, array_flip($credentialKeys)),
    ]);

    User::query()->findOrFail($profile->user_id)->update([
        'email_marketing_enabled' => true,
        ...array_intersect_key($attributes, array_flip($credentialKeys)),
    ]);

    Purchase::factory()
        ->for(User::query()->findOrFail($profile->user_id))
        ->create([
            'plan_id' => Plan::factory()->tool('email')->create()->id,
            'status' => PurchaseStatus::Completed,
        ]);

    return $profile;
}

test('the email marketing page shows stats, campaigns and the welcome automation', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = emailMarketingProfile(['user_id' => $owner->id]);

    Subscriber::factory()->for($profile)->count(2)->create();
    EmailCampaign::factory()->for($profile)->create(['status' => 'sent', 'sent_count' => 2]);

    $this->actingAs($owner)
        ->get(route('email-marketing.index', ['restaurant' => $profile->username]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('email-marketing/Index')
            ->where('profile.username', $profile->username)
            ->where('stats.subscribers', 2)
            ->where('stats.campaigns', 1)
            ->where('automation.is_enabled', true)
            ->has('subscribers', 2));
});

test('guests cannot open email marketing', function () {
    $this->get(route('email-marketing.index'))->assertRedirect(route('login'));
});

test('owners can save campaign drafts and send them to every subscriber', function () {
    Mail::fake();

    $owner = User::factory()->withAllTools()->create();
    $profile = emailMarketingProfile(['user_id' => $owner->id]);

    Subscriber::factory()->for($profile)->count(2)->create();

    $this->actingAs($owner)
        ->post(route('email-marketing.campaigns.store', ['restaurant' => $profile->username]), [
            'name' => 'Weekend special',
            'subject' => '20% off this weekend',
            'body' => 'Join us for dinner and save 20%.',
        ])
        ->assertRedirect();

    $campaign = $profile->emailCampaigns()->where('name', 'Weekend special')->first();

    expect($campaign?->status)->toBe('draft');

    $this->actingAs($owner)
        ->post(route('email-marketing.campaigns.send', [
            'restaurant' => $profile->username,
            'campaign' => $campaign?->id,
        ]))
        ->assertRedirect();

    Mail::assertSent(SubscriberBroadcast::class, 2);

    expect($campaign?->refresh()->status)->toBe('sent')
        ->and($campaign?->sent_count)->toBe(2)
        ->and($campaign?->failed_count)->toBe(0);
});

test('a campaign cannot be sent from another page', function () {
    Mail::fake();

    $owner = User::factory()->withAllTools()->create();
    $profile = emailMarketingProfile(['user_id' => $owner->id]);

    $otherProfile = emailMarketingProfile();
    $campaign = EmailCampaign::factory()->for($otherProfile)->create();

    $this->actingAs($owner)
        ->post(route('email-marketing.campaigns.send', [
            'restaurant' => $profile->username,
            'campaign' => $campaign->id,
        ]))
        ->assertNotFound();

    Mail::assertNothingSent();
});

test('adding a subscriber sends the welcome email', function () {
    Mail::fake();

    $owner = User::factory()->withAllTools()->create();
    $profile = emailMarketingProfile(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->post(route('email-marketing.subscribers.store', ['restaurant' => $profile->username]), [
            'email' => 'New@Example.com',
        ])
        ->assertRedirect();

    expect(Subscriber::query()->where('email', 'new@example.com')->exists())->toBeTrue();

    Mail::assertSent(SubscriberBroadcast::class, fn (SubscriberBroadcast $mail) => $mail->hasTo('new@example.com')
        && $mail->subjectLine === 'Welcome to '.$profile->restaurantName());

    expect(AutomationActivity::query()
        ->where('tool', 'email')
        ->where('status', 'sent')
        ->where('contact', 'new@example.com')
        ->exists())->toBeTrue();
});

test('the welcome email is not sent when marketing is paused', function () {
    Mail::fake();

    $owner = User::factory()->withAllTools()->create();
    $profile = emailMarketingProfile([
        'user_id' => $owner->id,
        'email_marketing_enabled' => false,
    ]);

    $this->actingAs($owner)
        ->post(route('email-marketing.subscribers.store', ['restaurant' => $profile->username]), [
            'email' => 'paused@example.com',
        ])
        ->assertRedirect();

    expect(Subscriber::query()->where('email', 'paused@example.com')->exists())->toBeTrue();

    Mail::assertNothingSent();
});

test('public page signups receive the welcome email', function () {
    Mail::fake();

    $profile = emailMarketingProfile();

    $this->post(route('subscribe', $profile->username), [
        'email' => 'visitor@example.com',
    ])->assertRedirect();

    expect(Subscriber::query()->where('email', 'visitor@example.com')->exists())->toBeTrue();

    Mail::assertSent(SubscriberBroadcast::class, fn (SubscriberBroadcast $mail) => $mail->hasTo('visitor@example.com'));
});

test('subscribers can be imported from orders and bookings', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = emailMarketingProfile(['user_id' => $owner->id]);

    Order::factory()->for($profile)->create(['customer_email' => 'Order@Example.com']);
    Reservation::factory()->for($profile)->create(['email' => 'booking@example.com']);

    $this->actingAs($owner)
        ->post(route('email-marketing.subscribers.sync', ['restaurant' => $profile->username]))
        ->assertRedirect();

    expect(Subscriber::query()->where('email', 'order@example.com')->where('source', 'order')->exists())->toBeTrue()
        ->and(Subscriber::query()->where('email', 'booking@example.com')->where('source', 'reservation')->exists())->toBeTrue();
});

test('owners can update the welcome automation', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = emailMarketingProfile(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->put(route('email-marketing.automations.update', ['restaurant' => $profile->username]), [
            'is_enabled' => false,
            'subject' => 'Thanks for joining {page}',
            'body' => 'Hello, welcome to {page}. This address is {email}.',
        ])
        ->assertRedirect();

    $automation = $profile->emailAutomations()->where('key', 'welcome')->first();

    expect($automation?->is_enabled)->toBeFalse()
        ->and($automation?->subject)->toBe('Thanks for joining {page}');
});

test('owners can save reply-to and footer settings', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = emailMarketingProfile(['user_id' => $owner->id]);

    $this->actingAs($owner)
        ->put(route('email-marketing.settings.update', ['restaurant' => $profile->username]), [
            'enabled' => true,
            'reply_to' => 'hello@example.com',
            'footer' => 'Open daily from 9am to 10pm.',
        ])
        ->assertRedirect();

    $owner->refresh();

    expect($owner->email_reply_to)->toBe('hello@example.com')
        ->and($owner->email_footer)->toBe('Open daily from 9am to 10pm.');
});

test('owners can remove subscribers, delete campaigns and export the audience', function () {
    $owner = User::factory()->withAllTools()->create();
    $profile = emailMarketingProfile(['user_id' => $owner->id]);

    $subscriber = Subscriber::factory()->for($profile)->create();
    $campaign = EmailCampaign::factory()->for($profile)->create();

    $this->actingAs($owner)
        ->get(route('email-marketing.subscribers.export', ['restaurant' => $profile->username]))
        ->assertOk()
        ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

    $this->actingAs($owner)
        ->delete(route('email-marketing.subscribers.destroy', [
            'restaurant' => $profile->username,
            'subscriber' => $subscriber->id,
        ]))
        ->assertRedirect();

    $this->actingAs($owner)
        ->delete(route('email-marketing.campaigns.destroy', [
            'restaurant' => $profile->username,
            'campaign' => $campaign->id,
        ]))
        ->assertRedirect();

    expect(Subscriber::query()->whereKey($subscriber->id)->exists())->toBeFalse()
        ->and(EmailCampaign::query()->whereKey($campaign->id)->exists())->toBeFalse();
});
