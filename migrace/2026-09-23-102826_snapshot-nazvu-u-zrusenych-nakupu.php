<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Nákup si drží název a kód v okamžiku prodeje, takže reporty nad historií nemusí sahat
// na produkt — ten se mezitím mohl přejmenovat. Zrušené nákupy ale ten snapshot nikdy
// nedostaly, přestože je report `finance-report-odhlaseni-neplaticu` vypisuje napříč
// ročníky a jediný zdroj názvu je pro ně dnešní produkt.
//
// Doplnit se to musí teď: až se produkty se stejným kódem sloučí do jednoho, zapsal by
// se sem dnešní název místo toho, který v tom ročníku platil.
$this->q(<<<'SQL'
ALTER TABLE shop_nakupy_zrusene
    ADD product_name VARCHAR(255) DEFAULT NULL,
    ADD product_code VARCHAR(255) DEFAULT NULL
SQL);

// Velikost se bere z varianty, ne z názvu produktu. Seskupení triček pod jednoho
// vlastníka mu velikost z názvu ustřihlo („Tričko účastnické" místo „… XXXL"), takže by
// se do snapshotu uložilo tričko bez velikosti. Varianta ji drží dál a spáruje se přes
// kód, protože zrušené nákupy `variant_id` nemají.
//
// Test na NULL je tu schválně, i když by IF prošlo i bez něj: přes LEFT JOIN varianta
// nemusí existovat a NULL by se protáhl třemi porovnáními, než ho IF vyhodnotí jako
// nepravdu. Tím by správný výsledek závisel na tříhodnotové logice místo na záměru.
$this->q(<<<'SQL'
UPDATE shop_nakupy_zrusene
JOIN shop_predmety ON shop_predmety.id_predmetu = shop_nakupy_zrusene.id_predmetu
LEFT JOIN product_variant
    ON product_variant.code = shop_predmety.kod_predmetu
SET shop_nakupy_zrusene.product_name = IF(
        product_variant.name IS NOT NULL
            AND TRIM(product_variant.name) REGEXP '^(XS|S|M|L|XL|XXL|XXXL|[0-9]+-[0-9]+)$'
            AND shop_predmety.nazev NOT REGEXP CONCAT(
                '[[:<:]]',
                TRIM(product_variant.name),
                '[[:>:]]'
            ),
        CONCAT(shop_predmety.nazev, ' ', TRIM(product_variant.name)),
        shop_predmety.nazev
    ),
    shop_nakupy_zrusene.product_code = shop_predmety.kod_predmetu
SQL);
