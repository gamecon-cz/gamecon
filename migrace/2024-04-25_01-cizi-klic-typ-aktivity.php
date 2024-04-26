<?php

/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
UPDATE akce_seznam SET typ = 3
WHERE typ = 99
AND nazev_akce IN ('Slavnostní zahájení', 'Slavnostní zakončení')
SQL,
);

/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
UPDATE akce_seznam SET typ = 0
WHERE typ = 99
AND nazev_akce = 'Prezence'
SQL,
);

/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
UPDATE akce_seznam SET typ = 9
WHERE typ = 99
AND nazev_akce LIKE 'Úvod do Mistrovství v DrD%'
SQL,
);

/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
ALTER TABLE akce_seznam
    ADD FOREIGN KEY (typ) REFERENCES akce_typy(id_typu)
    ON DELETE RESTRICT ON UPDATE RESTRICT;
SQL,
);
