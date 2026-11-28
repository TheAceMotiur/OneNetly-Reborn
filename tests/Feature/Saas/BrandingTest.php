<?php

use App\Enums\PurchaseStatus;
use App\Models\Plan;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\Resume;
use App\Models\User;
use App\Models\Widget;
use Carbon\CarbonInterface;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Give the user an active pro subscription that removes the branding.
 */
function grantBrandingRemoval(User $user, ?CarbonInterface $expiresAt = null): Plan
{
    $plan = Plan::factory()->create([
        'plan_type' => 'pro',
        'has_white_label' => true,
        'duration_days' => 30,
    ]);

    Purchase::factory()->create([
        'user_id' => $user->id,
        'plan_id' => $plan->id,
        'status' => PurchaseStatus::Completed,
        'expires_at' => $expiresAt ?? now()->addMonth(),
    ]);

    return $plan;
}

test('free accounts show the credit on their bio page', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create(['is_published' => true]);

    expect($user->canRemoveBranding())->toBeFalse();

    $this->get(route('bio.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('bio/Show')
            ->where('profile.show_branding', true));
});

test('subscribers hide the credit from their bio page', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create(['is_published' => true]);

    grantBrandingRemoval($user);

    expect($user->fresh()->canRemoveBranding())->toBeTrue();

    $this->get(route('bio.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.show_branding', false));
});

test('an expired subscription brings the bio credit back', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create(['is_published' => true]);

    grantBrandingRemoval($user, now()->subDay());

    $this->get(route('bio.show', $profile->username))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.show_branding', true));
});

test('free accounts get a watermark on their resumes', function () {
    $user = User::factory()->create();
    $resume = Resume::factory()->for($user)->create();

    $this->actingAs($user)->get(route('resumes.edit', $resume))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resumes/Edit')
            ->where('resume.watermark', true));
});

test('subscribers get clean resumes without a watermark', function () {
    $user = User::factory()->create();
    $resume = Resume::factory()->for($user)->create();

    grantBrandingRemoval($user);

    $this->actingAs($user)->get(route('resumes.edit', $resume))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('resume.watermark', false));
});

test('free accounts get a credit in their widget embed code', function () {
    $user = User::factory()->create();
    $widget = Widget::factory()->ofType('social-share')->for($user)->create();

    $embed = $this->get($widget->embedUrl());

    $embed->assertOk();

    expect($widget->previewDocument())->toContain('Made with')
        ->and($embed->getContent())->toContain('Made with');

    $this->actingAs($user)
        ->postJson(route('widgets.preview'), ['type' => 'social-share', 'config' => []])
        ->assertOk()
        ->assertJsonPath('watermark', true);
});

test('subscribers get widget code without the credit', function () {
    $user = User::factory()->create();
    $widget = Widget::factory()->ofType('social-share')->for($user)->create();

    grantBrandingRemoval($user);

    $fresh = Widget::query()->findOrFail($widget->id);
    $embed = $this->get($fresh->embedUrl());

    $embed->assertOk();

    expect($fresh->previewDocument())->not->toContain('Made with')
        ->and($embed->getContent())->not->toContain('Made with');

    $this->actingAs($user)
        ->postJson(route('widgets.preview'), ['type' => 'social-share', 'config' => []])
        ->assertOk()
        ->assertJsonPath('watermark', false);
});

test('administrators never see the branding', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    expect($admin->canRemoveBranding())->toBeTrue();
});
