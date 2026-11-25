<?php

namespace App\Enum;

enum ListingCondition: string
{
    case NEW = 'new';
    case LIKE_NEW = 'like_new';
    case GOOD = 'good';
    case FAIR = 'fair';
    case POOR = 'poor';

    public function label(): string
    {
        return match ($this) {
            self::NEW => 'New',
            self::LIKE_NEW => 'Like new',
            self::GOOD => 'Good',
            self::FAIR => 'Fair',
            self::POOR => 'For parts / Not working',
        };
    }
}
