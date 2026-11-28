<?php

namespace App\Enums;

enum PurchaseStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Refunded = 'refunded';

    /**
     * The human readable label.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
            self::Refunded => 'Refunded',
        };
    }

    /**
     * The text colour used when showing the status.
     */
    public function colorClass(): string
    {
        return match ($this) {
            self::Pending => 'text-amber-600',
            self::Completed => 'text-green-600',
            self::Failed => 'text-red-600',
            self::Refunded => 'text-slate-500',
        };
    }

    /**
     * All status values, for validation and selects.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $status): string => $status->value, self::cases());
    }
}
