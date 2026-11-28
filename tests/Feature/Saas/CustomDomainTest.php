<?php

use App\Models\Plan;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;
use App\Services\DomainVerifier;
use Inertia\Testing\AssertableInertia as Assert;

function domainUser(): User
{
    $user = User::factory()->create();
    Profile::factory()->for($user)->create(['username' => 'domain-owner']);

    return $user->refresh();
}

function bioPayload(User $user, array $overrides = []): array
{
    return array_merge([
        'username' => $user->profile->username,
        'display_name' => 'Domain Tester',
        'theme' => 'indigo',
        'template' => 'classic',
        'is_published' => true,
        'custom_domain' => 'shop.example.com',
    ], $overrides);
}

test('free users cannot save a custom domain', function () {
    $user = domainUser();

    $this->actingAs($user)
        ->patch(route('bio.update'), bioPayload($user))
        ->assertRedirect(route('bio.edit'));

    expect($user->refresh()->profile->custom_domain)->toBeNull();
});

test('a pro purchase allows saving a custom domain', function () {
    $user = domainUser();
    $plan = Plan::factory()->create(['plan_type' => 'pro', 'has_custom_domain' => true]);
    Purchase::factory()->for($user)->create(['plan_id' => $plan->id]);

    $this->actingAs($user)
        ->patch(route('bio.update'), bioPayload($user))
        ->assertRedirect(route('bio.edit'));

    expect($user->refresh()->profile->custom_domain)->toBe('shop.example.com');
});

test('custom domains must be valid host names', function () {
    $user = domainUser();
    $plan = Plan::factory()->create(['plan_type' => 'pro', 'has_custom_domain' => true]);
    Purchase::factory()->for($user)->create(['plan_id' => $plan->id]);

    $this->actingAs($user)
        ->patch(route('bio.update'), bioPayload($user, ['custom_domain' => 'https://not-a-host']))
        ->assertSessionHasErrors('custom_domain');
});

test('saving a domain creates a verification record', function () {
    $user = domainUser();
    $plan = Plan::factory()->create(['plan_type' => 'pro', 'has_custom_domain' => true]);
    Purchase::factory()->for($user)->create(['plan_id' => $plan->id]);

    $this->actingAs($user)
        ->patch(route('bio.update'), bioPayload($user))
        ->assertRedirect(route('bio.edit'));

    $profile = $user->refresh()->profile;

    expect($profile->custom_domain_token)->not->toBeNull()
        ->and($profile->custom_domain_verified_at)->toBeNull()
        ->and(app(DomainVerifier::class)->recordValue($profile))
        ->toBe(DomainVerifier::RECORD_PREFIX.$profile->custom_domain_token);
});

test('an owner can verify a domain after publishing the TXT record', function () {
    $user = domainUser();
    $plan = Plan::factory()->create(['plan_type' => 'pro', 'has_custom_domain' => true]);
    Purchase::factory()->for($user)->create(['plan_id' => $plan->id]);

    $user->profile->forceFill([
        'custom_domain' => 'shop.example.com',
        'custom_domain_token' => 'verify-token',
    ])->save();

    $this->mock(DomainVerifier::class, function ($mock): void {
        $mock->shouldReceive('verify')->once()->andReturnTrue();
    });

    $this->actingAs($user)
        ->post(route('bio.domain.verify'))
        ->assertRedirect();
});

test('a published verified profile is served on its custom domain', function () {
    Profile::factory()->create([
        'username' => 'on-domain',
        'custom_domain' => 'shop.example.com',
        'custom_domain_token' => 'token',
        'custom_domain_verified_at' => now(),
        'is_published' => true,
    ]);

    $this->get('http://shop.example.com/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bio/Show')
            ->where('profile.username', 'on-domain'));
});

test('hidden profiles are not served on their custom domain', function () {
    Profile::factory()->create([
        'custom_domain' => 'hidden.example.com',
        'custom_domain_verified_at' => now(),
        'is_published' => false,
    ]);

    $this->get('http://hidden.example.com/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});

test('unverified custom domains are not served', function () {
    Profile::factory()->create([
        'custom_domain' => 'pending.example.com',
        'custom_domain_token' => 'token',
        'is_published' => true,
    ]);

    $this->get('http://pending.example.com/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});
