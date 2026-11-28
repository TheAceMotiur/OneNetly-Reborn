<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Profile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class CouponController extends Controller
{
    /**
     * Create a promo code for one restaurant.
     */
    public function store(Request $request, Profile $profile): RedirectResponse
    {
        $this->authorizeProfile($request, $profile);

        $data = $request->validate([
            'code' => [
                'required', 'string', 'max:40', 'alpha_dash',
                Rule::unique('coupons', 'code')->where('profile_id', $profile->id),
            ],
            'type' => ['required', 'string', Rule::in(array_keys(Coupon::TYPES))],
            'value' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'min_order' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'expires_at' => ['nullable', 'date', 'after_or_equal:today'],
        ]);

        if ($data['type'] === Coupon::TYPE_PERCENT && (float) $data['value'] > 100) {
            throw ValidationException::withMessages([
                'value' => __('A percentage discount cannot be more than 100%.'),
            ]);
        }

        $profile->coupons()->create([
            'code' => Str::upper($data['code']),
            'type' => $data['type'],
            'value' => $data['value'],
            'min_order_cents' => (int) round(((float) ($data['min_order'] ?? 0)) * 100),
            'max_uses' => $data['max_uses'] ?? null,
            'expires_at' => $data['expires_at'] ?? null,
            'is_active' => true,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Promo code created.')]);

        return back();
    }

    /**
     * Update a promo code.
     */
    public function update(Request $request, Profile $profile, Coupon $coupon): RedirectResponse
    {
        $this->authorizeProfile($request, $profile);
        abort_unless($coupon->profile_id === $profile->id, 404);

        $data = $request->validate([
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $coupon->update(['is_active' => $request->boolean('is_active', $coupon->is_active)]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Promo code updated.')]);

        return back();
    }

    /**
     * Delete a promo code.
     */
    public function destroy(Request $request, Profile $profile, Coupon $coupon): RedirectResponse
    {
        $this->authorizeProfile($request, $profile);
        abort_unless($coupon->profile_id === $profile->id, 404);

        $coupon->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Promo code deleted.')]);

        return back();
    }

    /**
     * Ensure the signed-in user owns the restaurant.
     */
    private function authorizeProfile(Request $request, Profile $profile): void
    {
        abort_unless($profile->canManage($request->user()), 403);
    }
}
