<?php

namespace App\Http\Controllers;

use App\Mail\SubscriberBroadcast;
use App\Models\Profile;
use App\Models\Subscriber;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class SubscriberController extends Controller
{
    /**
     * List the page's email subscribers.
     */
    public function index(Request $request, Profile $profile): InertiaResponse
    {
        $this->authorizeAccess($request, $profile);

        return Inertia::render('subscribers/Index', [
            'restaurant' => ['username' => $profile->username, 'name' => $profile->restaurantName()],
            'subscribers' => $profile->subscribers()->latest()->get()->map(fn (Subscriber $subscriber): array => [
                'id' => $subscriber->id,
                'email' => $subscriber->email,
                'source' => $subscriber->source,
                'created_at' => $subscriber->created_at?->toDayDateTimeString(),
            ])->all(),
        ]);
    }

    /**
     * Download the audience as CSV.
     */
    public function export(Request $request, Profile $profile): StreamedResponse
    {
        $this->authorizeAccess($request, $profile);

        return response()->streamDownload(function () use ($profile): void {
            $handle = fopen('php://output', 'w');

            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['Email', 'Source', 'Subscribed at']);

            $profile->subscribers()->latest()->each(function (Subscriber $subscriber) use ($handle): void {
                fputcsv($handle, [
                    $subscriber->email,
                    $subscriber->source,
                    $subscriber->created_at?->toDateTimeString(),
                ]);
            });

            fclose($handle);
        }, "subscribers-{$profile->username}.csv", ['Content-Type' => 'text/csv']);
    }

    /**
     * Email every subscriber a short broadcast message.
     */
    public function send(Request $request, Profile $profile): RedirectResponse
    {
        $this->authorizeAccess($request, $profile);

        $data = $request->validate([
            'subject' => ['required', 'string', 'max:120'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $sent = 0;

        $profile->subscribers()->orderBy('id')->each(function (Subscriber $subscriber) use ($profile, $data, &$sent): void {
            try {
                Mail::to($subscriber->email)->send(new SubscriberBroadcast(
                    $profile,
                    $data['subject'],
                    $data['message'],
                    route('subscribers.unsubscribe', $subscriber->token),
                    (string) ($profile->user->email_footer ?? ''),
                ));

                $sent++;
            } catch (Throwable $exception) {
                report($exception);
            }
        });

        app(AuditLogger::class)->log($profile, 'subscribers.emailed', __('Emailed :count subscribers.', [
            'count' => $sent,
        ]));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':count subscribers emailed.', ['count' => $sent]),
        ]);

        return back();
    }

    /**
     * Remove an address from the audience.
     */
    public function destroy(Request $request, Profile $profile, Subscriber $subscriber): RedirectResponse
    {
        $this->authorizeAccess($request, $profile);
        abort_unless($subscriber->profile_id === $profile->id, 404);

        $email = $subscriber->email;

        $subscriber->delete();

        app(AuditLogger::class)->log($profile, 'subscriber.removed', __(':email removed from the audience.', [
            'email' => $email,
        ]));

        return back();
    }

    private function authorizeAccess(Request $request, Profile $profile): void
    {
        $user = $request->user();

        abort_unless(
            $user !== null
            && ($profile->user_id === $user->id
                || $user->sharedRestaurants()->whereKey($profile->id)->exists()),
            403,
        );
    }
}
