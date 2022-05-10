<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, nazev, popis, datovy_typ, skupina, poradi)
    VALUES
        ('BONUS_ZA_1H_AKTIVITU', 70, 'Bonus za vedení 1h aktivity', 'Kolik dostane vypravěč aktivity, která trvala hodinu', 'number', 'finance',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi)),
        ('BONUS_ZA_2H_AKTIVITU', 140, 'Bonus za vedení 2h aktivity', 'Kolik dostane vypravěč aktivity, která trvala dvě hodiny', 'number', 'finance',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi)),
        ('BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU', 280, 'Bonus za vedení 3-5h aktivity', 'Kolik dostane vypravěč standardní aktivity, která trvala tři až pět hodin', 'number', 'finance',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi)),
        ('BONUS_ZA_6H_AZ_7H_AKTIVITU', 420, 'Bonus za vedení 6-7h aktivity', 'Kolik dostane vypravěč aktivity, která trvala šest až sedm hodin', 'number', 'finance',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi)),
        ('BONUS_ZA_8H_AZ_9H_AKTIVITU', 560, 'Bonus za vedení 8-9h aktivity', 'Kolik dostane vypravěč aktivity, která trvala osm až devět hodin', 'number', 'finance',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi)),
        ('BONUS_ZA_10H_AZ_11H_AKTIVITU', 700, 'Bonus za vedení 10-11h aktivity', 'Kolik dostane vypravěč aktivity, která trvala deset až jedenáct hodin', 'number', 'finance',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi)),
        ('BONUS_ZA_12H_AZ_13H_AKTIVITU', 840, 'Bonus za vedení 12-13h aktivity', 'Kolik dostane vypravěč aktivity, která trvala dvanáct až třináct hodin', 'number', 'finance',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi))
SQL
);
