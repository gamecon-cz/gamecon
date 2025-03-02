<?php

$this->q(<<<SQL
INSERT INTO role_seznam
SET
    id_role = 26,
    kod_role = 'MINI_ORG',
    nazev_role = 'Mini-org',
    popis_role = 'TODO: ',
    rocnik_role = -1,
    typ_role = 'trvala',
    vyznam_role = 'MINI_ORG',
    skryta = 0,
    kategorie_role = 0
SQL,
);


$sirienId = 102;

$result = $this->q(<<<SQL
INSERT INTO `role_texty_podle_uzivatele` (vyznam_role, id_uzivatele, popis_role)
VALUES (
        'MINI_ORG',
        $sirienId,
        'TODO: '
    )
SQL,
);
