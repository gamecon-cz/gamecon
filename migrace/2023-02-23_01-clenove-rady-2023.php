<?php
/** @var \Godric\DbMigrations\Migration $this */

if (date('Y') === '2023') {

    $this->q(<<<SQL
CREATE TEMPORARY TABLE clenove_rady_temp (id_uzivatele INT UNSIGNED NOT NULL)
SQL
    );

    $this->q(<<<SQL
INSERT INTO clenove_rady_temp (id_uzivatele) VALUES
(53), -- Gandalf
(1112), -- Cemi
(1305), -- Wexxar
(102), -- Sirien
(170), -- Pavel
(1306), -- Lucka
(682) -- Flant
SQL
    );

    $this->q(<<<SQL
SELECT id_uzivatele, 23 /* "Člen rady" */, 1 /* uživatel "System" */
FROM clenove_rady_temp
SQL
    );

    $this->q(<<<SQL
INSERT INTO uzivatele_role (id_uzivatele, id_role, posadil)
SELECT clenove_rady_temp.id_uzivatele, 23 /* "Člen rady" */, 1 /* uživatel "System" */
FROM clenove_rady_temp
JOIN uzivatele_hodnoty
    ON clenove_rady_temp.id_uzivatele = uzivatele_hodnoty.id_uzivatele -- protože testovací DB takové uživatele nezná, tak je pro testy zahodíme
SQL
    );

    $this->q(<<<SQL
INSERT INTO uzivatele_role_log (id_uzivatele, id_role, id_zmenil, zmena)
SELECT clenove_rady_temp.id_uzivatele, 23 /*"Člen rady"*/, 1 /* uživatel "System" */, 'posazen'
FROM clenove_rady_temp
JOIN uzivatele_hodnoty
    ON clenove_rady_temp.id_uzivatele = uzivatele_hodnoty.id_uzivatele -- protože testovací DB takové uživatele nezná, tak je pro testy zahodíme
SQL
    );

    $this->q(<<<SQL
DROP TEMPORARY TABLE clenove_rady_temp
SQL
    );

}
