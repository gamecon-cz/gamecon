<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE akce_typy
    ADD kod_typu VARCHAR(20) NULL COMMENT 'kód pro identifikaci například v rozpočtovém reportu'
SQL
);

$this->q(<<<SQL
UPDATE akce_typy
SET kod_typu = 'Larp'
WHERE id_typu = 2
SQL
);

$this->q(<<<SQL
UPDATE akce_typy
SET kod_typu = 'AH'
WHERE id_typu = 7
SQL
);

$this->q(<<<SQL
UPDATE akce_typy
SET kod_typu = 'RPG'
WHERE id_typu = 4
SQL
);

$this->q(<<<SQL
UPDATE akce_typy
SET kod_typu = 'LKD'
WHERE id_typu = 8
SQL
);

$this->q(<<<SQL
UPDATE akce_typy
SET kod_typu = 'WG'
WHERE id_typu = 6
SQL
);

$this->q(<<<SQL
UPDATE akce_typy
SET kod_typu = 'Epic'
WHERE id_typu = 11
SQL
);

$this->q(<<<SQL
UPDATE akce_typy
SET kod_typu = 'Turn'
WHERE id_typu = 1
SQL
);

$this->q(<<<SQL
UPDATE akce_typy
SET kod_typu = 'DrD'
WHERE id_typu = 9
SQL
);

$this->q(<<<SQL
UPDATE akce_typy
SET kod_typu = 'Prednasky'
WHERE id_typu = 3
SQL
);
