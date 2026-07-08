<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Přejmenování práva 1012 (MODRE_TRICKO_ZDARMA): bonus za vedení aktivit nově
// dává zdarma libovolné tričko (dřív jen modré), vždy to nejlevnější v košíku.
// Samotné chování je v kódu (Cenik::cena); tady se jen sjednotí popisek práva,
// aby v adminu nemátl výrazem "modré". Placeholder %MODRE_TRICKO_ZDARMA_OD% i
// klíč nastavení zůstávají – mění se pouze text.

$this->q(
    "UPDATE r_prava_soupis
        SET r_prava_soupis.jmeno_prava = 'Tričko zdarma za dosažený bonus %MODRE_TRICKO_ZDARMA_OD%'
      WHERE r_prava_soupis.id_prava = 1012"
);
