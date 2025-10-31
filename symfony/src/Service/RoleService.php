<?php

declare(strict_types=1);

namespace App\Service;

use Gamecon\Role\Role as LegacyRole;

readonly class RoleService
{
    public function __construct(private int $currentYear)
    {
    }

    public function getRegisteredToCurrentYearRoleId(): int
    {
        return $this->getRegisteredToYearRoleId($this->currentYear);
    }

    public function getRegisteredToYearRoleId(int $year): int
    {
        return LegacyRole::prihlasenNaRocnik($year);
    }
}
