<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE `shop_predmety` SET `nazev`='Kostka' WHERE `id_predmetu`= 708
SQL
);

$this->q(<<<SQL
UPDATE `shop_predmety` SET `nazev`='Kostka kruhy' WHERE `id_predmetu`= 71
SQL
);

$this->q(<<<SQL
UPDATE `shop_predmety` SET `model_rok`= 2022, `nabizet_do`= '2022-07-13 23:59:00'  WHERE `id_predmetu` IN (645, 646, 71)
SQL
);

$this->q(<<<SQL
UPDATE `shop_predmety` SET `stav`= 0 WHERE `model_rok`= 2021 AND `id_predmetu` NOT IN (645, 646)
SQL
);
