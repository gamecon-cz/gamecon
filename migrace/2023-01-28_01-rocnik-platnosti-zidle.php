<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE r_zidle_soupis
SET jmeno_zidle = 'GC2014 odjel' -- jediný název kde chybělo GC{ROK}
WHERE id_zidle = -1403
SQL
);

$jakykoliRocnik = \Gamecon\Role\Zidle::JAKYKOLI_ROK;
$this->q(<<<SQL
ALTER TABLE r_zidle_soupis
ADD COLUMN rok INT NULL -- NULL pouze dočasně, viz níže
SQL
);

$this->q(<<<SQL
CREATE TEMPORARY TABLE r_zidle_soupis_rok_temp (id_zidle INT PRIMARY KEY, rok INT)
SQL
);

$this->q(<<<SQL
INSERT INTO r_zidle_soupis_rok_temp (id_zidle, rok)
SELECT id_zidle, REGEXP_SUBSTR(jmeno_zidle, '[0-9]{4}')
FROM r_zidle_soupis
WHERE jmeno_zidle REGEXP '[0-9]{4}'
SQL
);

$this->q(<<<SQL
UPDATE r_zidle_soupis
JOIN r_zidle_soupis_rok_temp USING(id_zidle)
SET r_zidle_soupis.rok = r_zidle_soupis_rok_temp.rok
SQL
);

$this->q(<<<SQL
DROP TEMPORARY TABLE r_zidle_soupis_rok_temp
SQL
);

$idckaTrvalychZidli    = \Gamecon\Role\Zidle::idckaTrvalychZidli();
$idckaTrvalychZidliSql = implode(',', $idckaTrvalychZidli);

$this->q(<<<SQL
UPDATE r_zidle_soupis
SET rok = $jakykoliRocnik
WHERE id_zidle IN ($idckaTrvalychZidliSql)
SQL
);

$rok = ROK;
$this->q(<<<SQL
UPDATE r_zidle_soupis
SET rok = $rok
WHERE rok IS NULL
SQL
);

$this->q(<<<SQL
ALTER TABLE r_zidle_soupis
MODIFY COLUMN rok INT NOT NULL
SQL
);

$this->q(<<<SQL
ALTER TABLE r_zidle_soupis
ADD UNIQUE INDEX(jmeno_zidle)
SQL
);
