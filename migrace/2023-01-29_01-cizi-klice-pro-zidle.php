<?php
/** @var \Godric\DbMigrations\Migration $this */

// nevím proč, ale na betě ani na ostré cizí klíče nejsou - asi nějaký export import? protože staré SQL migrace je zakládají a žádná nemaže - musíme je obnovit
$this->dropForeignKeysIfExist(
    [
        'FK_r_uzivatele_zidle_uzivatele_hodnoty',
        'r_uzivatele_zidle_ibfk_5',
        'r_uzivatele_zidle_ibfk_6',
        'r_uzivatele_zidle_ibfk_7',
    ],
    'r_uzivatele_zidle'
);

$this->q(<<<SQL
ALTER TABLE r_uzivatele_zidle
ADD FOREIGN KEY FK_r_uzivatele_zidle_r_zidle_soupis(id_zidle) REFERENCES r_zidle_soupis(id_zidle)
ON DELETE CASCADE ON UPDATE CASCADE
SQL
);

$this->q(<<<SQL
ALTER TABLE r_uzivatele_zidle
ADD FOREIGN KEY FK_r_uzivatele_zidle_uzivatele_hodnoty(id_uzivatele) REFERENCES uzivatele_hodnoty(id_uzivatele)
ON DELETE CASCADE ON UPDATE CASCADE
SQL
);
