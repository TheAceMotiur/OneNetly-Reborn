<?php

use App\Models\Plan;
use App\Models\Profile;
use App\Models\Purchase;
use App\Models\User;
use App\Services\MailConfiguration;
use App\Services\SettingsService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function settingsPayload(array $overrides = []): array
{
    return array_merge([
        'site_name' => 'OneNetly',
        'support_email' => 'help@onenetly.com',
        'footer_text' => 'One link for everything you do online.',
        'meta_description' => 'A bio link platform for creators.',
        'payments_enabled' => true,
        'paypal_me_username' => 'onenetly',
        'payment_note' => 'Include your account email in the PayPal note.',
        'custom_domains_enabled' => true,
        'analytics_enabled' => true,
        'mail_host' => '',
        'mail_port' => 587,
        'mail_encryption' => 'tls',
        'mail_username' => '',
        'mail_password' => '',
        'mail_from_name' => 'OneNetly',
        'mail_from_address' => 'hello@onenetly.com',
    ], $overrides);
}

test('non admins cannot manage settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.settings.edit'))
        ->assertForbidden();

    $this->actingAs($user)
        ->patch(route('admin.settings.update'), settingsPayload())
        ->assertForbidden();
});

test('admins can view settings', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/settings/Index')
            ->where('settings.site_name', 'OneNetly')
            ->has('groups.general')
            ->has('groups.payments')
            ->has('groups.features')
            ->has('groups.email'));
});

test('admins can update settings', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.settings.update'), settingsPayload([
            'site_name' => 'LinkHub',
            'payments_enabled' => false,
            'paypal_me_username' => 'linkhub',
            'custom_domains_enabled' => false,
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $settings = app(SettingsService::class);

    expect($settings->get('site_name'))->toBe('LinkHub')
        ->and($settings->bool('payments_enabled'))->toBeFalse()
        ->and($settings->bool('custom_domains_enabled'))->toBeFalse()
        ->and($settings->get('paypal_me_username'))->toBe('linkhub');

    $this->assertDatabaseHas('settings', [
        'key' => 'site_name',
        'value' => 'LinkHub',
    ]);
});

test('settings are validated', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.settings.update'), settingsPayload([
            'site_name' => '',
            'mail_from_address' => 'not-an-email',
            'paypal_me_username' => 'not a username!',
        ]))
        ->assertSessionHasErrors([
            'site_name',
            'mail_from_address',
            'paypal_me_username',
        ]);
});

test('the site name is shared across pages', function () {
    app(SettingsService::class)->set('site_name', 'LinkHub');

    $this->get(route('home'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('name', 'LinkHub'));
});

test('the browser tab title uses the configured site name', function () {
    app(SettingsService::class)->set('site_name', 'LinkHub');

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('<title>LinkHub</title>', false)
        ->assertDontSee('OneNetly')
        ->assertInertia(fn (Assert $page) => $page->where('name', 'LinkHub'));
});

test('smtp settings switch mail to the phpmailer driver', function () {
    app(SettingsService::class)->setMany([
        'mail_host' => 'smtp.mailprovider.com',
        'mail_port' => 465,
        'mail_encryption' => 'ssl',
        'mail_username' => 'mailer@onenetly.com',
        'mail_password' => 'smtp-secret',
    ]);

    app(MailConfiguration::class)->apply(app(SettingsService::class));

    expect(config('mail.default'))->toBe('phpmailer')
        ->and(config('mail.mailers.phpmailer.host'))->toBe('smtp.mailprovider.com')
        ->and(config('mail.mailers.phpmailer.port'))->toBe(465)
        ->and(config('mail.mailers.phpmailer.encryption'))->toBe('ssl')
        ->and(config('mail.mailers.phpmailer.username'))->toBe('mailer@onenetly.com')
        ->and(config('mail.mailers.phpmailer.password'))->toBe('smtp-secret');
});

test('smtp settings without a host keep the environment mailer', function () {
    app(SettingsService::class)->setMany([
        'mail_host' => '',
        'mail_password' => 'smtp-secret',
    ]);

    app(MailConfiguration::class)->apply(app(SettingsService::class));

    expect(config('mail.default'))->not->toBe('phpmailer');
});

test('the smtp password is encrypted and never exposed', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->patch(route('admin.settings.update'), settingsPayload([
            'mail_host' => 'smtp.mailprovider.com',
            'mail_username' => 'mailer@onenetly.com',
            'mail_password' => 'super-secret',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $stored = DB::table('settings')->where('key', 'mail_password')->value('value');

    expect($stored)->toBeString()
        ->and($stored)->not->toBe('super-secret')
        ->and(app(SettingsService::class)->get('mail_password'))->toBe('super-secret');

    $this->actingAs($admin)
        ->get(route('admin.settings.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('settings.mail_password', '')
            ->where('mailPasswordSet', true));
});

test('saving settings with a blank smtp password keeps the stored password', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    app(SettingsService::class)->set('mail_password', 'super-secret');

    $this->actingAs($admin)
        ->patch(route('admin.settings.update'), settingsPayload([
            'mail_host' => 'smtp.mailprovider.com',
            'mail_password' => '',
        ]))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(app(SettingsService::class)->get('mail_password'))->toBe('super-secret');
});

test('disabling payments blocks checkout', function () {
    app(SettingsService::class)->set('payments_enabled', false);
    config()->set('services.paypal.me_username', 'owner');

    $user = User::factory()->create();
    $plan = Plan::factory()->create(['price' => '9.99']);

    $this->actingAs($user)
        ->post(route('purchase.store'), ['plan_id' => $plan->id])
        ->assertRedirect(route('pricing.index'));

    expect(Purchase::count())->toBe(0);
});

test('disabling analytics hides the user analytics dashboard', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['has_analytics' => true]);
    Purchase::factory()->for($user)->create(['plan_id' => $plan->id]);

    app(SettingsService::class)->set('analytics_enabled', false);

    $this->actingAs($user)
        ->get(route('analytics'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('analytics/Index')
            ->where('analyticsEnabled', false)
            ->where('canViewAnalytics', false));
});

test('disabling custom domains stops serving them', function () {
    Profile::factory()->create([
        'custom_domain' => 'shop.example.com',
        'is_published' => true,
    ]);

    app(SettingsService::class)->set('custom_domains_enabled', false);

    $this->get('http://shop.example.com/')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Welcome'));
});

test('custom domains cannot be saved while the feature is disabled', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['has_custom_domain' => true]);
    Purchase::factory()->for($user)->create(['plan_id' => $plan->id]);
    $profile = Profile::factory()->for($user)->create(['username' => 'domain-owner']);

    app(SettingsService::class)->set('custom_domains_enabled', false);

    $this->actingAs($user)->patch(route('bio.update'), [
        'username' => $profile->username,
        'display_name' => 'Domain Tester',
        'theme' => 'indigo',
        'template' => 'classic',
        'is_published' => true,
        'custom_domain' => 'blocked.example.com',
    ])->assertRedirect(route('bio.edit'));

    expect($profile->refresh()->custom_domain)->toBeNull();
});

test('disabling custom domains locks the bio page field', function () {
    $user = User::factory()->create();
    $plan = Plan::factory()->create(['has_custom_domain' => true]);
    Purchase::factory()->for($user)->create(['plan_id' => $plan->id]);

    app(SettingsService::class)->set('custom_domains_enabled', false);

    $this->actingAs($user)
        ->get(route('bio.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('customDomainsEnabled', false)
            ->where('canUseCustomDomain', false));
});
