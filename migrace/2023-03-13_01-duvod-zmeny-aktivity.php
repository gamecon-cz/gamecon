<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE akce_prihlaseni_log
    ADD COLUMN zdroj_zmeny VARCHAR(128) DEFAULT NULL,
    ADD INDEX zdroj_zmeny(zdroj_zmeny),
    ADD COLUMN rocnik INT UNSIGNED NULL DEFAULT NULL
SQL
);

$this->q(<<<SQL
UPDATE akce_prihlaseni_log
JOIN akce_seznam ON akce_prihlaseni_log.id_akce = akce_seznam.id_akce
SET akce_prihlaseni_log.rocnik = akce_seznam.rok
WHERE TRUE -- jenom aby si IDE nestěžovalo
SQL
);
