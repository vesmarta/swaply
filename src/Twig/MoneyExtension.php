<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MoneyExtension extends AbstractExtension
{
    /** @return list<TwigFilter> */
    public function getFilters(): array
    {
        return [
            new TwigFilter('tnd', $this->formatTnd(...)),
        ];
    }

    public function formatTnd(?int $millimes): string
    {
        if (null === $millimes) {
            return '—';
        }

        return number_format($millimes / 1000, 3, '.', ' ');
    }
}
