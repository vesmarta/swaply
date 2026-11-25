<?php

namespace App\Enum;

enum TradeOfferStatus: string
{
    case PENDING = 'pending';
    case COUNTERED = 'countered';
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
    case EXPIRED = 'expired';
    case COMPLETED = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Awaiting response',
            self::COUNTERED => 'Countered',
            self::ACCEPTED => 'Accepted — pending confirmation',
            self::REJECTED => 'Declined',
            self::CANCELLED => 'Cancelled',
            self::EXPIRED => 'Expired',
            self::COMPLETED => 'Completed',
        };
    }

    public function badge(): string
    {
        return match ($this) {
            self::PENDING => 'warning text-dark',
            self::COUNTERED => 'secondary',
            self::ACCEPTED => 'success',
            self::REJECTED => 'danger',
            self::CANCELLED => 'dark',
            self::EXPIRED => 'secondary',
            self::COMPLETED => 'primary',
        };
    }
}
