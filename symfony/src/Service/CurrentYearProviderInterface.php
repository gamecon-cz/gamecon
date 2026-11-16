<?php

declare(strict_types=1);

namespace App\Service;

interface CurrentYearProviderInterface
{
    public function getCurrentYear(): int;
}
