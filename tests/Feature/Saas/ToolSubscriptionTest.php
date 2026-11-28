<?php

use App\Enums\PurchaseStatus;
use App\Models\AutomationActivity;
use App\Models\Plan;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\Subscriber;
use App\Models\User;
use App\Services\EmailMarketingNotifier;
use App\Services\WhatsAppNotifier;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Give the user an active subscription to the given plan.
 */
function subscribeUserTo(User $user, Plan $plan): Purchase
{
    return Purchase::factory()->for($user)->create([
        'plan_id' => $plan->id,
        'status' => PurchaseStatus::Completed,
    ]);
}

test('a tool workspace redirects to pricing without a subscription', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->get(route('whatsapp.index'))
        ->assertRedirect(route('pricing.index'));

    $this->actingAs($user)
        ->get(route('email-marketing.index'))
        ->assertRedirect(route('pricing.index'));
});

test('buying one tool unlocks only that tool', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();
    $plan = Plan::factory()->tool('whatsapp')->create();

    subscribeUserTo($user, $plan);

    $this->actingAs($user)
        ->get(route('whatsapp.index'))
        ->assertOk();

    $this->actingAs($user)
        ->get(route('twitter.index'))
        ->assertRedirect(route('pricing.index'));

    expect($user->fresh()->subscribedTools())->toBe(['whatsapp'])
        ->and($user->fresh()->hasToolAccess('whatsapp'))->toBeTrue()
        ->and($user->fresh()->hasToolAccess('twitter'))->toBeFalse();
});

test('two separate tool subscriptions unlock both tools', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    subscribeUserTo($user, Plan::factory()->tool('whatsapp')->create());
    subscribeUserTo($user, Plan::factory()->tool('twitter')->create());

    expect($user->fresh()->subscribedTools())->toBe(['whatsapp', 'twitter']);

    $this->actingAs($user)->get(route('whatsapp.index'))->assertOk();
    $this->actingAs($user)->get(route('twitter.index'))->assertOk();
    $this->actingAs($user)->get(route('instagram.index'))->assertRedirect(route('pricing.index'));
});

test('the bundle unlocks every tool', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    subscribeUserTo($user, Plan::factory()->bundle()->create());

    foreach (array_keys(Plan::TOOLS) as $tool) {
        expect($user->fresh()->hasToolAccess($tool))->toBeTrue();
    }

    $this->actingAs($user)->get(route('email-marketing.index'))->assertOk();
});

test('tool subscriptions do not change the website plan limits', function () {
    $user = User::factory()->create();
    Profile::factory()->for($user)->create();

    subscribeUserTo($user, Plan::factory()->tool('whatsapp')->create());

    expect($user->fresh()->currentPlan())->toBeNull()
        ->and($user->fresh()->hasPlanFeature('has_analytics'))->toBeFalse();
});

test('the sidebar only lists subscribed tools', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('subscribedTools', []));

    subscribeUserTo($user, Plan::factory()->tool('facebook')->create());

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('subscribedTools', ['facebook']));
});

test('the pricing page lists tools separately from bundles and website plans', function () {
    Plan::factory()->tool('whatsapp')->create(['position' => 0]);
    Plan::factory()->bundle()->create(['position' => 1]);
    Plan::factory()->create(['plan_type' => 'pro', 'position' => 2]);

    $this->get(route('pricing.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('plans', 3)
            ->where('plans.0.is_tool', true)
            ->where('plans.0.tool', 'whatsapp')
            ->where('plans.1.is_bundle', true)
            ->where('plans.2.is_tool', false)
            ->where('plans.2.is_bundle', false));
});

test('an expired tool subscription closes the workspace', function () {
    $user = User::factory()->withAllTools()->create();
    Profile::factory()->for($user)->create();

    Purchase::query()->where('user_id', $user->id)->update(['expires_at' => now()->subDay()]);

    $this->actingAs($user->fresh())
        ->get(route('whatsapp.index'))
        ->assertRedirect(route('pricing.index'));
});

test('automations stop running once the tool subscription lapses', function () {
    Http::fake();
    Mail::fake();

    $user = User::factory()->withAllTools()->create();
    $profile = Profile::factory()->for($user)->create([
        'business_type' => Profile::RESTAURANT_TYPE,
    ]);

    $user->update([
        'whatsapp_enabled' => true,
        'whatsapp_phone_number_id' => '1234567890',
        'whatsapp_access_token' => 'test-token',
        'whatsapp_order_updates' => true,
        'email_marketing_enabled' => true,
    ]);

    Purchase::query()->where('user_id', $user->id)->update(['expires_at' => now()->subDay()]);

    $subscriber = Subscriber::factory()->for($profile)->create();

    app(WhatsAppNotifier::class)->welcome($profile, '+15550002222', 'Ada');
    app(EmailMarketingNotifier::class)->welcome($subscriber);

    expect($user->fresh()->hasToolAccess('whatsapp'))->toBeFalse()
        ->and($user->fresh()->hasToolAccess('email'))->toBeFalse()
        ->and(AutomationActivity::query()->count())->toBe(0);

    Http::assertNothingSent();
    Mail::assertNothingSent();
});

test('an active tool cannot be bought twice', function () {
    config()->set('services.paypal.me_username', 'owner');

    $user = User::factory()->create();
    $plan = Plan::factory()->tool('whatsapp')->create();

    subscribeUserTo($user, $plan);

    $this->actingAs($user)
        ->post(route('purchase.store'), ['plan_id' => $plan->id])
        ->assertRedirect(route('pricing.index'));

    expect(Purchase::query()->where('plan_id', $plan->id)->count())->toBe(1);
});
