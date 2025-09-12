<?php

$this->q(<<<SQL
INSERT INTO role_seznam
SET
    id_role = 26,
    kod_role = 'MINI_ORG',
    nazev_role = 'Mini-org',
    popis_role = 'Výpomoc při organizaci GC',
    rocnik_role = -1,
    typ_role = 'trvala',
    vyznam_role = 'MINI_ORG',
    skryta = 0,
    kategorie_role = 0
SQL,
);


$sirienIdResult = mysqli_execute_query(
    $this->connection,
    <<<SQL
    SELECT id_uzivatele FROM uzivatele_hodnoty WHERE jmeno_uzivatele = 'Petr' AND prijmeni_uzivatele = 'Mazák'
    SQL,
);

$sirienId = mysqli_fetch_column($sirienIdResult);

if (!$sirienId) {
    return;
}

$result = $this->q(<<<SQL
INSERT INTO `role_texty_podle_uzivatele` (vyznam_role, id_uzivatele, popis_role)
VALUES (
        'MINI_ORG',
        $sirienId,
        'Goblin'
    )
SQL,
);
