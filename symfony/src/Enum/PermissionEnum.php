<?php

declare(strict_types=1);

namespace App\Enum;

use Gamecon\Pravo;

/**
 * Rights an API operation can require. The values are the legacy ones by construction, so a
 * case can never point at a different right than the same name does in Gamecon\Pravo. Add a
 * case when an operation first needs it.
 */
enum PermissionEnum: int
{
    case ADMINISTRACE_INFOPULT = Pravo::ADMINISTRACE_INFOPULT;
    case ADMINISTRACE_UBYTOVANI = Pravo::ADMINISTRACE_UBYTOVANI;
}
