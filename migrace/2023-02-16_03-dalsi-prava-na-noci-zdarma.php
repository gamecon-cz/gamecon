<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava) VALUES
    (1029, 'Čtvrteční noc zdarma', ''),
    (1030, 'Páteční noc zdarma', ''),
    (1031, 'Sobotní noc zdarma', '')
SQL
);
