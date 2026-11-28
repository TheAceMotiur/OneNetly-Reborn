<?php

use App\Enums\PurchaseStatus;
use App\Mail\PurchaseConfirmed;
use App\Models\Plan;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

test('non admins cannot manage plans or purchases', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create();

    $this->actingAs($user)->get(route('admin.plans.index'))->assertForbidden();
    $this->actingAs($user)->post(route('admin.plans.store'), [])->assertForbidden();
    $this->actingAs($user)->get(route('admin.purchases.index'))->assertForbidden();
    $this->actingAs($user)->get(route('admin.analytics'))->assertForbidden();

    expect($plan->refresh())->not->toBeNull();
});

test('admins can create a plan with feature flags', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->post(route('admin.plans.store'), [
        'name' => 'Studio',
        'plan_type' => 'premium',
        'price' => '29.99',
        'currency' => 'usd',
        'features' => ['custom domain', 'analytics'],
        'is_active' => true,
        'has_custom_domain' => true,
        'has_analytics' => true,
        'has_white_label' => true,
        'position' => 3,
    ])->assertRedirect();

    $plan = Plan::where('name', 'Studio')->sole();

    expect($plan->currency)->toBe('USD')
        ->and($plan->features)->toBe(['custom domain', 'analytics'])
        ->and($plan->has_custom_domain)->toBeTrue()
        ->and($plan->has_white_label)->toBeTrue();
});

test('admins can update and delete an unused plan', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $plan = Plan::factory()->create(['name' => 'Old', 'price' => '5.00']);

    $this->actingAs($admin)->patch(route('admin.plans.update', $plan), [
        'name' => 'Renamed',
        'plan_type' => $plan->plan_type,
        'price' => '7.00',
        'currency' => 'USD',
        'features' => ['new feature'],
        'is_active' => false,
        'has_custom_domain' => false,
        'has_analytics' => false,
        'has_white_label' => false,
        'position' => 1,
    ])->assertRedirect();

    expect($plan->refresh()->name)->toBe('Renamed')
        ->and($plan->is_active)->toBeFalse()
        ->and($plan->has_white_label)->toBeFalse();

    $this->actingAs($admin)
        ->delete(route('admin.plans.destroy', $plan))
        ->assertRedirect();

    expect(Plan::find($plan->id))->toBeNull();
});

test('a plan with purchases cannot be deleted', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $plan = Plan::factory()->create();
    Purchase::factory()->create(['plan_id' => $plan->id]);

    $this->actingAs($admin)
        ->delete(route('admin.plans.destroy', $plan))
        ->assertRedirect();

    expect(Plan::find($plan->id))->not->toBeNull();
});

test('admins can confirm a pending purchase', function () {
    Mail::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $purchase = Purchase::factory()->pending()->create();

    $this->actingAs($admin)->patch(route('admin.purchases.update', $purchase), [
        'status' => PurchaseStatus::Completed->value,
        'paypal_transaction_id' => 'PAY-123456',
    ])->assertRedirect();

    $purchase->refresh();

    expect($purchase->status)->toBe(PurchaseStatus::Completed)
        ->and($purchase->paypal_transaction_id)->toBe('PAY-123456')
        ->and($purchase->payment_date)->not->toBeNull();
});

test('the purchase confirmation email renders', function () {
    $purchase = Purchase::factory()->pending()->create();
    $purchase->load(['plan', 'user']);

    expect((new PurchaseConfirmed($purchase))->render())
        ->toContain($purchase->plan->name)
        ->toContain($purchase->displayAmount());
});

test('confirming a purchase emails the buyer only once', function () {
    Mail::fake();

    $admin = User::factory()->create(['is_admin' => true]);
    $purchase = Purchase::factory()->pending()->create();
    $buyer = $purchase->user;

    $this->actingAs($admin)->patch(route('admin.purchases.update', $purchase), [
        'status' => PurchaseStatus::Completed->value,
    ])->assertRedirect();

    Mail::assertSent(PurchaseConfirmed::class, fn (PurchaseConfirmed $mail): bool => $mail->hasTo($buyer->email));

    $this->actingAs($admin)->patch(route('admin.purchases.update', $purchase), [
        'status' => PurchaseStatus::Completed->value,
    ])->assertRedirect();

    expect(Mail::sent(PurchaseConfirmed::class)->count())->toBe(1);
});

test('the admin analytics page reports platform totals', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    Profile::factory()->create(['custom_domain' => 'shop.example.com']);

    $this->actingAs($admin)->get(route('admin.analytics'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/analytics/Index')
            ->where('stats.customDomains', 1)
            ->has('daily', 30));
});

test('admins can assign a custom domain to any page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $profile = Profile::factory()->create();

    $this->actingAs($admin)->patch(route('admin.profiles.update', $profile), [
        'custom_domain' => 'Managed.Example.com',
    ])->assertRedirect();

    expect($profile->refresh()->custom_domain)->toBe('managed.example.com');
});
