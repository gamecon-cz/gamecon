<?php

use Gamecon\Role\Role;
use Gamecon\Pravo;

// přidání UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC
$sql1 = "
    INSERT INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava)
    VALUES
        (" . Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC . ", 'Ubytování může objednat jednu noc', '')
";

$sql2 = "
INSERT IGNORE INTO prava_role(id_role, id_prava)
VALUES
    (" . Role::ORGANIZATOR          . ", " . Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC . "),
    (" . Role::PUL_ORG_BONUS_UBYTKO . ", " . Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC . "),
    (" . Role::PUL_ORG_BONUS_TRICKO . ", " . Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC . "),
    (" . Role::MINI_ORG             . ", " . Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC . "),
    (" . Role::CESTNY_ORGANIZATOR   . ", " . Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC . "),
    (" . Role::LETOSNI_VYPRAVEC     . ", " . Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC . "),
    (" . Role::LETOSNI_INFOPULT     . ", " . Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC . "),
    (" . Role::LETOSNI_PARTNER      . ", " . Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC . "),
    (" . Role::LETOSNI_HERMAN       . ", " . Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC . "),
    (" . Role::LETOSNI_BRIGADNIK    . ", " . Pravo::UBYTOVANI_MUZE_OBJEDNAT_JEDNU_NOC . ")
";

$this->q($sql1);
$this->q($sql2);
