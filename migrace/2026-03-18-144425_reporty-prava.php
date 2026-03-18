<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q("
CREATE TABLE reporty_prava (
    id_reportu bigint(20) unsigned NOT NULL,
    id_prava   bigint(20) NOT NULL,
    PRIMARY KEY (id_reportu, id_prava),
    KEY (id_reportu),
    KEY (id_prava),
    FOREIGN KEY (id_reportu) REFERENCES reporty (id) ON DELETE CASCADE,
    FOREIGN KEY (id_prava) REFERENCES r_prava_soupis (id_prava) ON DELETE CASCADE
)");

// finance-report-eshop → requires ADMINISTRACE_FINANCE (108)
$this->q("INSERT INTO reporty_prava (id_reportu, id_prava)
    SELECT id, 108 FROM reporty WHERE skript = 'finance-report-eshop'");

// update-zustatku → requires ADMINISTRACE_NASTAVENI (110)
$this->q("INSERT INTO reporty_prava (id_reportu, id_prava)
    SELECT id, 110 FROM reporty WHERE skript = 'update-zustatku'");
