<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE r_zidle_soupis
    CHANGE COLUMN id_zidle id_role          INT AUTO_INCREMENT,
    CHANGE COLUMN kod_zidle kod_role        VARCHAR(36) NOT NULL,
    CHANGE COLUMN jmeno_zidle nazev_role    VARCHAR(255) NOT NULL,
    CHANGE COLUMN popis_zidle popis_role    TEXT NOT NULL,
    CHANGE COLUMN rocnik rocnik_role        INT NOT NULL,
    CHANGE COLUMN typ_zidle typ_role        VARCHAR(24) NOT NULL,
    CHANGE COLUMN vyznam vyznam_role        VARCHAR(48) NOT NULL,
    DROP INDEX jmeno_zidle,
    DROP INDEX kod_zidle,
    ADD UNIQUE INDEX(nazev_role),
    ADD UNIQUE INDEX(kod_role)
SQL
);

$this->q(<<<SQL
RENAME TABLE r_zidle_soupis TO role_seznam
SQL
);

$this->q(<<<SQL
ALTER TABLE r_uzivatele_zidle
    CHANGE COLUMN id_zidle id_role INT NOT NULL,
    DROP FOREIGN KEY FK_r_uzivatele_zidle_r_zidle_soupis,
    DROP FOREIGN KEY FK_r_uzivatele_zidle_uzivatele_hodnoty,
    DROP INDEX id_zidle
SQL
);

$this->q(<<<SQL
ALTER TABLE r_uzivatele_zidle
    ADD FOREIGN KEY FK_uzivatele_role_role_seznam(id_role) REFERENCES role_seznam(id_role),
    ADD FOREIGN KEY FK_uzivatele_role_uzivatele_hodnoty(id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele)
SQL
);

$this->q(<<<SQL
RENAME TABLE r_uzivatele_zidle TO uzivatele_role
SQL
);

$this->q(<<<SQL
ALTER TABLE r_uzivatele_zidle_log
    CHANGE COLUMN id_zidle id_role INT NOT NULL,
    DROP FOREIGN KEY FK_r_uzivatele_zidle_log_to_r_zidle_soupis,
    DROP FOREIGN KEY FK_r_uzivatele_zidle_log_to_uzivatele_hodnoty,
    DROP FOREIGN KEY FK_r_uzivatele_zidle_log_zmenil_to_uzivatele_hodnoty
SQL
);

$this->q(<<<SQL
ALTER TABLE r_uzivatele_zidle_log
    ADD FOREIGN KEY FK_uzivatele_role_log_to_role_seznam(id_role) REFERENCES role_seznam(id_role),
    ADD FOREIGN KEY FK_uzivatele_role_log_to_uzivatele_hodnoty(id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele),
    ADD FOREIGN KEY FK_uzivatele_role_log_zmenil_to_uzivatele_hodnoty(id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele)
SQL
);

$this->q(<<<SQL
RENAME TABLE r_uzivatele_zidle_log TO uzivatele_role_log
SQL
);

$this->q(<<<SQL
DROP VIEW IF EXISTS platne_zidle
SQL
);

$this->q(<<<SQL
CREATE SQL SECURITY INVOKER VIEW platne_role
AS SELECT * FROM role_seznam
WHERE rocnik_role IN ((SELECT hodnota FROM systemove_nastaveni WHERE klic = 'ROCNIK' LIMIT 1), -1)
SQL
);

$this->q(<<<SQL
DROP VIEW IF EXISTS platne_zidle_uzivatelu
SQL
);

$this->q(<<<SQL
CREATE SQL SECURITY INVOKER VIEW platne_role_uzivatelu
AS SELECT uzivatele_role.*
   FROM uzivatele_role
   JOIN platne_role ON uzivatele_role.id_role = platne_role.id_role
SQL
);

$this->q(<<<SQL
ALTER TABLE r_prava_zidle
    CHANGE COLUMN id_zidle id_role int not null,
    DROP FOREIGN KEY FK_r_prava_zidle_to_r_prava_soupis,
    ADD FOREIGN KEY FK_prava_role_to_r_prava_soupis(id_prava) REFERENCES r_prava_soupis (id_prava) ON UPDATE CASCADE ON DELETE CASCADE,
    DROP FOREIGN KEY FK_r_prava_zidle_to_r_zidle_soupis,
    ADD foreign key FK_prava_role_to_role_seznam(id_role) REFERENCES role_seznam(id_role) ON UPDATE CASCADE ON DELETE CASCADE
SQL
);

$this->q(<<<SQL
RENAME TABLE r_prava_zidle TO prava_role
SQL
);

if (file_exists(LOGY . '/zidle.log')) {
    rename(LOGY . '/zidle.log', LOGY . '/role.log');
}
