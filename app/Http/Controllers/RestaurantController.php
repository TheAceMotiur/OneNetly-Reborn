<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Profile;
use App\Models\ProfileView;
use App\Models\ServiceRequest;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RestaurantController extends Controller
{
    /**
     * Show the restaurant hub with every restaurant the user runs.
     */
    public function index(Request $request): Response
    {
        $restaurants = $request->user()->accessibleRestaurants();

        $selected = $this->selectedRestaurant($request, $restaurants);
        $profileIds = $restaurants->pluck('id');
        $today = Carbon::today();

        return Inertia::render('restaurant/Index', [
            'restaurants' => $restaurants->map(fn (Profile $profile): array => [
                'username' => $profile->username,
                'name' => $profile->restaurantName(),
                'public_url' => route('restaurant.show', $profile->username),
                'is_published' => $profile->is_published,
                'ordering_enabled' => $profile->menu_ordering_enabled,
                'accepting_orders' => $profile->accepting_orders,
                'item_count' => $profile->menuItems()->count(),
                'orders_count' => $profile->orders()->count(),
            ])->all(),
            'selected' => $selected instanceof Profile ? $this->selectedPayload($selected, $today) : null,
            'totals' => [
                'restaurants' => $restaurants->count(),
                'orders_today' => Order::query()
                    ->whereIn('profile_id', $profileIds)
                    ->whereDate('created_at', $today)
                    ->count(),
                'new_orders' => Order::query()
                    ->whereIn('profile_id', $profileIds)
                    ->where('status', OrderStatus::New)
                    ->count(),
                'pending_requests' => ServiceRequest::query()
                    ->whereIn('profile_id', $profileIds)
                    ->where('status', ServiceRequest::STATUS_PENDING)
                    ->count(),
                'views_today' => ProfileView::query()
                    ->whereIn('profile_id', $profileIds)
                    ->whereDate('viewed_at', $today)
                    ->count(),
                'views_week' => ProfileView::query()
                    ->whereIn('profile_id', $profileIds)
                    ->where('viewed_at', '>=', $today->copy()->subDays(6))
                    ->count(),
            ],
            'serviceRequests' => ServiceRequest::query()
                ->with('profile:id,username,display_name,company')
                ->whereIn('profile_id', $profileIds)
                ->where('status', ServiceRequest::STATUS_PENDING)
                ->latest('id')
                ->limit(12)
                ->get()
                ->map(fn (ServiceRequest $serviceRequest): array => [
                    'id' => $serviceRequest->id,
                    'type_label' => $serviceRequest->typeLabel(),
                    'table_number' => $serviceRequest->table_number,
                    'restaurant' => $serviceRequest->profile->restaurantName(),
                    'created_human' => $serviceRequest->created_at?->diffForHumans(),
                    'complete_url' => route('restaurant.requests.complete', [
                        $serviceRequest->profile,
                        $serviceRequest,
                    ]),
                ])
                ->all(),
            'currencies' => Profile::MENU_CURRENCIES,
            'templates' => Profile::RESTAURANT_TEMPLATES,
            'timezones' => Profile::TIMEZONES,
            'weekdays' => Profile::WEEKDAYS,
            'can_manage_team' => $selected instanceof Profile
                && $selected->user_id === $request->user()->id,
            'team_store_url' => $selected instanceof Profile
                ? route('restaurant.team.store', $selected)
                : null,
            'team' => $selected instanceof Profile
                && $selected->user_id === $request->user()->id
                    ? $selected->members()->get()->map(function (User $member) use ($selected): array {
                        $role = $selected->roleFor($member) ?? 'staff';

                        return [
                            'id' => $member->id,
                            'name' => $member->name,
                            'email' => $member->email,
                            'role' => $role,
                            'role_label' => ucfirst($role),
                            'remove_url' => route('restaurant.team.destroy', [$selected, $member]),
                        ];
                    })->all()
                    : [],
        ]);
    }

    /**
     * Add another restaurant for the signed-in user.
     */
    public function store(Request $request): RedirectResponse
    {
        $limit = $request->user()->restaurantLimit();

        if ($limit !== null && $request->user()->restaurants()->count() >= $limit) {
            return back()->withErrors([
                'name' => __('Your current plan allows :limit restaurants. Upgrade to add more.', ['limit' => $limit]),
            ]);
        }

        $request->merge([
            'username' => Str::lower(trim((string) $request->input('username'))),
        ]);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'username' => [
                'required', 'string', 'max:30', 'alpha_dash',
                Rule::notIn(Profile::RESERVED_USERNAMES),
                Rule::unique('profiles', 'username'),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
        ]);

        $profile = $request->user()->profiles()->create([
            'username' => $data['username'],
            'display_name' => $data['name'],
            'company' => $data['name'],
            'bio' => $data['description'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            'business_type' => Profile::RESTAURANT_TYPE,
            'theme' => 'amber',
            'template' => 'classic',
            'is_published' => true,
            'show_contact_form' => false,
            'menu_currency' => 'USD',
            'menu_ordering_enabled' => false,
        ]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __(':name has been added.', ['name' => $profile->restaurantName()]),
        ]);

        return to_route('restaurant.index', ['restaurant' => $profile->username]);
    }

    /**
     * Update the details of one of the user's restaurants.
     */
    public function update(Request $request, Profile $profile): RedirectResponse
    {
        abort_unless($profile->canManage($request->user()), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'template' => ['sometimes', 'string', Rule::in(array_keys(Profile::RESTAURANT_TEMPLATES))],
            'delivery_enabled' => ['sometimes', 'boolean'],
            'delivery_fee' => ['sometimes', 'numeric', 'min:0', 'max:10000'],
            'min_order' => ['sometimes', 'numeric', 'min:0', 'max:100000'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'service_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'timezone' => ['sometimes', 'string', 'timezone'],
            'opening_hours' => ['nullable', 'array', 'size:7'],
            'opening_hours.*.closed' => ['required_with:opening_hours', 'boolean'],
            'opening_hours.*.open' => ['nullable', 'date_format:H:i'],
            'opening_hours.*.close' => ['nullable', 'date_format:H:i'],
            'sms_notifications_enabled' => ['sometimes', 'boolean'],
            'loyalty_enabled' => ['sometimes', 'boolean'],
            'reservation_slot_capacity' => ['sometimes', 'integer', 'min:0', 'max:500'],
            'reservation_max_party' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'reservation_auto_confirm' => ['sometimes', 'boolean'],
            'reservation_reminder_hours' => ['sometimes', 'integer', 'min:0', 'max:72'],
        ]);

        $profile->update([
            'display_name' => $data['name'],
            'company' => $data['name'],
            'bio' => $data['description'] ?? null,
            'address' => $data['address'] ?? null,
            'phone' => $data['phone'] ?? null,
            ...(isset($data['template']) ? ['restaurant_template' => $data['template']] : []),
            ...(isset($data['delivery_enabled']) ? ['delivery_enabled' => (bool) $data['delivery_enabled']] : []),
            ...(isset($data['delivery_fee']) ? ['delivery_fee_cents' => (int) round(((float) $data['delivery_fee']) * 100)] : []),
            ...(isset($data['min_order']) ? ['min_order_cents' => (int) round(((float) $data['min_order']) * 100)] : []),
            ...(isset($data['tax_rate']) ? ['tax_rate' => round((float) $data['tax_rate'], 2)] : []),
            ...(isset($data['service_rate']) ? ['service_rate' => round((float) $data['service_rate'], 2)] : []),
            ...(array_key_exists('opening_hours', $data) ? ['opening_hours' => $this->normaliseHours($data['opening_hours'])] : []),
            ...(isset($data['timezone']) ? ['timezone' => $data['timezone']] : []),
            ...(isset($data['sms_notifications_enabled']) ? ['sms_notifications_enabled' => (bool) $data['sms_notifications_enabled']] : []),
            ...(isset($data['loyalty_enabled']) ? ['loyalty_enabled' => (bool) $data['loyalty_enabled']] : []),
            ...(isset($data['reservation_slot_capacity']) ? ['reservation_slot_capacity' => (int) $data['reservation_slot_capacity']] : []),
            ...(isset($data['reservation_max_party']) ? ['reservation_max_party' => (int) $data['reservation_max_party']] : []),
            ...(isset($data['reservation_auto_confirm']) ? ['reservation_auto_confirm' => (bool) $data['reservation_auto_confirm']] : []),
            ...(isset($data['reservation_reminder_hours']) ? ['reservation_reminder_hours' => (int) $data['reservation_reminder_hours']] : []),
        ]);

        app(AuditLogger::class)->log($profile, 'settings.updated', __('Restaurant settings updated.'));

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Restaurant details updated.'),
        ]);

        return back();
    }

    /**
     * Mark a guest service request as handled.
     */
    public function completeRequest(Request $request, Profile $profile, ServiceRequest $serviceRequest): RedirectResponse
    {
        abort_unless($profile->canOperate($request->user()), 403);
        abort_unless($serviceRequest->profile_id === $profile->id, 404);

        $serviceRequest->update(['status' => ServiceRequest::STATUS_DONE]);

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => __('Guest request completed.'),
        ]);

        return back();
    }

    /**
     * Normalize the submitted opening hours into seven indexed days.
     *
     * @param  array<int, array{closed?: bool, open?: string|null, close?: string|null}>|null  $hours
     * @return array<int, array{closed: bool, open: string, close: string}>|null
     */
    private function normaliseHours(?array $hours): ?array
    {
        if ($hours === null) {
            return null;
        }

        $normalised = [];

        for ($day = 0; $day < 7; $day++) {
            $row = $hours[$day] ?? [];

            $normalised[$day] = [
                'closed' => (bool) ($row['closed'] ?? false),
                'open' => $row['open'] ?? '09:00',
                'close' => $row['close'] ?? '17:00',
            ];
        }

        return $normalised;
    }

    /**
     * Resolve the restaurant shown on the hub.
     *
     * @param  Collection<int, Profile>  $restaurants
     */
    private function selectedRestaurant(Request $request, Collection $restaurants): ?Profile
    {
        $username = trim((string) $request->query('restaurant', ''));

        if ($username !== '') {
            $match = $restaurants->firstWhere('username', $username);

            if ($match instanceof Profile) {
                return $match;
            }
        }

        return $restaurants->first();
    }

    /**
     * Build the dashboard payload for one restaurant.
     *
     * @return array<string, mixed>
     */
    private function selectedPayload(Profile $profile, Carbon $today): array
    {
        $todayOrders = $profile->orders()->whereDate('created_at', $today);

        return [
            'username' => $profile->username,
            'name' => $profile->restaurantName(),
            'description' => $profile->bio,
            'address' => $profile->address,
            'phone' => $profile->phone,
            'currency' => $profile->menu_currency,
            'template' => $profile->restaurant_template,
            'ordering_enabled' => $profile->menu_ordering_enabled,
            'accepting_orders' => $profile->accepting_orders,
            'delivery_enabled' => $profile->delivery_enabled,
            'delivery_fee_cents' => $profile->delivery_fee_cents,
            'min_order_cents' => $profile->min_order_cents,
            'tax_rate' => $profile->tax_rate,
            'service_rate' => $profile->service_rate,
            'sms_notifications_enabled' => $profile->sms_notifications_enabled,
            'loyalty_enabled' => $profile->loyalty_enabled,
            'reservation_slot_capacity' => $profile->reservation_slot_capacity,
            'reservation_max_party' => $profile->reservation_max_party,
            'reservation_auto_confirm' => $profile->reservation_auto_confirm,
            'reservation_reminder_hours' => $profile->reservation_reminder_hours,
            'opening_hours' => $profile->opening_hours,
            'timezone' => $profile->timezone,
            'is_published' => $profile->is_published,
            'public_url' => route('restaurant.show', $profile->username),
            'menu_url' => route('restaurant.menu', $profile),
            'orders_url' => route('restaurant.orders', $profile),
            'qr_url' => route('restaurant.qr', $profile),
            'item_count' => $profile->menuItems()->count(),
            'category_count' => $profile->menuCategories()->count(),
            'stats' => [
                'today_orders' => (clone $todayOrders)->count(),
                'today_total' => number_format(
                    (int) (clone $todayOrders)->sum('total_cents') / 100,
                    2,
                ).' '.$profile->menu_currency,
                'new_orders' => $profile->orders()->where('status', OrderStatus::New)->count(),
            ],
            'loyalty' => [
                'members' => $profile->loyaltyAccounts()->limit(5)->get()->map(fn ($account): array => [
                    'name' => $account->name ?: $account->phone,
                    'phone' => $account->phone,
                    'points' => $account->points,
                    'visits' => $account->visits,
                ])->all(),
                'members_count' => $profile->loyaltyAccounts()->count(),
            ],
            'recent_orders' => $profile->orders()
                ->withCount('items')
                ->latest('id')
                ->limit(6)
                ->get()
                ->map(fn (Order $order): array => [
                    'id' => $order->id,
                    'reference' => $order->reference,
                    'customer_name' => $order->customer_name,
                    'type_label' => $order->typeLabel(),
                    'table_number' => $order->table_number,
                    'total' => $order->displayTotal(),
                    'status_label' => $order->status->label(),
                    'status_class' => $order->status->colorClass(),
                    'created_human' => $order->created_at?->diffForHumans(),
                    'items_count' => $order->items_count,
                ])
                ->all(),
        ];
    }
}
