<?php

namespace App\Enum;

enum PurchaseStatus: string
{
    case PENDING = 'pending';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending',
            self::ACCEPTED => 'Accepted',
            self::REJECTED => 'Declined',
            self::CANCELLED => 'Cancelled',
            self::COMPLETED => 'Completed',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::PENDING => 'warning',
            self::ACCEPTED => 'info',
            self::REJECTED => 'danger',
            self::CANCELLED => 'secondary',
            self::COMPLETED => 'success',
        };
    }
}
