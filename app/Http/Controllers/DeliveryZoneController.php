<?php

namespace App\Http\Controllers;

use App\Models\DeliveryZone;
use App\Models\Profile;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class DeliveryZoneController extends Controller
{
    /**
     * Show the delivery zones of one restaurant.
     */
    public function index(Request $request, Profile $profile): Response
    {
        abort_unless($profile->canManage($request->user()), 403);

        return Inertia::render('delivery/Index', [
            'zones' => $profile->deliveryZones()->get()->map(fn (DeliveryZone $zone): array => [
                'id' => $zone->id,
                'name' => $zone->name,
                'postcodes' => implode(', ', $zone->postcodes),
                'fee' => number_format($zone->fee_cents / 100, 2),
                'min_order' => number_format($zone->min_order_cents / 100, 2),
                'is_active' => $zone->is_active,
            ])->all(),
            'restaurant' => [
                'username' => $profile->username,
                'name' => $profile->restaurantName(),
                'currency' => $profile->menu_currency,
                'delivery_fee' => number_format($profile->delivery_fee_cents / 100, 2),
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
     * Add a delivery zone.
     */
    public function store(Request $request, Profile $profile): RedirectResponse
    {
        abort_unless($profile->canManage($request->user()), 403);

        $zone = $profile->deliveryZones()->create($this->validated($request));

        app(AuditLogger::class)->log($profile, 'delivery.zone_created', __('Delivery zone :name created.', [
            'name' => $zone->name,
        ]), $zone);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Delivery zone added.')]);

        return back();
    }

    /**
     * Update a delivery zone.
     */
    public function update(Request $request, Profile $profile, DeliveryZone $zone): RedirectResponse
    {
        abort_unless($profile->canManage($request->user()), 403);
        abort_unless($zone->profile_id === $profile->id, 404);

        $zone->update($this->validated($request));

        app(AuditLogger::class)->log($profile, 'delivery.zone_updated', __('Delivery zone :name updated.', [
            'name' => $zone->name,
        ]), $zone);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Delivery zone updated.')]);

        return back();
    }

    /**
     * Remove a delivery zone.
     */
    public function destroy(Request $request, Profile $profile, DeliveryZone $zone): RedirectResponse
    {
        abort_unless($profile->canManage($request->user()), 403);
        abort_unless($zone->profile_id === $profile->id, 404);

        $name = $zone->name;

        $zone->delete();

        app(AuditLogger::class)->log($profile, 'delivery.zone_deleted', __('Delivery zone :name deleted.', [
            'name' => $name,
        ]));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Delivery zone removed.')]);

        return back();
    }

    /**
     * Validate and normalise a zone submission.
     *
     * @return array{name: string, postcodes: array<int, string>, fee_cents: int, min_order_cents: int, is_active: bool}
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'postcodes' => ['required', 'string', 'max:500'],
            'fee' => ['required', 'numeric', 'min:0', 'max:10000'],
            'min_order' => ['required', 'numeric', 'min:0', 'max:100000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $postcodes = DeliveryZone::normalisePostcodes($data['postcodes']);

        if ($postcodes === []) {
            throw ValidationException::withMessages([
                'postcodes' => __('Add at least one postcode or prefix.'),
            ]);
        }

        return [
            'name' => $data['name'],
            'postcodes' => $postcodes,
            'fee_cents' => (int) round(((float) $data['fee']) * 100),
            'min_order_cents' => (int) round(((float) $data['min_order']) * 100),
            'is_active' => $request->boolean('is_active', true),
        ];
    }
}
