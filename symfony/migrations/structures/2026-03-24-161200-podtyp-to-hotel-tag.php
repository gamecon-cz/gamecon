<?php

/** @var \Godric\DbMigrations\Migration $this */

// Add breakfast_included column: boolean attribute on accommodation products
// that signals "price already includes breakfast" (hotel rooms).
// Replaces the earlier design that modelled this as a ProductTag 'hotel' —
// a tag would have been inconsistent with the other category tags
// (predmet/ubytovani/tricko/jidlo/vstupne/parcon/proplaceni-bonusu), all of
// which are mutually-exclusive categories, whereas hotel-ness is an attribute
// that only ever coexists with the ubytovani category.
$this->q(<<<SQL
ALTER TABLE shop_predmety
    ADD COLUMN breakfast_included TINYINT(1) NOT NULL DEFAULT 0
        COMMENT 'True iff cena_aktualni already includes breakfast (e.g. hotel rooms)'
SQL,
);

// Migrate legacy podtyp='hotel' rows → breakfast_included=1, then drop podtyp.
// Guarded by column-exists check because the podtyp column was only ever
// added by migration 2026-03-17-144555_shop-predmety-podtyp.php (ticket 1406)
// and may be missing on branches that never had it.
$podtypColumnExists = $this->q(<<<SQL
SELECT COUNT(*) FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'shop_predmety'
  AND COLUMN_NAME = 'podtyp'
SQL,
)->fetch(PDO::FETCH_COLUMN);

if ($podtypColumnExists) {
    $this->q(<<<SQL
UPDATE shop_predmety
SET breakfast_included = 1
WHERE podtyp = 'hotel'
SQL,
    );

    $this->q('ALTER TABLE shop_predmety DROP COLUMN podtyp');
}
