<?php

use App\Models\Resume;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot open the resume builder', function () {
    $this->get(route('resumes.index'))->assertRedirect(route('login'));
    $this->get(route('resumes.templates'))->assertRedirect(route('login'));
});

test('users can create a resume from a template', function () {
    $user = User::factory()->create(['name' => 'Ada Lovelace', 'email' => 'ada@example.com']);

    $this->actingAs($user)->post(route('resumes.store'), [
        'title' => 'Engineering resume',
        'template' => 'modern',
        'accent' => 'emerald',
        'font' => 'serif',
    ])->assertRedirect();

    $resume = Resume::firstOrFail();

    expect($resume->user_id)->toBe($user->id)
        ->and($resume->title)->toBe('Engineering resume')
        ->and($resume->template)->toBe('modern')
        ->and($resume->accent)->toBe('emerald')
        ->and($resume->font)->toBe('serif')
        ->and($resume->is_public)->toBeFalse()
        ->and($resume->slug)->not->toBeEmpty()
        ->and($resume->structuredContent()['basics']['name'])->toBe('Ada Lovelace')
        ->and($resume->structuredContent()['basics']['email'])->toBe('ada@example.com');
});

test('the builder only lists the resumes of the signed-in user', function () {
    $user = User::factory()->create();
    Resume::factory()->for($user)->count(2)->create();
    Resume::factory()->create();

    $this->actingAs($user)->get(route('resumes.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resumes/Index')
            ->has('resumes', 2)
            ->has('templates', count(Resume::TEMPLATES)));
});

test('the template gallery previews every template', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('resumes.templates'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resumes/Templates')
            ->has('templates', count(Resume::TEMPLATES))
            ->where('templates.0.ats', true)
            ->where('sample.basics.name', 'Alex Morgan'));
});

test('users can save resume content and styling', function () {
    $user = User::factory()->create();
    $resume = Resume::factory()->for($user)->create();

    $content = Resume::blankContent();
    $content['basics']['name'] = 'Ada Lovelace';
    $content['basics']['headline'] = 'Mathematician';
    $content['experience'][] = [
        'title' => 'Analyst',
        'company' => 'Acme',
        'location' => 'London',
        'start' => '1842',
        'end' => '',
        'current' => true,
        'bullets' => "Wrote the first algorithm.\nPublished the notes.",
    ];

    $this->actingAs($user)->patch(route('resumes.update', $resume), [
        'title' => 'Ada Lovelace CV',
        'template' => 'sidebar',
        'accent' => 'rose',
        'font' => 'serif',
        'content' => $content,
    ])->assertRedirect();

    $resume->refresh();
    $saved = $resume->structuredContent();

    expect($resume->title)->toBe('Ada Lovelace CV')
        ->and($resume->template)->toBe('sidebar')
        ->and($resume->accent)->toBe('rose')
        ->and($resume->font)->toBe('serif')
        ->and($resume->accentHex())->toBe(Resume::ACCENTS['rose'])
        ->and($saved['basics']['headline'])->toBe('Mathematician')
        ->and($saved['experience'][0]['company'])->toBe('Acme')
        ->and($saved['experience'][0]['current'])->toBeTrue()
        ->and($resume->completeness())->toBeGreaterThan(0);
});

test('unknown templates and accents are rejected', function () {
    $user = User::factory()->create();
    $resume = Resume::factory()->for($user)->create();
    $template = $resume->template;

    $this->actingAs($user)->patch(route('resumes.update', $resume), [
        'title' => 'Resume',
        'template' => 'rainbow',
        'accent' => 'neon',
        'font' => 'comic',
        'content' => Resume::blankContent(),
    ])->assertSessionHasErrors(['template', 'accent', 'font']);

    expect($resume->refresh()->template)->toBe($template);
});

test('users cannot open or change another users resume', function () {
    $resume = Resume::factory()->create();
    $intruder = User::factory()->create();

    $this->actingAs($intruder)->get(route('resumes.edit', $resume))->assertForbidden();
    $this->actingAs($intruder)->get(route('resumes.print', $resume))->assertForbidden();
    $this->actingAs($intruder)->patch(route('resumes.update', $resume), [
        'title' => 'Hijacked',
        'template' => 'classic',
        'accent' => 'indigo',
        'font' => 'sans',
        'content' => Resume::blankContent(),
    ])->assertForbidden();
    $this->actingAs($intruder)->delete(route('resumes.destroy', $resume))->assertForbidden();

    expect($resume->refresh()->title)->not->toBe('Hijacked');
});

test('users can duplicate a resume', function () {
    $user = User::factory()->create();
    $resume = Resume::factory()->for($user)->create(['title' => 'Designer CV']);

    $this->actingAs($user)->post(route('resumes.duplicate', $resume))->assertRedirect();

    expect(Resume::count())->toBe(2)
        ->and(Resume::where('user_id', $user->id)->count())->toBe(2)
        ->and(Resume::where('title', 'Designer CV (copy)')->exists())->toBeTrue();
});

test('a published resume is readable by guests', function () {
    $resume = Resume::factory()->published()->create([
        'slug' => 'ada-lovelace-cv',
        'title' => 'Ada Lovelace CV',
    ]);

    $this->get(route('resume.public', 'ada-lovelace-cv'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('resumes/Print')
            ->where('resume.title', 'Ada Lovelace CV')
            ->where('backUrl', null));
});

test('a private resume is not publicly available', function () {
    Resume::factory()->create(['slug' => 'private-cv']);

    $this->get(route('resume.public', 'private-cv'))->assertNotFound();
});

test('the owner can publish and unpublish a resume', function () {
    $user = User::factory()->create();
    $resume = Resume::factory()->for($user)->create();

    $this->actingAs($user)->post(route('resumes.publish', $resume))->assertRedirect();

    expect($resume->refresh()->is_public)->toBeTrue()
        ->and($resume->publicUrl())->toBe(route('resume.public', $resume->slug));

    $this->actingAs($user)->post(route('resumes.publish', $resume))->assertRedirect();

    expect($resume->refresh()->is_public)->toBeFalse()
        ->and($resume->publicUrl())->toBeNull();
});

test('shared and printed resumes are kept out of search engines', function () {
    $resume = Resume::factory()->published()->create(['slug' => 'ada-lovelace-cv']);

    $this->get(route('resume.public', 'ada-lovelace-cv'))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');

    $this->actingAs($resume->user)
        ->get(route('resumes.print', $resume))
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive');
});

test('users can delete their resume', function () {
    $user = User::factory()->create();
    $resume = Resume::factory()->for($user)->create();

    $this->actingAs($user)->delete(route('resumes.destroy', $resume))
        ->assertRedirect(route('resumes.index'));

    expect(Resume::find($resume->id))->toBeNull();
});
