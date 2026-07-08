<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

use Gamecon\Shop\TypPredmetu;

// Snížení ceny standardních obědů a večeří ze 150 na 140 Kč pro letošní ročník.
//
// Mění se:
//  1) aktuální cena položek (shop_predmety.cena_aktualni) -> ovlivní budoucí prodeje,
//  2) zpětně zamrzlá nákupní cena už prodaných (shop_nakupy.cena_nakupni).
//
// Záměrně jen položky s cenou 150 -> levnější varianty ("Oběd ... levnější")
// a orgové ("Oběd s organizátory") mají jinou cenu a NEMĚNÍ se.
// Snídaně se netýká.

$rok        = ROCNIK;
$typJidlo   = TypPredmetu::JIDLO;
$staraCena  = 150;
$novaCena   = 140;

// 1) Nejdřív prodané řádky (dokud jsou položky ještě identifikovatelné cenou 150).
$this->q(
    "UPDATE shop_nakupy
        SET shop_nakupy.cena_nakupni = $novaCena
      WHERE shop_nakupy.rok = $rok
        AND shop_nakupy.cena_nakupni = $staraCena
        AND shop_nakupy.id_predmetu IN (
            SELECT shop_predmety.id_predmetu
              FROM shop_predmety
             WHERE shop_predmety.typ = $typJidlo
               AND shop_predmety.model_rok = $rok
               AND (shop_predmety.nazev LIKE 'Oběd%' OR shop_predmety.nazev LIKE 'Večeře%')
               AND shop_predmety.nazev NOT LIKE '%s organizátory%'
        )"
);

// 2) Poté aktuální cena položek.
$this->q(
    "UPDATE shop_predmety
        SET shop_predmety.cena_aktualni = $novaCena
      WHERE shop_predmety.typ = $typJidlo
        AND shop_predmety.model_rok = $rok
        AND shop_predmety.cena_aktualni = $staraCena
        AND (shop_predmety.nazev LIKE 'Oběd%' OR shop_predmety.nazev LIKE 'Večeře%')
        AND shop_predmety.nazev NOT LIKE '%s organizátory%'"
);
