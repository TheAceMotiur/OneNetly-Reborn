<?php

use App\Enums\PurchaseStatus;
use App\Models\Plan;
use App\Models\Purchase;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('the pricing page lists active plans only', function () {
    Plan::factory()->create(['name' => 'Visible', 'is_active' => true, 'position' => 0]);
    Plan::factory()->create(['name' => 'Hidden', 'is_active' => false, 'position' => 1]);

    $this->get(route('pricing.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('pricing/Index')
            ->has('plans', 1)
            ->where('plans.0.name', 'Visible'));
});

test('guests cannot start a purchase', function () {
    $plan = Plan::factory()->create();

    $this->post(route('purchase.store'), ['plan_id' => $plan->id])
        ->assertRedirect(route('login'));

    expect(Purchase::count())->toBe(0);
});

test('a paid plan purchase is recorded and redirects to paypal me', function () {
    config()->set('services.paypal.me_username', 'owner');
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['price' => '9.99', 'currency' => 'USD']);

    $this->actingAs($user)
        ->post(route('purchase.store'), ['plan_id' => $plan->id])
        ->assertRedirect('https://paypal.me/owner/9.99USD');

    $purchase = Purchase::sole();

    expect($purchase->user_id)->toBe($user->id)
        ->and($purchase->plan_id)->toBe($plan->id)
        ->and($purchase->status)->toBe(PurchaseStatus::Pending);
});

test('a free plan does not create a purchase', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['price' => '0']);

    $this->actingAs($user)
        ->post(route('purchase.store'), ['plan_id' => $plan->id])
        ->assertRedirect(route('pricing.index'));

    expect(Purchase::count())->toBe(0);
});

test('a purchase cannot start when paypal is not configured', function () {
    config()->set('services.paypal.me_username', null);
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['price' => '9.99']);

    $this->actingAs($user)
        ->post(route('purchase.store'), ['plan_id' => $plan->id])
        ->assertRedirect(route('pricing.index'));

    expect(Purchase::count())->toBe(0);
});

test('the buyer sees the return page and others cannot', function () {
    $buyer = User::factory()->create();
    $other = User::factory()->create();
    $purchase = Purchase::factory()->for($buyer)->pending()->create();

    $this->actingAs($buyer)
        ->get(route('purchase.return', $purchase))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('purchases/Return'));

    $this->actingAs($other)
        ->get(route('purchase.return', $purchase))
        ->assertForbidden();
});

test('a confirmed purchase unlocks the plan features', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create([
        'plan_type' => 'pro',
        'has_analytics' => true,
        'has_custom_domain' => true,
    ]);

    Purchase::factory()->for($user)->create([
        'plan_id' => $plan->id,
        'status' => PurchaseStatus::Completed,
    ]);

    expect($user->hasPlanFeature('has_analytics'))->toBeTrue()
        ->and($user->hasPlanFeature('has_custom_domain'))->toBeTrue();
});
