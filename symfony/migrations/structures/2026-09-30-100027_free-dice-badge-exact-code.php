<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// The year's rule names this year's free dice and badge by exact code. 2026's are the ones
// legacy picked; other years get none, so they give nothing away until the import names one.

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
