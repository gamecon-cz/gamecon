<?php

declare(strict_types=1);

namespace Gamecon\Shop;

use App\Enum\ProductStateEnum;

/**
 * @deprecated Use App\Enum\ProductStateEnum. Kept so legacy call sites that compare
 *             against the raw int from `shop_predmety`.`stav` keep working; the values
 *             come from the enum, so the two cannot drift apart.
 */
class StavPredmetu
{
    public const MIMO        = ProductStateEnum::RETIRED->value;
    public const VEREJNY     = ProductStateEnum::PUBLIC->value;
    public const PODPULTOVY  = ProductStateEnum::RESTRICTED->value;
    public const POZASTAVENY = ProductStateEnum::SUSPENDED->value;
}
