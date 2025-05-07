<?php

use Gamecon\Role\Role;
use Gamecon\Pravo;

// přidání UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC
$this->q(<<<SQL
    INSERT INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava)
    VALUES
        (1037, 'Ubytování může objednat jednu noc', '')
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO prava_role(id_role, id_prava)
VALUES
    (2 /* ORGANIZATOR */, 1037),
    (21 /* PUL_ORG_BONUS_UBYTKO */, 1037),
    (22 /* PUL_ORG_BONUS_TRICKO */, 1037),
    (26 /* MINI_ORG */, 1037),
    (15 /* CESTNY_ORGANIZATOR */, 1037),
    ((SELECT COALESCE(MIN(id_role), 2) /* MIN = latest u ročníkové role */ FROM role_seznam WHERE vyznam_role = 'VYPRAVEC'), 1037),
    ((SELECT COALESCE(MIN(id_role), 2) /* MIN = latest u ročníkové role */ FROM role_seznam WHERE vyznam_role = 'INFOPULT'), 1037),
    ((SELECT COALESCE(MIN(id_role), 2) /* MIN = latest u ročníkové role */ FROM role_seznam WHERE vyznam_role = 'PARTNER'), 1037),
    ((SELECT COALESCE(MIN(id_role), 2) /* MIN = latest u ročníkové role */ FROM role_seznam WHERE vyznam_role = 'HERMAN'), 1037),
    ((SELECT COALESCE(MIN(id_role), 2) /* MIN = latest u ročníkové role */ FROM role_seznam WHERE vyznam_role = 'BRIGADNIK'), 1037)
SQL,
);
