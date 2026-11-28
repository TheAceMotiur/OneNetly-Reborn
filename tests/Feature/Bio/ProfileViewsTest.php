<?php

use App\Models\Profile;
use App\Models\ProfileView;
use App\Models\User;
use App\Services\ProfileViewTracker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

function viewVisitorAgent(): string
{
    return 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_5 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.5 Mobile/15E148 Safari/604.1';
}

test('a real visitor view is recorded with its referrer and browser', function () {
    $profile = Profile::factory()->create(['is_published' => true]);

    $this->withHeaders([
        'User-Agent' => viewVisitorAgent(),
        'Referer' => 'https://www.instagram.com/',
    ])->get(route('bio.show', $profile->username))->assertOk();

    $view = ProfileView::sole();

    expect($view->profile_id)->toBe($profile->id)
        ->and($view->referrer)->toBe('https://www.instagram.com/')
        ->and($view->user_agent)->toBe(viewVisitorAgent())
        ->and($view->viewed_at)->not->toBeNull();
});

test('the owners own visits are not counted', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create();

    $this->actingAs($user)
        ->withHeaders(['User-Agent' => viewVisitorAgent()])
        ->get(route('bio.show', $profile->username))
        ->assertOk();

    expect(ProfileView::count())->toBe(0);
});

test('bots and link previews are not counted as views', function () {
    $profile = Profile::factory()->create();

    $this->withHeaders([
        'User-Agent' => 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)',
    ])->get(route('bio.show', $profile->username));

    $this->withHeaders([
        'User-Agent' => viewVisitorAgent(),
        'Sec-Purpose' => 'prefetch',
    ])->get(route('bio.show', $profile->username));

    $this->withHeaders([
        'User-Agent' => viewVisitorAgent(),
        'Purpose' => 'prefetch',
    ])->get(route('bio.show', $profile->username));

    expect(ProfileView::count())->toBe(0);
});

test('every visitor in a burst from one network is counted', function () {
    $profile = Profile::factory()->create();

    for ($i = 0; $i < 35; $i++) {
        $this->withHeaders(['User-Agent' => viewVisitorAgent()])
            ->get(route('bio.show', $profile->username))
            ->assertOk();
    }

    expect(ProfileView::count())->toBe(35);
});

test('a rapid reload from the same visitor counts as one view', function () {
    $profile = Profile::factory()->create();

    $session = app('session.store');
    $session->setId('profile-view-test-session');
    $session->start();

    $tracker = app(ProfileViewTracker::class);

    foreach (range(1, 3) as $ignored) {
        $request = Request::create(
            route('bio.show', $profile->username),
            'GET',
            server: ['HTTP_USER_AGENT' => viewVisitorAgent()],
        );
        $request->setLaravelSession($session);

        $tracker->record($request, $profile);
    }

    expect(ProfileView::count())->toBe(1);
});

test('views of a password protected page are counted', function () {
    $profile = Profile::factory()->create([
        'access_password' => Hash::make('secret'),
    ]);

    $this->withHeaders(['User-Agent' => viewVisitorAgent()])
        ->get(route('bio.show', $profile->username))
        ->assertInertia(fn (Assert $page) => $page->component('bio/Password'));

    expect(ProfileView::count())->toBe(1);
});

test('recorded views appear in the dashboard week and on the leaderboard', function () {
    $user = User::factory()->create();
    $profile = Profile::factory()->for($user)->create([
        'display_name' => 'Counted Page',
    ]);

    $this->withHeaders(['User-Agent' => viewVisitorAgent()])
        ->get(route('bio.show', $profile->username));

    $this->actingAs($user)->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('stats.views', 1)
            ->where('stats.viewsWeek', 1));

    $this->get(route('leaderboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('entries.0.username', $profile->username)
            ->where('entries.0.views', 1));
});
