<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role, skryta)
VALUES
    (23, 'CLEN_RADY', 'Člen rady', 'Členové rady mají zvláštní zodpovědnost a pravomoce', -1, 'trvala', 'CLEN_RADY', 0),
    (24, 'SEF_INFOPULTU', 'Šéf infopultu', 'S pravomocemi dělat větší zásahy u přhlášených', -1, 'trvala', 'SEF_INFOPULTU', 0)
SQL
);

$this->q(<<<SQL
INSERT INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava)
VALUES
    (1032, 'Hromadná aktivace aktivit', 'Může použít "Aktivovat hromadně" v aktivitách')
SQL
);

$this->q(<<<SQL
INSERT INTO prava_role
SET id_prava = 1032, id_role = 23
SQL
);
