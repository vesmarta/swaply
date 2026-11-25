<?php

namespace App\Enum;

enum CashDirection: string
{
    case NONE = 'none';
    case REQUESTER_PAYS = 'requester_pays';
    case OWNER_PAYS = 'owner_pays';

    public function label(): string
    {
        return match ($this) {
            self::NONE => 'No cash',
            self::REQUESTER_PAYS => 'Requester adds cash',
            self::OWNER_PAYS => 'Owner adds cash',
        };
    }
}
