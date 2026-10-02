<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// A cancelled purchase keeps its variant too. Paired through its row's code, the same match
// every purchase was given its variant by; the row is the item that was bought, the code
// snapshot is not (the cart snapshots the model's code for every size).
$this->q(<<<'SQL'
ALTER TABLE shop_nakupy_zrusene
    ADD COLUMN IF NOT EXISTS variant_id BIGINT UNSIGNED DEFAULT NULL
SQL);

$this->q(<<<'SQL'
UPDATE shop_nakupy_zrusene
    INNER JOIN shop_predmety AS vlastni_radek ON vlastni_radek.id_predmetu = shop_nakupy_zrusene.id_predmetu
    INNER JOIN product_variant ON product_variant.code = vlastni_radek.kod_predmetu
SET shop_nakupy_zrusene.variant_id = product_variant.id
WHERE shop_nakupy_zrusene.variant_id IS NULL
SQL);

// The deploy migrates without strict mode: NOT NULL would turn a missing variant into 0 and the
// foreign key would not check it, so a cancelled purchase without a variant has to stop us here.
$bezVarianty = (int) $this->q('SELECT COUNT(*) FROM shop_nakupy_zrusene WHERE variant_id IS NULL')->fetchColumn();
if ($bezVarianty > 0) {
    throw new \RuntimeException("Zrušených nákupů bez varianty: {$bezVarianty}. Jejich řádek katalogu nemá variantu se stejným kódem — doplň ji a migraci spusť znovu.");
}

// IF EXISTS, so a run that failed between dropping and re-adding the key can be repeated.
$this->q(<<<'SQL'
ALTER TABLE shop_nakupy_zrusene
    DROP FOREIGN KEY IF EXISTS FK_nakupy_zrusene_variant
SQL);

$this->q(<<<'SQL'
ALTER TABLE shop_nakupy_zrusene
    MODIFY variant_id BIGINT UNSIGNED NOT NULL
SQL);

$this->q(<<<'SQL'
ALTER TABLE shop_nakupy_zrusene
    ADD CONSTRAINT FK_nakupy_zrusene_variant FOREIGN KEY (variant_id) REFERENCES product_variant (id) ON DELETE RESTRICT
SQL);
