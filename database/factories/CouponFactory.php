<?php

namespace Database\Factories;

use App\Models\Coupon;
use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Coupon>
 */
class CouponFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'profile_id' => Profile::factory(),
            'code' => Str::upper(Str::random(8)),
            'type' => Coupon::TYPE_PERCENT,
            'value' => '10.00',
            'min_order_cents' => 0,
            'max_uses' => null,
            'used_count' => 0,
            'expires_at' => null,
            'is_active' => true,
        ];
    }
}
