<?php

use App\Models\Link;
use App\Models\Profile;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('a link sends visitors to its weighted variant and records the variant click', function () {
    $profile = Profile::factory()->create(['is_published' => true]);
    $link = Link::factory()->for($profile)->create([
        'url' => 'https://example.com/original',
        'is_active' => true,
    ]);

    $variant = $link->variants()->create([
        'label' => 'Landing page',
        'url' => 'https://example.com/landing',
        'weight' => 100,
        'is_active' => true,
    ]);

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)')
        ->get(route('links.redirect', $link))
        ->assertRedirect('https://example.com/landing');

    expect($link->refresh()->clicks)->toBe(1)
        ->and($variant->refresh()->clicks)->toBe(1)
        ->and($link->clicks()->where('variant_id', $variant->id)->count())->toBe(1);
});

test('a paused variant is ignored and the original link stays the destination', function () {
    $profile = Profile::factory()->create(['is_published' => true]);
    $link = Link::factory()->for($profile)->create(['url' => 'https://example.com/original']);

    $link->variants()->create([
        'label' => 'Paused',
        'url' => 'https://example.com/paused',
        'weight' => 100,
        'is_active' => false,
    ]);

    $this->withHeader('User-Agent', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)')
        ->get(route('links.redirect', $link))
        ->assertRedirect('https://example.com/original');
});

test('owners add collaborators who can edit but not manage the team', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $page = Profile::factory()->for($owner)->create([
        'business_type' => 'personal',
        'is_published' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('bio.collaborators.store'), [
            'email' => $editor->email,
            'role' => 'editor',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($page->collaborators()->whereKey($editor->id)->exists())->toBeTrue();

    $this->actingAs($editor)
        ->post(route('links.store', ['page' => $page->username]), [
            'title' => 'Shared link',
            'url' => 'https://example.com/shared',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect($page->links()->where('title', 'Shared link')->exists())->toBeTrue();

    $this->actingAs($editor)
        ->post(route('bio.collaborators.store', ['page' => $page->username]), [
            'email' => User::factory()->create()->email,
            'role' => 'viewer',
        ])
        ->assertForbidden();
});

test('viewers can open the management screens but cannot change anything', function () {
    $owner = User::factory()->create();
    $viewer = User::factory()->create();
    $page = Profile::factory()->for($owner)->create(['business_type' => 'personal']);

    $page->collaborators()->attach($viewer, ['role' => 'viewer']);

    $this->actingAs($viewer)
        ->get(route('links.index', ['page' => $page->username]))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('links/Index')
            ->where('canEdit', false));

    $this->actingAs($viewer)
        ->post(route('links.store', ['page' => $page->username]), [
            'title' => 'Nope',
            'url' => 'https://example.com/nope',
        ])
        ->assertForbidden();
});

test('the biolink management screens load for the owner and a collaborator', function () {
    $owner = User::factory()->create();
    $editor = User::factory()->create();
    $page = Profile::factory()->for($owner)->create(['business_type' => 'personal']);

    $page->collaborators()->attach($editor, ['role' => 'editor']);

    $this->actingAs($owner)
        ->get(route('bio.edit'))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('bio/Edit')
            ->where('activePage', $page->username)
            ->where('canManageCollaborators', true));

    $this->actingAs($owner)
        ->get(route('bio.blocks'))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('bio/Blocks')
            ->where('canEdit', true));

    $this->actingAs($editor)
        ->get(route('bio.edit', ['page' => $page->username]))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('bio/Edit')
            ->where('activePage', $page->username)
            ->where('canManageCollaborators', false));
});

test('page blocks can be managed and appear on the public page', function () {
    $owner = User::factory()->create();
    $page = Profile::factory()->for($owner)->create([
        'business_type' => 'personal',
        'is_published' => true,
    ]);

    $this->actingAs($owner)
        ->post(route('bio.blocks.store'), [
            'type' => 'heading',
            'content' => 'Welcome to my page',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $this->actingAs($owner)
        ->post(route('bio.blocks.store'), [
            'type' => 'video',
            'media_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $block = $page->blocks()->where('type', 'heading')->sole();

    $this->actingAs($owner)
        ->patch(route('bio.blocks.update', $block), [
            'type' => 'heading',
            'content' => 'Welcome back',
        ])
        ->assertRedirect();

    $this->get(route('bio.show', $page->username))
        ->assertInertia(fn (Assert $inertia) => $inertia
            ->component('bio/Show')
            ->has('blocks', 2)
            ->where('blocks.0.content', 'Welcome back')
            ->where('blocks.1.embed_url', 'https://www.youtube.com/embed/dQw4w9WgXcQ'));

    $this->actingAs($owner)
        ->delete(route('bio.blocks.destroy', $block))
        ->assertRedirect();

    expect($page->blocks()->count())->toBe(1);
});

test('blocks require content for text blocks and a url for media blocks', function () {
    $owner = User::factory()->create();

    $this->actingAs($owner)
        ->post(route('bio.blocks.store'), ['type' => 'text'])
        ->assertSessionHasErrors('content');

    $this->actingAs($owner)
        ->post(route('bio.blocks.store'), ['type' => 'image'])
        ->assertSessionHasErrors('media_url');
});
