<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE uzivatele_role_podle_rocniku (
    id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
    id_uzivatele INT NOT NULL,
    id_role INT NOT NULL,
    od_kdy DATETIME NOT NULL,
    rocnik INT NOT NULL,
    UNIQUE INDEX (id_uzivatele, id_role, rocnik),
    FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele) ON DELETE CASCADE ON UPDATE CASCADE,
    FOREIGN KEY (id_role) REFERENCES role_seznam(id_role) ON DELETE CASCADE ON UPDATE CASCADE,
    INDEX rocnik (rocnik)
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci
SQL
);
