<?php

namespace App\Enum;

enum ListingType: string
{
    case SWAP = 'swap';
    case SALE = 'sale';
    case BOTH = 'both';

    public function label(): string
    {
        return match ($this) {
            self::SWAP => 'Swap only',
            self::SALE => 'Sale only',
            self::BOTH => 'Swap or sale',
        };
    }

    public function allowsSwap(): bool
    {
        return $this !== self::SALE;
    }

    public function allowsSale(): bool
    {
        return $this !== self::SWAP;
    }
}
