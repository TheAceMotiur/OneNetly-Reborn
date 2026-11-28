<?php

namespace App\Http\Controllers\Bio;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Profile;
use App\Services\OrderPlacementService;
use App\Services\ProfileAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(
        private readonly ProfileAccess $access,
        private readonly OrderPlacementService $orders,
    ) {}

    /**
     * Place an order from the public menu.
     */
    public function store(Request $request, string $username): RedirectResponse
    {
        $profile = Profile::query()
            ->where('username', $username)
            ->where('is_published', true)
            ->with('user')
            ->firstOrFail();

        abort_unless($profile->acceptsOrders(), 404);

        if (! $this->access->isUnlocked($request, $profile)) {
            return redirect()->to(route('restaurant.show', $profile->username, false));
        }

        if (filled($request->input('website'))) {
            return back();
        }

        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:80'],
            'customer_phone' => ['required', 'string', 'max:40'],
            'customer_email' => ['nullable', 'email', 'max:120'],
            'order_type' => ['required', 'string', Rule::in(array_keys(Order::TYPES))],
            'table_number' => ['nullable', 'string', 'max:20', 'required_if:order_type,dine_in'],
            'delivery_address' => ['nullable', 'string', 'max:255', 'required_if:order_type,delivery'],
            'delivery_postcode' => ['nullable', 'string', 'max:20'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'redeem_points' => ['sometimes', 'boolean'],
            'notes' => ['nullable', 'string', 'max:500'],
            'tip_cents' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.id' => ['required', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
            'items.*.options' => ['nullable', 'array', 'max:20'],
            'items.*.options.*' => ['integer'],
            'website' => ['nullable', 'string', 'max:255'],
        ]);

        $order = $this->orders->place($profile, $data);

        Inertia::flash('order', [
            'reference' => $order->reference,
            'total' => $order->displayTotal(),
            'tracking_url' => route('restaurant.track', [$profile->username, $order->reference]),
        ]);
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Order :reference has been sent.', ['reference' => $order->reference]),
        ]);

        return to_route('restaurant.show', array_filter([
            'username' => $profile->username,
            'table' => $data['table_number'] ?? null,
        ]));
    }
}
