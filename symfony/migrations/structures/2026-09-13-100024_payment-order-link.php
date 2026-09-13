<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Protizápis k anonymnímu prodeji nebyl s prodejem nijak spojený — držely je pohromadě jen
// shodný čas a částka. Když se prodej nedokončil, platba po něm zůstala a nikdo se to
// nedozvěděl; zrušení ani změna prodeje se do platby nepromítnou vůbec.
//
// ON DELETE SET NULL, ne CASCADE: mazat finanční řádek kvůli smazané objednávce je příliš
// tiché. Odebrání platby při zrušení prodeje má být vědomý krok aplikace.
$this->q(<<<SQL
ALTER TABLE platby
    ADD order_id BIGINT UNSIGNED DEFAULT NULL,
    ADD CONSTRAINT FK_platby_order FOREIGN KEY (order_id) REFERENCES shop_order (id) ON DELETE SET NULL
SQL);

$this->q('CREATE INDEX IDX_platby_order ON platby (order_id)');

// Historii lze dopárovat jen tam, kde je to jednoznačné. Legacy psalo platbu i nákup ve
// stejné vteřině stejným operátorem, takže dvojice sedí — ale jen když na tu vteřinu
// připadá právě jeden nákup a právě jedna platba. Objednávky z té doby sdružují celý den
// prodeje, takže podle nich párovat nejde.
$this->q(<<<SQL
UPDATE platby
JOIN (
    SELECT platby.id AS id_platby, MIN(shop_nakupy.order_id) AS order_id
    FROM platby
    JOIN shop_nakupy
      ON shop_nakupy.id_uzivatele = platby.id_uzivatele
     AND shop_nakupy.id_objednatele = platby.provedl
     AND shop_nakupy.datum = platby.provedeno
    WHERE platby.poznamka = 'anonymní prodej'
    GROUP BY platby.id
    HAVING COUNT(DISTINCT shop_nakupy.order_id) = 1
) AS parovani ON parovani.id_platby = platby.id
SET platby.order_id = parovani.order_id
SQL);
