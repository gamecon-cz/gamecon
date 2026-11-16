<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Reads current year from the ROCNIK PHP constant (set in nastaveni/nastaveni.php).
 */
class CurrentYearProvider implements CurrentYearProviderInterface
{
    public function getCurrentYear(): int
    {
        if (defined('ROCNIK')) {
            return (int) ROCNIK;
        }

        return (int) date('Y');
    }
}
