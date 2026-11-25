<?php

namespace App\Constant;

final class Governorates
{
    public const ALL = [
        'Ariana',
        'Béja',
        'Ben Arous',
        'Bizerte',
        'Gabès',
        'Gafsa',
        'Jendouba',
        'Kairouan',
        'Kasserine',
        'Kébili',
        'Le Kef',
        'Mahdia',
        'Manouba',
        'Médenine',
        'Monastir',
        'Nabeul',
        'Sfax',
        'Sidi Bouzid',
        'Siliana',
        'Sousse',
        'Tataouine',
        'Tozeur',
        'Tunis',
        'Zaghouan',
    ];

    /** @return array<string, string> */
    public static function choices(): array
    {
        return array_combine(self::ALL, self::ALL);
    }
}
