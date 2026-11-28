<?php

namespace App\Services;

use App\Mail\NewOrderReceived;
use App\Mail\OrderConfirmation;
use App\Models\Coupon;
use App\Models\DeliveryZone;
use App\Models\LoyaltyAccount;
use App\Models\MenuItem;
use App\Models\MenuOption;
use App\Models\Order;
use App\Models\Profile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class OrderPlacementService
{
    /**
     * Validate prices, apply charges and store an order.
     *
     * @param  array{
     *     customer_name: string,
     *     customer_phone: string,
     *     customer_email?: string|null,
     *     order_type: string,
     *     table_number?: string|null,
     *     delivery_address?: string|null,
     *     delivery_postcode?: string|null,
     *     scheduled_for?: string|null,
     *     coupon_code?: string|null,
     *     redeem_points?: bool|null,
     *     notes?: string|null,
     *     tip_cents?: int|null,
     *     items: array<int, array{id: int|string, quantity: int, notes?: string|null, options?: array<int, int|string>}>
     * }  $data
     */
    public function place(Profile $profile, array $data, bool $notify = true): Order
    {
        if ($data['order_type'] === 'delivery' && ! $profile->delivery_enabled) {
            throw ValidationException::withMessages([
                'order_type' => __('Delivery is not available at this restaurant right now.'),
            ]);
        }

        $menuItems = $this->availableMenuItems($profile, $data['items']);
        $zone = $this->deliveryZoneFor($profile, $data);

        if (filled($data['scheduled_for'] ?? null)) {
            $scheduledFor = Carbon::parse((string) $data['scheduled_for']);

            if ($scheduledFor->isAfter(now()->addDays(14))) {
                throw ValidationException::withMessages([
                    'scheduled_for' => __('Orders can be scheduled up to two weeks ahead.'),
                ]);
            }
        }

        $order = DB::transaction(function () use ($data, $menuItems, $profile, $zone): Order {
            $subtotal = 0;
            $lines = [];

            foreach ($data['items'] as $line) {
                $menuItem = $menuItems->get((int) $line['id']);

                if (! $menuItem instanceof MenuItem) {
                    throw ValidationException::withMessages([
                        'items' => __('Some items are no longer available. Please refresh the menu and try again.'),
                    ]);
                }

                $basePrice = $menuItem->priceInCents();

                if ($basePrice === null) {
                    throw ValidationException::withMessages([
                        'items' => __('One of the items does not have an orderable price. Please contact the restaurant.'),
                    ]);
                }

                $chosenOptions = $this->selectedOptions($menuItem, $line['options'] ?? []);
                $unitPrice = $basePrice + array_sum(array_column($chosenOptions, 'price_cents'));

                $quantity = (int) $line['quantity'];
                $lineTotal = $unitPrice * $quantity;
                $subtotal += $lineTotal;

                $lines[] = [
                    'menu_item_id' => $menuItem->id,
                    'name' => $menuItem->name,
                    'unit_price_cents' => $unitPrice,
                    'quantity' => $quantity,
                    'line_total_cents' => $lineTotal,
                    'notes' => $line['notes'] ?? null,
                    'options' => $chosenOptions === [] ? null : $chosenOptions,
                ];
            }

            $minimum = max(
                $profile->min_order_cents,
                $zone instanceof DeliveryZone ? $zone->min_order_cents : 0,
            );

            if ($minimum > 0 && $subtotal < $minimum) {
                throw ValidationException::withMessages([
                    'items' => __('The minimum order is :amount.', [
                        'amount' => number_format($minimum / 100, 2).' '.$profile->menu_currency,
                    ]),
                ]);
            }

            $coupon = $this->couponFor($profile, $data['coupon_code'] ?? null, $subtotal);
            $discount = $coupon?->discountFor($subtotal) ?? 0;
            $discountedSubtotal = max(0, $subtotal - $discount);

            $pointsRedeemed = 0;
            $loyaltyDiscount = 0;

            if (($data['redeem_points'] ?? false) && $discountedSubtotal > 0) {
                $phone = app(LoyaltyService::class)->normalisePhone($data['customer_phone']);

                $account = LoyaltyAccount::query()
                    ->where('profile_id', $profile->id)
                    ->where('phone', $phone)
                    ->lockForUpdate()
                    ->first();

                if ($account instanceof LoyaltyAccount) {
                    $pointsRedeemed = min($account->points, $discountedSubtotal);
                    $loyaltyDiscount = $pointsRedeemed;

                    if ($pointsRedeemed > 0) {
                        $account->decrement('points', $pointsRedeemed);
                        $discountedSubtotal -= $pointsRedeemed;
                    }
                }
            }

            $deliveryFee = $data['order_type'] === 'delivery'
                ? ($zone instanceof DeliveryZone ? $zone->fee_cents : $profile->delivery_fee_cents)
                : 0;
            $service = (int) round($discountedSubtotal * $profile->service_rate / 100);
            $tax = (int) round(($discountedSubtotal + $deliveryFee + $service) * $profile->tax_rate / 100);
            $tip = (int) ($data['tip_cents'] ?? 0);

            $order = $profile->orders()->create([
                'reference' => $this->reference(),
                'customer_name' => $data['customer_name'],
                'customer_phone' => $data['customer_phone'],
                'customer_email' => $data['customer_email'] ?? null,
                'order_type' => $data['order_type'],
                'table_number' => $data['table_number'] ?? null,
                'delivery_address' => $data['delivery_address'] ?? null,
                'delivery_postcode' => $data['delivery_postcode'] ?? null,
                'scheduled_for' => $data['scheduled_for'] ?? null,
                'coupon_code' => $coupon?->code,
                'notes' => $data['notes'] ?? null,
                'subtotal_cents' => $subtotal,
                'discount_cents' => $discount,
                'points_redeemed' => $pointsRedeemed,
                'loyalty_discount_cents' => $loyaltyDiscount,
                'delivery_fee_cents' => $deliveryFee,
                'service_cents' => $service,
                'tax_cents' => $tax,
                'tip_cents' => $tip,
                'total_cents' => $discountedSubtotal + $deliveryFee + $service + $tax + $tip,
                'currency' => $profile->menu_currency,
                'status' => 'new',
            ]);

            $order->items()->createMany($lines);

            return $order->load('items');
        });

        try {
            app(LoyaltyService::class)->award($order);
        } catch (Throwable $exception) {
            report($exception);
        }

        if ($notify) {
            $this->notify($profile, $order);
        }

        return $order;
    }

    /**
     * Email the owner and call the POS webhook after the response.
     */
    private function notify(Profile $profile, Order $order): void
    {
        try {
            Mail::to($profile->user->email)->send(new NewOrderReceived($order));
        } catch (Throwable $exception) {
            report($exception);
        }

        if (filled($order->customer_email)) {
            try {
                Mail::to((string) $order->customer_email)->send(new OrderConfirmation($order));
            } catch (Throwable $exception) {
                report($exception);
            }
        }

        app()->terminating(function () use ($order): void {
            try {
                app(PosWebhookDispatcher::class)->orderCreated($order);
            } catch (Throwable $exception) {
                report($exception);
            }

            try {
                app(SmsNotifier::class)->orderPlaced($order);
            } catch (Throwable $exception) {
                report($exception);
            }

            try {
                app(WhatsAppNotifier::class)->orderPlaced($order);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    /**
     * Find the delivery zone that covers the order address.
     *
     * @param  array{order_type: string, delivery_postcode?: string|null}  $data
     */
    private function deliveryZoneFor(Profile $profile, array $data): ?DeliveryZone
    {
        if ($data['order_type'] !== 'delivery') {
            return null;
        }

        $zones = $profile->deliveryZones()->where('is_active', true)->get();

        if ($zones->isEmpty()) {
            return null;
        }

        $postcode = DeliveryZone::normalisePostcode((string) ($data['delivery_postcode'] ?? ''));
        $zone = $zones->first(fn (DeliveryZone $candidate): bool => $candidate->covers($postcode));

        if (! $zone instanceof DeliveryZone) {
            throw ValidationException::withMessages([
                'delivery_postcode' => $postcode === ''
                    ? __('Enter the postcode so we can check the delivery area.')
                    : __('We do not deliver to :postcode yet.', ['postcode' => $postcode]),
            ]);
        }

        return $zone;
    }

    /**
     * Load only available items that belong to the ordering profile.
     *
     * @param  array<int, array{id: int|string, quantity: int, notes?: string|null, options?: array<int, int|string>}>  $lines
     * @return Collection<int, MenuItem>
     */
    private function availableMenuItems(Profile $profile, array $lines): Collection
    {
        $ids = collect($lines)->pluck('id')->map(fn (mixed $id): int => (int) $id)->unique();

        $items = MenuItem::query()
            ->whereIn('id', $ids)
            ->where('is_available', true)
            ->whereHas('category', fn ($query) => $query
                ->where('profile_id', $profile->id)
                ->where('is_active', true))
            ->with('optionGroups.options')
            ->get()
            ->keyBy('id');

        if ($items->count() !== $ids->count()) {
            throw ValidationException::withMessages([
                'items' => __('Some items are no longer available. Please refresh the menu and try again.'),
            ]);
        }

        return $items;
    }

    /**
     * Validate, constrain and price the options chosen for one order line.
     *
     * @param  array<int, int|string>  $optionIds
     * @return array<int, array{group: string, name: string, price_cents: int}>
     */
    private function selectedOptions(MenuItem $item, array $optionIds): array
    {
        $optionIds = array_values(array_unique(array_map(intval(...), $optionIds)));
        $groups = $item->optionGroups;

        $allowedIds = $groups
            ->flatMap(fn ($group) => $group->options->where('is_available', true)->pluck('id'))
            ->all();

        if (array_diff($optionIds, $allowedIds) !== []) {
            throw ValidationException::withMessages([
                'items' => __('Some options are no longer available. Please refresh the menu and try again.'),
            ]);
        }

        $chosen = [];

        foreach ($groups as $group) {
            $available = $group->options->where('is_available', true)->keyBy('id');
            $selections = array_values(array_filter(
                $optionIds,
                fn (int $id): bool => $available->has($id),
            ));

            if ($group->is_required && $available->isNotEmpty() && $selections === []) {
                throw ValidationException::withMessages([
                    'items' => __('Please choose :group for :item.', [
                        'group' => $group->name,
                        'item' => $item->name,
                    ]),
                ]);
            }

            if (! $group->allow_multiple && count($selections) > 1) {
                throw ValidationException::withMessages([
                    'items' => __('Please choose only one :group for :item.', [
                        'group' => $group->name,
                        'item' => $item->name,
                    ]),
                ]);
            }

            foreach ($selections as $id) {
                /** @var MenuOption $option */
                $option = $available->get($id);

                $chosen[] = [
                    'group' => $group->name,
                    'name' => $option->name,
                    'price_cents' => $option->priceInCents(),
                ];
            }
        }

        return $chosen;
    }

    /**
     * Find and consume a promo code for this order.
     */
    private function couponFor(Profile $profile, ?string $code, int $subtotal): ?Coupon
    {
        if (blank($code)) {
            return null;
        }

        $coupon = $profile->coupons()
            ->whereRaw('UPPER(code) = ?', [Str::upper(trim($code))])
            ->lockForUpdate()
            ->first();

        if (! $coupon instanceof Coupon || ! $coupon->isUsableFor($subtotal)) {
            throw ValidationException::withMessages([
                'coupon_code' => __('This promo code is not valid for this order.'),
            ]);
        }

        $coupon->increment('used_count');

        return $coupon->refresh();
    }

    /**
     * Generate a short, unique order reference.
     */
    private function reference(): string
    {
        do {
            $reference = 'ORD-'.Str::upper(Str::random(6));
        } while (Order::query()->where('reference', $reference)->exists());

        return $reference;
    }
}
