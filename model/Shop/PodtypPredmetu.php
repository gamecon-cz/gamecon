<?php

declare(strict_types=1);

namespace Gamecon\Shop;

/**
 * Legacy constants for the `podtyp` column (dropped in migration
 * 2026-11-07-100009_legacy-catalog-removed.php). `Shop` still derives the value in SQL:
 * `hotel` from `breakfast_included`, `mikina` from the product's `mikina` tag.
 */
class PodtypPredmetu
{
    public const HOTEL = 'hotel';
    public const MIKINA = 'mikina';
}
