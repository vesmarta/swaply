<?php

namespace App\Enum;

enum ListingStatus: string
{
    case PENDING = 'pending';
    case ACTIVE = 'active';
    case REJECTED = 'rejected';
    case RESERVED = 'reserved';
    case TRADED = 'traded';
    case SOLD = 'sold';
    case WITHDRAWN = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::PENDING => 'Pending review',
            self::ACTIVE => 'Active',
            self::REJECTED => 'Rejected',
            self::RESERVED => 'Reserved (deal in progress)',
            self::TRADED => 'Traded',
            self::SOLD => 'Sold',
            self::WITHDRAWN => 'Withdrawn',
        };
    }

    public function isVisibleToPublic(): bool
    {
        return \in_array($this, [self::ACTIVE, self::RESERVED, self::TRADED, self::SOLD], true);
    }
}
