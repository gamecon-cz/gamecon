<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
    ALTER TABLE uzivatele_role_log
    DROP FOREIGN KEY FK_uzivatele_role_log_to_role_seznam,
    DROP FOREIGN KEY FK_uzivatele_role_log_to_uzivatele_hodnoty,
    DROP FOREIGN KEY FK_uzivatele_role_log_zmenil_to_uzivatele_hodnoty
    SQL,
);

$this->q(<<<SQL
ALTER TABLE uzivatele_role_log
    ADD FOREIGN KEY FK_uzivatele_role_log_to_role_seznam(id_role) REFERENCES role_seznam(id_role) ON DELETE CASCADE ON UPDATE CASCADE
SQL,
);

$this->q(<<<SQL
ALTER TABLE uzivatele_role
    DROP FOREIGN KEY FK_uzivatele_role_role_seznam,
    DROP FOREIGN KEY FK_uzivatele_role_uzivatele_hodnoty
SQL
);

$this->q(<<<SQL
ALTER TABLE uzivatele_role
    ADD FOREIGN KEY FK_uzivatele_role_role_seznam(id_role) REFERENCES role_seznam(id_role) ON DELETE CASCADE ON UPDATE CASCADE,
    ADD FOREIGN KEY FK_uzivatele_role_uzivatele_hodnoty(id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE
SQL
);
