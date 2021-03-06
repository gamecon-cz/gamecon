<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE akce_instance(
    id_instance INTEGER PRIMARY KEY AUTO_INCREMENT,
    id_hlavni_akce INTEGER NOT NULL,
    CONSTRAINT FOREIGN KEY FK_akce_instance_to_akce_seznam(id_hlavni_akce) REFERENCES akce_seznam(id_akce)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

INSERT INTO akce_instance(id_instance, id_hlavni_akce)
SELECT patri_pod, MIN(id_akce)
FROM akce_seznam
WHERE patri_pod > 0
GROUP BY patri_pod;

ALTER TABLE akce_seznam
MODIFY COLUMN patri_pod INTEGER DEFAULT NULL;

UPDATE akce_seznam
SET patri_pod = NULL
WHERE patri_pod = 0;

ALTER TABLE akce_seznam
ADD CONSTRAINT FOREIGN KEY FK_akce_seznam_to_akce_instance(patri_pod) REFERENCES akce_instance(id_instance)
        ON UPDATE CASCADE ON DELETE RESTRICT;

ALTER TABLE akce_lokace
ADD UNIQUE KEY nazev_rok(nazev, rok);

ALTER TABLE akce_seznam
MODIFY COLUMN stav INTEGER NOT NULL;

CREATE TABLE akce_stav(
    id_stav INTEGER UNIQUE KEY AUTO_INCREMENT,
    nazev VARCHAR(128) PRIMARY KEY
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO'; -- jinak to místo nuly vloží novou autoincrement hodnotu
INSERT INTO akce_stav(id_stav, nazev)
VALUES
(0, 'nová'),
(1, 'aktivovaná'),
(2, 'proběhnutá'),
(3, 'systémová'),
(4, 'publikovaná'),
(5, 'připravená');
SET SQL_MODE = '';

ALTER TABLE akce_seznam
ADD CONSTRAINT FOREIGN KEY FK_akce_seznam_to_akce_stav(stav) REFERENCES akce_stav(id_stav)
    ON UPDATE CASCADE ON DELETE RESTRICT;

CREATE TABLE mutex(
    id_mutex INTEGER UNSIGNED NOT NULL UNIQUE AUTO_INCREMENT,
    akce VARCHAR(128) NOT NULL PRIMARY KEY,
    klic VARCHAR(128) NOT NULL UNIQUE,
    zamknul INTEGER NULL,
    od DATETIME NOT NULL,
    do DATETIME NULL,
    FOREIGN KEY FK_mutex_to_uzivatele_hodnoty(zamknul) REFERENCES uzivatele_hodnoty(id_uzivatele)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci;

ALTER TABLE akce_seznam
MODIFY COLUMN lokace INT NULL DEFAULT NULL;
SQL
);
