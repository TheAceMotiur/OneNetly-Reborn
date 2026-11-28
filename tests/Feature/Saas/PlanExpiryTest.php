<?php

use App\Enums\PurchaseStatus;
use App\Models\Plan;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

test('confirming a timed purchase records when access ends', function () {
    Mail::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $plan = Plan::factory()->create(['plan_type' => 'pro', 'duration_days' => 30]);
    $purchase = Purchase::factory()->pending()->create(['plan_id' => $plan->id]);

    $this->actingAs($admin)->patch(route('admin.purchases.update', $purchase), [
        'status' => PurchaseStatus::Completed->value,
    ])->assertRedirect();

    $purchase->refresh();

    expect($purchase->expires_at)->not->toBeNull()
        ->and($purchase->expires_at->greaterThan(now()->addDays(29)))->toBeTrue()
        ->and($purchase->isActive())->toBeTrue();
});

test('a lifetime purchase never expires', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['plan_type' => 'pro', 'max_links' => null, 'duration_days' => null]);

    Purchase::factory()->for($user)->create([
        'plan_id' => $plan->id,
        'status' => PurchaseStatus::Completed,
        'expires_at' => null,
    ]);

    expect($user->currentPlan()?->id)->toBe($plan->id);
});

test('expired purchases stop granting the plan', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create([
        'plan_type' => 'pro',
        'max_links' => null,
        'has_analytics' => true,
    ]);

    Purchase::factory()->for($user)->create([
        'plan_id' => $plan->id,
        'status' => PurchaseStatus::Completed,
        'expires_at' => now()->subDay(),
    ]);

    expect($user->currentPlan())->toBeNull()
        ->and($user->hasPlanFeature('has_analytics'))->toBeFalse()
        ->and($user->linkLimit())->toBe(5);
});

test('admins can override the access end date', function () {
    Mail::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $purchase = Purchase::factory()->pending()->create();

    $this->actingAs($admin)->patch(route('admin.purchases.update', $purchase), [
        'status' => PurchaseStatus::Completed->value,
        'expires_at' => '2027-01-31',
    ])->assertRedirect();

    expect($purchase->refresh()->expires_at?->toDateString())->toBe('2027-01-31');
});

test('the pricing page shows how long access lasts', function () {
    Plan::factory()->create(['plan_type' => 'pro', 'duration_days' => 30]);

    $this->get(route('pricing.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('pricing/Index')
            ->where('plans.0.duration_label', '30 days of access'));
});
