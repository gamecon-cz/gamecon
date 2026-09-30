<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Free dice and badge go to this year's item only, as legacy Cenik did, instead of any
// item whose code contains "kostka"/"placka". The year's rule names it by exact code.
//
// 2026's items are the ones legacy picked: the dice was the one flagged je_letosni_hlavni
// (a column dropped earlier on this branch), the badge the one its tie-break chose. Rules
// of other years get no code, so they give nothing away until the e-shop import names one.

$this->q(<<<'SQL'
UPDATE discount_rule
SET parameters = '{"scope":"product_code","effect":"free","codeFragment":"kostka","productCode":"kostka_2026_verne","maxQuantity":1}'
WHERE code = 'kostka_zdarma' AND year = 2026
SQL);

$this->q(<<<'SQL'
UPDATE discount_rule
SET parameters = '{"scope":"product_code","effect":"free","codeFragment":"placka","productCode":"placka_2026_verne","maxQuantity":1}'
WHERE code = 'placka_zdarma' AND year = 2026
SQL);

$this->q(<<<'SQL'
UPDATE discount_rule
SET parameters = '{"scope":"product_code","effect":"free","codeFragment":"kostka","maxQuantity":1}'
WHERE code = 'kostka_zdarma' AND year <> 2026
SQL);

$this->q(<<<'SQL'
UPDATE discount_rule
SET parameters = '{"scope":"product_code","effect":"free","codeFragment":"placka","maxQuantity":1}'
WHERE code = 'placka_zdarma' AND year <> 2026
SQL);
