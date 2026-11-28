<?php

namespace App\Http\Controllers\EmailMarketing;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use App\Services\EmailMarketingNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriberController extends Controller
{
    /**
     * Add an address to the audience manually.
     */
    public function store(Request $request): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        $data = $request->validate([
            'email' => ['required', 'email', 'max:120'],
        ]);

        $subscriber = $profile->subscribers()->firstOrCreate([
            'email' => mb_strtolower(trim($data['email'])),
        ], [
            'source' => 'manual',
        ]);

        if ($subscriber->wasRecentlyCreated) {
            app(EmailMarketingNotifier::class)->welcome($subscriber);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $subscriber->wasRecentlyCreated
                ? __('Subscriber added.')
                : __('That email is already on the list.'),
        ]);

        return back();
    }

    /**
     * Import email addresses already collected on orders and bookings.
     */
    public function sync(Request $request): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        $emails = collect();

        $profile->orders()
            ->select(['customer_email'])
            ->whereNotNull('customer_email')
            ->get()
            ->each(function ($order) use ($emails): void {
                $emails->push(['email' => $order->customer_email, 'source' => 'order']);
            });

        $profile->reservations()
            ->select(['email'])
            ->whereNotNull('email')
            ->get()
            ->each(function ($reservation) use ($emails): void {
                $emails->push(['email' => $reservation->email, 'source' => 'reservation']);
            });

        $added = 0;

        foreach ($emails as $entry) {
            $email = mb_strtolower(trim((string) $entry['email']));

            if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                continue;
            }

            $subscriber = $profile->subscribers()->firstOrCreate(
                ['email' => $email],
                ['source' => $entry['source']],
            );

            if ($subscriber->wasRecentlyCreated) {
                $added++;
            }
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':count new subscriber(s) imported from orders and bookings.', ['count' => $added]),
        ]);

        return back();
    }

    /**
     * Remove an address from the audience.
     */
    public function destroy(Request $request, Subscriber $subscriber): RedirectResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

        abort_unless($subscriber->profile_id === $profile->id, 404);

        $subscriber->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Subscriber removed.')]);

        return back();
    }

    /**
     * Download the audience as CSV.
     */
    public function export(Request $request): StreamedResponse
    {
        $profile = $this->automationAccount($request)->workspaceProfile();

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
}
