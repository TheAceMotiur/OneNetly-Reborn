<?php

namespace App\Services;

use App\Models\LoyaltyAccount;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class LoyaltyService
{
    /**
     * The number of points needed to earn one currency unit back.
     */
    public const POINTS_PER_UNIT = 100;

    /**
     * Award one point per whole currency unit spent on an order.
     */
    public function award(Order $order): ?LoyaltyAccount
    {
        $order->loadMissing('profile');

        if (! $order->profile->loyalty_enabled) {
            return null;
        }

        $phone = $this->normalisePhone($order->customer_phone);

        if ($phone === '') {
            return null;
        }

        $points = intdiv($order->total_cents, 100);

        return DB::transaction(function () use ($order, $phone, $points): LoyaltyAccount {
            $account = LoyaltyAccount::query()
                ->where('profile_id', $order->profile_id)
                ->where('phone', $phone)
                ->lockForUpdate()
                ->first();

            if ($account === null) {
                $account = LoyaltyAccount::query()->create([
                    'profile_id' => $order->profile_id,
                    'phone' => $phone,
                    'name' => $order->customer_name,
                    'email' => $order->customer_email,
                    'points' => 0,
                    'lifetime_points' => 0,
                    'visits' => 0,
                    'total_spent_cents' => 0,
                ]);
            }

            $account->incrementEach([
                'points' => $points,
                'lifetime_points' => $points,
                'visits' => 1,
                'total_spent_cents' => $order->total_cents,
            ], [
                'name' => $order->customer_name,
                'email' => $order->customer_email ?: $account->email,
                'last_order_at' => now(),
            ]);

            return $account->refresh();
        });
    }

    /**
     * Find the account belonging to a phone number.
     */
    public function forPhone(int $profileId, ?string $phone): ?LoyaltyAccount
    {
        $phone = $this->normalisePhone($phone);

        if ($phone === '') {
            return null;
        }

        return LoyaltyAccount::query()
            ->where('profile_id', $profileId)
            ->where('phone', $phone)
            ->first();
    }

    /**
     * Normalize a phone number for matching guest accounts.
     */
    public function normalisePhone(?string $phone): string
    {
        return preg_replace('/[^\d+]/', '', trim((string) $phone)) ?? '';
    }
}
