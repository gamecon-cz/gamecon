<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
alter table akce_typy
    add kod_typu varchar(20) null comment 'kód pro identifikaci například v rozpočtovém reportu'
SQL
);

$this->q(<<<SQL
UPDATE akce_typy t
SET t.kod_typu = 'Larp'
WHERE t.id_typu = 2
SQL
);

$this->q(<<<SQL
UPDATE akce_typy t
SET t.kod_typu = 'AH'
WHERE t.id_typu = 7
SQL
);

$this->q(<<<SQL
UPDATE akce_typy t
SET t.kod_typu = 'RPG'
WHERE t.id_typu = 4
SQL
);

$this->q(<<<SQL
UPDATE akce_typy t
SET t.kod_typu = 'LKD'
WHERE t.id_typu = 8
SQL
);

$this->q(<<<SQL
UPDATE akce_typy t
SET t.kod_typu = 'WG'
WHERE t.id_typu = 6
SQL
);

$this->q(<<<SQL
UPDATE akce_typy t
SET t.kod_typu = 'Epic'
WHERE t.id_typu = 11
SQL
);

$this->q(<<<SQL
UPDATE akce_typy t
SET t.kod_typu = 'Turn'
WHERE t.id_typu = 1
SQL
);

$this->q(<<<SQL
UPDATE akce_typy t
SET t.kod_typu = 'DrD'
WHERE t.id_typu = 9
SQL
);

$this->q(<<<SQL
UPDATE akce_typy t
SET t.kod_typu = 'Pred'
WHERE t.id_typu = 3
SQL
);
