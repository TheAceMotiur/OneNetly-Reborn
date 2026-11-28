<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\Reservation;
use App\Services\AuditLogger;
use App\Services\PosWebhookDispatcher;
use App\Services\SmsNotifier;
use App\Services\WhatsAppNotifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

class ReservationController extends Controller
{
    /**
     * Show the bookings for one restaurant.
     */
    public function index(Request $request, Profile $profile): Response
    {
        abort_unless($profile->canOperate($request->user()), 403);

        $reservations = $profile->reservations()
            ->orderBy('reservation_date')
            ->orderBy('reservation_time')
            ->limit(200)
            ->get();

        return Inertia::render('reservations/Index', [
            'reservations' => $reservations->map(fn (Reservation $reservation): array => [
                'id' => $reservation->id,
                'name' => $reservation->name,
                'phone' => $reservation->phone,
                'email' => $reservation->email,
                'party_size' => $reservation->party_size,
                'date' => $reservation->reservation_date->toDateString(),
                'date_label' => $reservation->reservation_date->format('D, j M Y'),
                'time' => $reservation->reservation_time,
                'notes' => $reservation->notes,
                'status' => $reservation->status,
                'status_label' => $reservation->statusLabel(),
                'status_class' => $reservation->statusColorClass(),
                'created_human' => $reservation->created_at?->diffForHumans(),
            ])->all(),
            'stats' => [
                'pending' => $profile->reservations()
                    ->where('status', Reservation::STATUS_PENDING)->count(),
                'upcoming' => $profile->reservations()
                    ->where('status', Reservation::STATUS_CONFIRMED)
                    ->whereDate('reservation_date', '>=', today())
                    ->count(),
                'guests' => (int) $profile->reservations()
                    ->where('status', '!=', Reservation::STATUS_CANCELLED)
                    ->whereDate('reservation_date', '>=', today())
                    ->sum('party_size'),
                'waitlist' => $profile->reservations()
                    ->where('status', Reservation::STATUS_WAITLISTED)
                    ->count(),
            ],
            'statuses' => Reservation::STATUSES,
            'restaurant' => [
                'username' => $profile->username,
                'name' => $profile->restaurantName(),
                'public_url' => route('restaurant.show', $profile->username),
            ],
            'restaurants' => $request->user()->accessibleRestaurants()
                ->map(fn (Profile $restaurant): array => [
                    'username' => $restaurant->username,
                    'name' => $restaurant->restaurantName(),
                ])
                ->all(),
        ]);
    }

    /**
     * Confirm or cancel a booking.
     */
    public function update(Request $request, Profile $profile, Reservation $reservation): RedirectResponse
    {
        abort_unless($profile->canOperate($request->user()), 403);
        abort_unless($reservation->profile_id === $profile->id, 404);

        $data = $request->validate([
            'status' => ['required', 'string', Rule::in(array_keys(Reservation::STATUSES))],
        ]);

        $previous = $reservation->status;

        $reservation->update(['status' => $data['status']]);

        app(AuditLogger::class)->log(
            $profile,
            'reservation.status',
            __('Reservation for :name on :date changed to :status.', [
                'name' => $reservation->name,
                'date' => $reservation->reservation_date->format('j M').' '.$reservation->reservation_time,
                'status' => strtolower($reservation->statusLabel()),
            ]),
            $reservation,
        );

        if ($previous !== $data['status']) {
            app()->terminating(function () use ($reservation, $previous): void {
                app(PosWebhookDispatcher::class)->reservationUpdated($reservation, $previous);
                app(SmsNotifier::class)->reservationStatusChanged($reservation, $previous);

                try {
                    app(WhatsAppNotifier::class)->reservationStatusChanged($reservation, $previous);
                } catch (Throwable $exception) {
                    report($exception);
                }
            });
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Reservation :status.', ['status' => strtolower($reservation->statusLabel())]),
        ]);

        return back();
    }
}
