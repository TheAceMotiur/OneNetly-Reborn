<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use App\Models\Reservation;
use App\Services\PosWebhookDispatcher;
use App\Services\WhatsAppNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Throwable;

class PublicReservationController extends Controller
{
    /**
     * Request a table from the public menu.
     */
    public function store(Request $request, string $username): RedirectResponse
    {
        $profile = Profile::query()
            ->where('username', $username)
            ->where('is_published', true)
            ->firstOrFail();

        if (filled($request->input('website'))) {
            return back();
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'phone' => ['required', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:120'],
            'party_size' => ['required', 'integer', 'min:1', 'max:'.$profile->reservation_max_party],
            'reservation_date' => ['required', 'date', 'after_or_equal:today'],
            'reservation_time' => ['required', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:500'],
            'join_waitlist' => ['sometimes', 'boolean'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        $joinWaitlist = (bool) ($data['join_waitlist'] ?? false);

        unset($data['website'], $data['join_waitlist']);

        $reservation = DB::transaction(function () use ($profile, $data, $joinWaitlist): Reservation {
            Profile::query()->whereKey($profile->id)->lockForUpdate()->first();

            if ($profile->reservation_slot_capacity > 0) {
                $booked = $profile->reservations()
                    ->whereDate('reservation_date', $data['reservation_date'])
                    ->where('reservation_time', $data['reservation_time'])
                    ->whereNotIn('status', [Reservation::STATUS_CANCELLED, Reservation::STATUS_WAITLISTED])
                    ->count();

                if ($booked >= $profile->reservation_slot_capacity) {
                    if (! $joinWaitlist) {
                        throw ValidationException::withMessages([
                            'reservation_time' => __('That time is fully booked. Join the waitlist and we will call you if a table frees up.'),
                        ]);
                    }

                    $data['status'] = Reservation::STATUS_WAITLISTED;
                }
            }

            return $profile->reservations()->create([
                ...$data,
                'status' => $data['status'] ?? ($profile->reservation_auto_confirm
                    ? Reservation::STATUS_CONFIRMED
                    : Reservation::STATUS_PENDING),
            ]);
        });

        app()->terminating(function () use ($reservation): void {
            try {
                app(PosWebhookDispatcher::class)->reservationCreated($reservation);
                app(WhatsAppNotifier::class)->reservationReceived($reservation);
            } catch (Throwable $exception) {
                report($exception);
            }
        });

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => $reservation->status === Reservation::STATUS_WAITLISTED
                ? __('You are on the waitlist. The restaurant will contact you if a table frees up.')
                : __('Table request sent. The restaurant will confirm shortly.'),
        ]);

        return back();
    }
}
