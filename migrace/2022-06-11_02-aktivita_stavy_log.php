<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE akce_stavy_log(
    akce_stavy_log_id SERIAL,
    id_akce INT NOT NULL,
    id_stav INT NOT NULL,
    kdy TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_akce) REFERENCES akce_seznam(id_akce) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_stav) REFERENCES akce_stav(id_stav) ON DELETE CASCADE ON UPDATE CASCADE
)
SQL
);

$this->q(<<<SQL
INSERT IGNORE INTO akce_stav(id_stav, nazev)
    VALUES (6, 'uzavřená')
SQL
);
