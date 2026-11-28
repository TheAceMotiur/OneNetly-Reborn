<?php

use App\Models\Profile;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

test('a published page provides a downloadable contact card', function () {
    $profile = Profile::factory()->create([
        'username' => 'ada-card',
        'display_name' => 'Ada Lovelace',
        'company' => 'Analytical Engines',
        'job_title' => 'Mathematician',
        'contact_email' => 'ada@example.com',
        'phone' => '+1 555 0100',
        'contact_website' => 'https://ada.example.com',
        'address' => '123 Main Street, London',
        'bio' => 'Computing pioneer.',
        'is_published' => true,
    ]);

    $response = $this->get(route('bio.vcard', $profile->username));

    $response->assertOk()
        ->assertHeader('content-type', 'text/vcard; charset=UTF-8')
        ->assertDownload('ada-card.vcf');

    expect($response->getContent())
        ->toContain('BEGIN:VCARD')
        ->toContain('FN:Ada Lovelace')
        ->toContain('ORG:Analytical Engines')
        ->toContain('TITLE:Mathematician')
        ->toContain('EMAIL;TYPE=INTERNET:ada@example.com')
        ->toContain('TEL;TYPE=CELL:+1 555 0100')
        ->toContain('URL:https://ada.example.com')
        ->toContain('ADR;TYPE=WORK:;;123 Main Street\\, London;;;;')
        ->toContain('END:VCARD');
});

test('the public page advertises the contact card', function () {
    $profile = Profile::factory()->create([
        'company' => 'Analytical Engines',
        'is_published' => true,
    ]);

    $this->get(route('bio.show', $profile->username))
        ->assertInertia(fn (Assert $page) => $page
            ->where('profile.has_vcard', true)
            ->where('profile.vcard_url', route('bio.vcard', $profile->username)));
});

test('protected pages require the page password before downloading a contact card', function () {
    $profile = Profile::factory()->create([
        'access_password' => Hash::make('secret-page'),
        'company' => 'Private Studio',
        'is_published' => true,
    ]);

    $this->get(route('bio.vcard', $profile->username))
        ->assertRedirect(route('bio.show', $profile->username));

    $this->post(route('bio.unlock', $profile->username), [
        'password' => 'secret-page',
    ]);

    $this->get(route('bio.vcard', $profile->username))
        ->assertOk()
        ->assertDownload();
});

test('unpublished pages do not expose contact cards', function () {
    $profile = Profile::factory()->unpublished()->create();

    $this->get(route('bio.vcard', $profile->username))->assertNotFound();
});
