<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, nazev, popis, datovy_typ, skupina)
    VALUES
        ('NEPLATIC_CASTKA_VELKY_DLUH', 200, 'Ještě příliš velký dluh neplatiče', 'Kolik kč je pro nás stále tak velký dluh, že mu hrozí odhlášení jako neplatiči', 'number','Neplatič'),
        ('NEPLATIC_CASTKA_POSLAL_DOST', 1000, 'Už dost velká částka proti odhlášení', 'Kolik kč musí letos účastník poslat, abychom ho nezařadili do neplatičů', 'number','Neplatič'),
        ('NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN', 7, 'Počet dní od registrace před vlnou kdy je chráněn', 'Kolik nejvýše dní od registrace do odhlašovací vlny neplatičů je nový účastník ještě chráněn, aby nebyl brán jako neplatič', 'integer','Neplatič')
SQL
);
