<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO r_prava_soupis
    SET id_prava = 111,
        jmeno_prava = 'Administrace - panel Peníze',
        popis_prava = 'Koutek pro šéfa financí GC'
SQL
);

$this->q(<<<SQL
INSERT INTO prava_role
    SET id_prava = 111, -- Administrace - panel Peníze
        id_role = (SELECT id_role FROM role_seznam WHERE kod_role = 'SPRAVCE_FINANCI_GC')
SQL
);
