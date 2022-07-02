<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE systemove_nastaveni
SET skupina = 'Aktivita'
WHERE skupina = 'Čas'
AND klic IN (
    'AKTIVITA_EDITOVATELNA_X_MINUT_PO_JEJIM_KONCI',
    'AKTIVITA_EDITOVATELNA_X_MINUT_PRED_JEJIM_ZACATKEM',
    'AUTOMATICKY_UZAMKNOUT_AKTIVITU_X_MINUT_PO_ZACATKU',
    'PRIHLASENI_NA_POSLEDNI_CHVILI_X_MINUT_PRED_ZACATKEM_AKTIVITY',
    'UPOZORNIT_NA_NEUZAMKNUTOU_AKTIVITU_S_MAXIMALNE_X_VYPRAVECI',
    'UPOZORNIT_NA_NEUZAMKNUTOU_AKTIVITU_X_MINUT_PO_KONCI'
)
SQL
);

$this->q(<<<SQL
UPDATE systemove_nastaveni
SET nazev = 'Kdy vypravěče upozorníme že nezavřel'
WHERE nazev = 'Kdy vypravěče upozorníme že neuzavřel aktivitu'
SQL
);
