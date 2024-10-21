<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE FUNCTION IF NOT EXISTS aktualniRocnik() RETURNS int READS SQL DATA
RETURN (select hodnota from systemove_nastaveni where klic = 'ROCNIK' limit 1)
SQL
);

$this->q(<<<SQL
CREATE FUNCTION IF NOT EXISTS systemoveNastaveni(klic varchar(128)) RETURNS varchar(255) READS SQL DATA
    RETURN (select hodnota from systemove_nastaveni where systemove_nastaveni.klic collate utf8mb3_general_ci = klic limit 1)
SQL
);

$this->q(<<<SQL
CREATE FUNCTION IF NOT EXISTS maPravo(user int, pravo int) RETURNS bool READS SQL DATA
RETURN exists(select 1
              from platne_role_uzivatelu
                join prava_role on prava_role.id_role = platne_role_uzivatelu.id_role
              where platne_role_uzivatelu.id_uzivatele = user and prava_role.id_prava = pravo)
SQL
);

$this->q(<<<SQL
CREATE FUNCTION IF NOT EXISTS delkaAktivityJakoNasobekStandardni(id_akce int) RETURNS decimal(4, 2) READS SQL DATA
    RETURN
        CASE (select hour(timediff(akce_seznam.konec, akce_seznam.zacatek)) from akce_seznam where akce_seznam.id_akce = id_akce limit 1)
            WHEN 1 THEN 0.25
            WHEN 2 THEN 0.5
            WHEN 3 THEN 1
            WHEN 4 THEN 1
            WHEN 5 THEN 1
            WHEN 6 THEN 1.5
            WHEN 7 THEN 1.5
            WHEN 8 THEN 2
            WHEN 9 THEN 2
            WHEN 10 THEN 2.5
            WHEN 11 THEN 2.5
            WHEN 12 THEN 3
            WHEN 13 THEN 3
        END
SQL
);
