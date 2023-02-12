<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE r_zidle_soupis
SET jmeno_zidle = 'GC2014 odjel' -- jediný název kde chybělo GC{ROK}
WHERE id_zidle = -1403
SQL
);

$jakykoliRocnik = \Gamecon\Role\Role::JAKYKOLI_ROCNIK;
$this->q(<<<SQL
ALTER TABLE r_zidle_soupis
ADD COLUMN rocnik INT NULL -- NULL pouze dočasně, viz níže
SQL
);

$this->q(<<<SQL
CREATE TEMPORARY TABLE r_zidle_soupis_rocnik_temp (id_zidle INT PRIMARY KEY, rocnik INT)
SQL
);

$this->q(<<<SQL
INSERT INTO r_zidle_soupis_rocnik_temp (id_zidle, rocnik)
SELECT id_zidle, REGEXP_SUBSTR(jmeno_zidle, '[0-9]{4}')
FROM r_zidle_soupis
WHERE jmeno_zidle REGEXP '[0-9]{4}'
SQL
);

$this->q(<<<SQL
UPDATE r_zidle_soupis
JOIN r_zidle_soupis_rocnik_temp USING(id_zidle)
SET r_zidle_soupis.rocnik = r_zidle_soupis_rocnik_temp.rocnik
SQL
);

$this->q(<<<SQL
DROP TEMPORARY TABLE r_zidle_soupis_rocnik_temp
SQL
);

$idckaTrvalychZidli    = \Gamecon\Role\Role::idckaTrvalychZidli();
$idckaTrvalychZidliSql = implode(',', $idckaTrvalychZidli);

$this->q(<<<SQL
UPDATE r_zidle_soupis
SET rocnik = $jakykoliRocnik
WHERE id_zidle IN ($idckaTrvalychZidliSql)
SQL
);

$rocnik = ROCNIK;
$this->q(<<<SQL
UPDATE r_zidle_soupis
SET rocnik = $rocnik
WHERE rocnik IS NULL
SQL
);

$this->q(<<<SQL
ALTER TABLE r_zidle_soupis
MODIFY COLUMN rocnik INT NOT NULL
SQL
);

$this->q(<<<SQL
ALTER TABLE r_zidle_soupis
ADD UNIQUE INDEX(jmeno_zidle)
SQL
);
