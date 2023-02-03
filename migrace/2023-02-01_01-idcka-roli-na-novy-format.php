<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->dropForeignKeysIfExist([
    'r_prava_zidle_ibfk_3',
    'r_prava_zidle_ibfk_4',
    'r_prava_zidle_ibfk_5',
    'r_prava_zidle_ibfk_6',
], 'r_prava_zidle');

$staraNaNovaIdcka = [
    6  => ROK * -100000 - 6, // Vypravěč
    7  => ROK * -100000 - 7, // Zázemí
    8  => ROK * -100000 - 8, // Infopult
    13 => ROK * -100000 - 13, // Partner
    17 => ROK * -100000 - 17, // Dobrovolník senior
    18 => ROK * -100000 - 18, // Středeční noc zdarma
    19 => ROK * -100000 - 19, // Nedělní noc zdarma
    23 => ROK * -100000 - 23, // Neodhlašovat
    24 => ROK * -100000 - 24, // Herman
    25 => ROK * -100000 - 25, // Brigádník
];

foreach ($staraNaNovaIdcka as $stareId => $noveId) {
    $this->q(<<<SQL
UPDATE r_zidle_soupis
SET id_zidle = $noveId
WHERE id_zidle = $stareId
SQL
    );
}
