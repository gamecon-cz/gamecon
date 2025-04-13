<?php

use Gamecon\Role\Role;

// přidání UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC
$this->q(<<<SQL
    INSERT INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava)
    VALUES
        (1037, 'Ubytování může objednat jednu noc', '')
SQL,
);

$sql = "
INSERT IGNORE INTO prava_role(id_role, id_prava)
VALUES
    (2, 1037),
    (21, 1037),
    (22, 1037),
    (15, 1037),
    (26, 1037),
    (" . Role::LETOSNI_VYPRAVEC . ", 1037),
    (" . Role::LETOSNI_INFOPULT . ", 1037),
    (" . Role::LETOSNI_PARTNER . ", 1037),
    (" . Role::LETOSNI_HERMAN . ", 1037),
    (" . Role::LETOSNI_BRIGADNIK . ", 1037)
";

$this->q($sql);
