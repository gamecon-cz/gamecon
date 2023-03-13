<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE shop_nakupy
ADD COLUMN id_nakupu SERIAL FIRST
SQL
);

$this->q(<<<SQL
CREATE TABLE IF NOT EXISTS shop_nakupy_zrusene (
    id_nakupu  BIGINT UNSIGNED NOT NULL,
    id_uzivatele INT           NOT NULL,
    id_predmetu  INT           NOT NULL,
    rocnik       SMALLINT      NOT NULL,
    cena_nakupni DECIMAL(6, 2) NOT NULL COMMENT 'aktuální cena v okamžiku nákupu (bez slev)',
    datum_nakupu               TIMESTAMP NOT NULL,
    datum_zruseni              TIMESTAMP DEFAULT CURRENT_TIMESTAMP() NOT NULL,
    zdroj_zruseni              VARCHAR(255) NULL DEFAULT NULL,
    CONSTRAINT FK_zrusene_objednavky_to_shop_predmety
        FOREIGN KEY (id_predmetu) REFERENCES shop_predmety (id_predmetu)
            ON UPDATE CASCADE,
    CONSTRAINT FK_zrusene_objednavky_to_uzivatele_hodnoty
        FOREIGN KEY (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele)
            ON UPDATE CASCADE,
    INDEX datum_zruseni(datum_zruseni),
    INDEX zdroj_zruseni(zdroj_zruseni)
)
SQL
);
