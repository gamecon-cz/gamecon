<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE `shop_predmety`
    DROP COLUMN `kod_predmetu`;
SQL
);

$this->q(<<<SQL
ALTER TABLE `shop_predmety`
    ADD COLUMN `kod_predmetu` VARCHAR(255) NULL AFTER `nazev`;
SQL
);

$result = $this->q(<<<SQL
SELECT `id_predmetu`, `nazev` FROM `shop_predmety`
SQL
);

$kodZNazvu = fn(string $nazev) => kodZNazvu($nazev);
$kody      = [];
foreach (mysqli_fetch_all($result, MYSQLI_ASSOC) as ['id_predmetu' => $idPredmetu, 'nazev' => $nazev]) {
    $this->q(<<<SQL
UPDATE `shop_predmety`
SET `kod_predmetu` = '{$kodZNazvu($nazev)}'
WHERE `id_predmetu` = $idPredmetu
SQL
    );
}

$this->q(<<<SQL
ALTER TABLE `shop_predmety`
    MODIFY COLUMN `kod_predmetu` VARCHAR(255) NOT NULL;
SQL
);

$this->q(<<<SQL
ALTER TABLE `shop_predmety`
    ADD UNIQUE INDEX `kod_predmetu_model_rok`(kod_predmetu, model_rok);
SQL
);
