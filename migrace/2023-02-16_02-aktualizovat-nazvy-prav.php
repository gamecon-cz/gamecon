<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE r_prava_soupis
    SET jmeno_prava = 'Administrace - panel Infopult'
    WHERE jmeno_prava = 'Administrace - panel Úvod'
SQL
);

$this->q(<<<SQL
UPDATE r_prava_soupis
    SET jmeno_prava = 'Administrace - panel Uživatel'
    WHERE jmeno_prava = 'Administrace - panel Ubytování'
SQL
);

$this->q(<<<SQL
UPDATE r_prava_soupis
    SET jmeno_prava = 'Administrace - panel Aktivity'
    WHERE jmeno_prava = 'Administrace - panel Akce'
SQL
);
