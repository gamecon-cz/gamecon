<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// A shirt's colour changes from year to year, who it is for does not. Renamed in place, so the
// products keep their tag.

foreach ([
    'tricko_modre'   => ['tricko_vypravecske', 'Vypravěčské tričko'],
    'tricko_cervene' => ['tricko_organizatorske', 'Organizátorské tričko'],
] as $puvodniKod => [$novyKod, $novyNazev]) {
    $this->q(<<<SQL
UPDATE product_tag
SET code = '{$novyKod}', name = '{$novyNazev}', updated_at = NOW()
WHERE code = '{$puvodniKod}'
SQL);
}
