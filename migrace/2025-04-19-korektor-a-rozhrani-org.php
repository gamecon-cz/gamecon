<?php

$this->q(<<<SQL
INSERT INTO role_seznam
SET
    id_role = 27,
    kod_role = 'KOREKTOR',
    nazev_role = 'Korektor',
    popis_role = 'Kontrola a opravy textu',
    rocnik_role = -1,
    typ_role = 'trvala',
    vyznam_role = 'KOREKTOR',
    skryta = 0,
    kategorie_role = 1
SQL,
);


$this->q(<<<SQL
INSERT INTO role_seznam
SET
    id_role = 28,
    kod_role = 'ROZHRANI_ORG',
    nazev_role = 'Rozhraní - Org',
    popis_role = 'Organizátor konference Rozhraní',
    rocnik_role = -1,
    typ_role = 'trvala',
    vyznam_role = 'ROZHRANI_ORG',
    skryta = 0,
    kategorie_role = 0
SQL,
);

