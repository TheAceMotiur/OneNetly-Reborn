<?php

use App\Models\User;
use App\Models\Widget;
use App\Support\Widgets\WidgetCatalog;
use App\Support\Widgets\WidgetSnippets;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open the widgets area', function () {
    $this->get(route('widgets.index'))->assertRedirect(route('login'));
});

test('the gallery lists the catalogue and only the users widgets', function () {
    $user = User::factory()->create();
    Widget::factory()->for($user)->ofType('social-share')->create();
    Widget::factory()->create();

    $this->actingAs($user)->get(route('widgets.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('widgets/Index')
            ->has('widgets', 1)
            ->has('catalog', count(WidgetCatalog::all()))
            ->has('categories', count(WidgetCatalog::CATEGORIES))
            ->where('activeCategory', null));
});

test('the gallery can be filtered by category', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('widgets.index', ['category' => 'utility']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeCategory', 'utility')
            ->has('catalog', count(WidgetCatalog::catalog('utility'))));

    $this->actingAs($user)->get(route('widgets.index', ['category' => 'nonsense']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('activeCategory', null));
});

test('users can add a widget with its default settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('widgets.store'), [
        'type' => 'adblock-detector',
    ])->assertRedirect();

    $widget = Widget::firstOrFail();

    expect($widget->user_id)->toBe($user->id)
        ->and($widget->type)->toBe('adblock-detector')
        ->and($widget->name)->toBe('AdBlock detector')
        ->and($widget->configuration()['style'])->toBe('banner')
        ->and($widget->public_key)->not->toBeNull()
        ->and($widget->snippet())->toContain('/widgets/embed/'.$widget->public_key.'.js')
        ->and($widget->previewDocument())->toContain('<!doctype html>');
});

test('unknown widget types are rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->post(route('widgets.store'), [
        'type' => 'crypto-miner',
    ])->assertSessionHasErrors('type');

    expect(Widget::count())->toBe(0);
});

test('users can save widget settings', function () {
    $user = User::factory()->create();
    $widget = Widget::factory()->for($user)->ofType('social-share')->create();

    $this->actingAs($user)->patch(route('widgets.update', $widget), [
        'name' => 'Article share bar',
        'config' => [
            'label' => 'Enjoyed this?',
            'networks' => 'x,linkedin,email',
            'style' => 'icons',
            'theme' => 'dark',
            'url' => 'https://example.com/post',
            'text' => 'A good read',
            'align' => 'center',
            'copy' => true,
        ],
    ])->assertRedirect();

    $widget->refresh();

    expect($widget->name)->toBe('Article share bar')
        ->and($widget->configuration()['theme'])->toBe('dark');

    $embed = $this->get($widget->embedUrl());

    $embed->assertOk();

    expect((string) $embed->headers->get('Content-Type'))->toContain('javascript')
        ->and($embed->getContent())->toContain('linkedin.com')
        ->and($embed->getContent())->toContain('https://example.com/post');
});

test('invalid colours and out of range numbers fall back or fail validation', function () {
    $user = User::factory()->create();
    $widget = Widget::factory()->for($user)->ofType('countdown')->create();

    $this->actingAs($user)->patch(route('widgets.update', $widget), [
        'name' => 'Launch',
        'config' => [
            'title' => 'Launch',
            'ends_at' => '2026-12-31T23:59',
            'background' => 'javascript:alert(1)',
            'text_color' => '#ffffff',
            'accent' => '#818cf8',
            'expired_text' => 'Over',
        ],
    ])->assertSessionHasErrors('config.background');
});

test('the embed code is a single public script line', function () {
    $user = User::factory()->create();
    $widget = Widget::factory()->for($user)->ofType('scroll-progress')->create();

    $snippet = $widget->snippet();

    expect($snippet)->toStartWith('<script src="')
        ->toContain($widget->public_key.'.js')
        ->toContain('async')
        ->not->toContain("\n");

    $this->get($widget->embedUrl())
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
        ->assertSee('onw-progress', false);
});

test('unknown embed keys are not found', function () {
    $this->get('/widgets/embed/doesnotexist123.js')->assertNotFound();
});

test('the preview endpoint returns escaped markup', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('widgets.preview'), [
        'type' => 'adblock-detector',
        'config' => [
            'title' => 'Support us',
            'message' => '<script>alert("xss")</script>',
            'style' => 'toast',
            'accent' => '#4f46e5',
            'delay' => 500,
        ],
    ]);

    $response->assertOk()->assertJsonStructure(['preview', 'watermark']);

    $preview = (string) $response->json('preview');

    expect($preview)->not->toContain('<script>alert')
        ->and($preview)->toContain('toast');
});

test('text settings are html escaped in markup widgets', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('widgets.preview'), [
        'type' => 'announcement-bar',
        'config' => [
            'message' => '<img src=x onerror=alert(1)>',
            'link_label' => 'Shop',
            'link_url' => '/',
            'position' => 'top',
            'background' => '#4f46e5',
            'text_color' => '#ffffff',
            'dismissible' => true,
        ],
    ]);

    $preview = (string) $response->json('preview');

    expect($preview)->toContain('&lt;img src=x onerror=alert(1)&gt;')
        ->and($preview)->not->toContain('<img src=x');
});

test('the extra widget options change the rendered embed', function () {
    $share = WidgetSnippets::render('social-share', [
        'text' => 'Read this',
        'align' => 'center',
        'copy' => true,
    ]);

    expect($share)->toContain('onw-align-center')
        ->and($share)->toContain('Read this')
        ->and($share)->toContain('Copy link');

    $cookie = WidgetSnippets::render('cookie-banner', [
        'show_decline' => false,
        'expires_days' => 30,
    ]);

    expect($cookie)->toContain('var days = 30;')
        ->and($cookie)->toContain('if (false) {');

    $countdown = WidgetSnippets::render('countdown', [
        'show_labels' => false,
        'hide_when_done' => true,
    ]);

    expect($countdown)->toContain('data-labels="0"')
        ->and($countdown)->toContain("root.style.display = 'none';");

    $announcement = WidgetSnippets::render('announcement-bar', [
        'link_new_tab' => true,
        'dismiss_days' => 7,
    ]);

    expect($announcement)->toContain('target="_blank"')
        ->and($announcement)->toContain('var days = 7;');

    $whatsapp = WidgetSnippets::render('whatsapp-button', [
        'phone' => '14155550134',
        'greeting' => 'Need help?',
        'always_label' => true,
    ]);

    expect($whatsapp)->toContain('onw-always')
        ->and($whatsapp)->toContain('Need help?');

    $progress = WidgetSnippets::render('scroll-progress', [
        'track_color' => '#111827',
        'align' => 'center',
        'rounded' => true,
    ]);

    expect($progress)->toContain('--onw-track:#111827')
        ->and($progress)->toContain('onw-center')
        ->and($progress)->toContain('onw-rounded');

    $adblock = WidgetSnippets::render('adblock-detector', [
        'cta_label' => 'How to disable',
        'cta_url' => '/support/ads',
        'position' => 'left',
    ]);

    expect($adblock)->toContain('How to disable')
        ->and($adblock)->toContain('data-position="left"');

    $backToTop = WidgetSnippets::render('back-to-top', [
        'shape' => 'circle',
        'smooth' => false,
    ]);

    expect($backToTop)->toContain('onw-circle')
        ->and($backToTop)->toContain('behavior: "auto"');
});

test('users cannot open or change another users widget', function () {
    $widget = Widget::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->get(route('widgets.edit', $widget))->assertForbidden();
    $this->actingAs($intruder)->patch(route('widgets.update', $widget), [
        'name' => 'Hijacked',
        'config' => $widget->configuration(),
    ])->assertForbidden();
    $this->actingAs($intruder)->delete(route('widgets.destroy', $widget))->assertForbidden();

    expect($widget->refresh()->name)->not->toBe('Hijacked');
});

test('users can delete their widget', function () {
    $user = User::factory()->create();
    $widget = Widget::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('widgets.destroy', $widget))
        ->assertRedirect(route('widgets.index'));

    expect(Widget::find($widget->id))->toBeNull();
});

test('every widget gets the delivery controls with safe defaults', function () {
    foreach (array_keys(WidgetCatalog::all()) as $type) {
        $config = WidgetCatalog::defaults($type);

        expect($config)->toHaveKeys([
            'behaviour_paused',
            'behaviour_device',
            'behaviour_pages',
            'behaviour_paths',
            'behaviour_delay',
            'behaviour_frequency',
            'behaviour_from',
            'behaviour_until',
            'behaviour_animation',
        ])
            ->and($config['behaviour_paused'])->toBeFalse()
            ->and($config['behaviour_device'])->toBe('all')
            ->and($config['behaviour_pages'])->toBe('everywhere')
            ->and($config['behaviour_delay'])->toBe(0)
            ->and($config['behaviour_frequency'])->toBe('always')
            ->and($config['behaviour_animation'])->toBe('none');
    }
});

test('the embed script applies the delivery rules before rendering', function () {
    $user = User::factory()->create();
    $widget = Widget::factory()->for($user)->ofType('announcement-bar')->create();

    $this->actingAs($user)->patch(route('widgets.update', $widget), [
        'name' => 'Mobile sale bar',
        'config' => array_merge($widget->configuration(), [
            'behaviour_device' => 'mobile',
            'behaviour_pages' => 'include',
            'behaviour_paths' => '/blog/*, /pricing',
            'behaviour_delay' => 1500,
            'behaviour_frequency' => 'day',
            'behaviour_animation' => 'slide',
            'behaviour_from' => '2026-12-01T09:00',
            'behaviour_until' => '2026-12-31T23:59',
        ]),
    ])->assertRedirect();

    $embed = (string) $this->get($widget->embedUrl())->getContent();

    expect($embed)
        ->toContain('"device":"mobile"')
        ->toContain('"pages":"include"')
        ->toContain('"paths":["/blog/*","/pricing"]')
        ->toContain('"delay":1500')
        ->toContain('"frequency":"day"')
        ->toContain('"animation":"slide"')
        ->toContain('"from":"2026-12-01T09:00"')
        ->toContain('"until":"2026-12-31T23:59"')
        ->toContain('window.setTimeout(show, behaviour.delay)')
        ->toContain('onw-seen-')
        ->not->toContain('__BEHAVIOUR__');
});

test('a paused widget keeps its settings and stops rendering on the site', function () {
    $user = User::factory()->create();
    $widget = Widget::factory()->for($user)->ofType('back-to-top')->create();

    $this->actingAs($user)->patch(route('widgets.update', $widget), [
        'name' => 'Back to top',
        'config' => array_merge($widget->configuration(), [
            'behaviour_paused' => true,
        ]),
    ])->assertRedirect();

    $widget->refresh();

    expect($widget->configuration()['behaviour_paused'])->toBeTrue()
        ->and($this->get($widget->embedUrl())->getContent())->toContain('"paused":true')
        ->and(WidgetSnippets::preview('back-to-top', ['behaviour_paused' => true]))->toContain('onw-top');
});

test('unknown targeting values are rejected', function () {
    $user = User::factory()->create();
    $widget = Widget::factory()->for($user)->ofType('scroll-progress')->create();

    $this->actingAs($user)->patch(route('widgets.update', $widget), [
        'name' => 'Progress',
        'config' => array_merge($widget->configuration(), [
            'behaviour_device' => 'tablet',
            'behaviour_frequency' => 'sometimes',
        ]),
    ])->assertSessionHasErrors(['config.behaviour_device', 'config.behaviour_frequency']);
});
