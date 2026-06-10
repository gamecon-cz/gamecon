<?php

declare(strict_types=1);

/** @var \Godric\DbMigrations\Migration $this */

use Gamecon\Uzivatel\Finance;

// Poukaz "jedna (nejdražší) aktivita zdarma" se zapisuje jako pevný řádek v `slevy`.
// Dřív se hodnota brala z nejdražší ZAPSANÉ aktivity včetně technických/brigádnických,
// které účastník neplatí (jdou do bonusu vypravěče) -> poukaz vycházel přemrštěně.
// Oprava ve Finance::prepoctiSlevuNaJednuAktivitu() už interní typy vynechává, ale
// existující (přemrštěné) řádky je potřeba jednorázově přepočítat - jinak by se
// přepočítaly až při příští změně role/přihlášky.
Finance::prepocitejVsechnyPoukazyNaJednuAktivitu();
