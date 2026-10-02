<?php

declare(strict_types=1);

namespace Gamecon\Shop;

/**
 * Legacy constants for the `podtyp` column (dropped in migration
 * 2026-09-02-100005_podtyp-to-hotel-tag.php). `Shop` still derives the value in SQL:
 * `hotel` from `breakfast_included`, `mikina` from the product's `mikina` tag.
 */
class PodtypPredmetu
{
    public const HOTEL = 'hotel';
    public const MIKINA = 'mikina';
}
