<?php

declare(strict_types=1);

namespace Gamecon\Shop;

/**
 * Legacy constants for the `podtyp` column (dropped in migration
 * 2026-03-24-161200-podtyp-to-hotel-tag.php).
 *
 * The `podtyp` string is still emitted virtually by the
 * `shop_predmety_s_typem` view via
 * `CASE WHEN breakfast_included THEN 'hotel' ELSE NULL END AS podtyp`,
 * so legacy callers reading the view continue to see 'hotel' unchanged.
 */
class PodtypPredmetu
{
    public const HOTEL = 'hotel';
}
