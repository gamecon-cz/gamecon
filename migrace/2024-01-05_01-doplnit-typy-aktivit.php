<?php

/** @var \Godric\DbMigrations\Migration $this */
$this->q(<<<SQL
ALTER TABLE akce_typy
MODIFY COLUMN stranka_o INT DEFAULT NULL COMMENT 'id stranky "O rpg na GC" apod.'
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO akce_typy (id_typu, typ_1p, typ_1pmn, url_typu_mn, stranka_o, poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu)
VALUES (0, '(bez typu – organizační)', '(bez typu – organizační)', 'organizacni', null, -1, 0, '', 1, 0)
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO akce_typy (id_typu, typ_1p, typ_1pmn, url_typu_mn, stranka_o, poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu)
VALUES (1, 'turnaj v deskovkách', 'turnaje v deskovkách', 'turnaje', null, 2, 0, 'V oblíbených nebo nových deskovkách! Jako v každém správném turnaji, můžeš i tady vyhrát nějaké ceny! Třeba právě tu deskovku!', 1, 1)
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO akce_typy
    (id_typu, typ_1p, typ_1pmn, url_typu_mn, stranka_o, poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu)
VALUES (2, 'larp', 'larpy', 'larpy', null, 5, 1, 'Staň se postavou dramatického nebo komediálního příběhu a prožij naplno každý okamžik, jako by svět okolo neexistoval.', 1, 1)
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO akce_typy (id_typu, typ_1p, typ_1pmn, url_typu_mn, stranka_o, poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu)
VALUES (3, 'přednáška', 'přednášky', 'prednasky', null, 10, 0, 'Hraní je fajn, to jo, ale v poznání je moc! Přijď si poslechnout zajímavé speakery všeho druhu.', 1, 1)
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO akce_typy (id_typu, typ_1p, typ_1pmn, url_typu_mn, stranka_o, poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu)
VALUES (4, 'RPG', 'RPG', 'rpg', null, 6, 1, 'K tomu, aby ses přenesl/a do jiného světa a zažil/a napínavé dobrodružství ti budou stačit jen kostky a vlastní představivost.', 1, 1)
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO akce_typy (id_typu, typ_1p, typ_1pmn, url_typu_mn, stranka_o, poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu)
VALUES (5, 'workshop', 'workshopy', 'workshopy', null, -2, 0, '', 0, 0)
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO akce_typy (id_typu, typ_1p, typ_1pmn, url_typu_mn, stranka_o, poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu)
VALUES (6, 'wargaming', 'wargaming', 'wargaming', null, 4, 1, 'Armády figurek na bitevním poli! Přijď si zahrát pořádnou řežbu zasazenou do tvého oblíbeného světa!', 1, 1)
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO akce_typy (id_typu, typ_1p, typ_1pmn, url_typu_mn, stranka_o, poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu)
VALUES (7, 'bonus', 'akční a bonusové aktivity', 'bonusy', null, 9, 0, 'Nebaví tě pořád sedět u stolu? Tak pro tebe tu máme tyhle pohybovky a další zábavný program.', 1, 1)
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO akce_typy (id_typu, typ_1p, typ_1pmn, url_typu_mn, stranka_o, poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu)
VALUES (8, 'legendy klubu dobrodruhů', 'legendy klubu dobrodruhů', 'legendy', null, 8, 1, 'Pára, magie a víly. O tom je svět Příběhů Impéria. Poskládejte společně s dalšími družinami dohromady příběh, o kterém napíšou v The Times!', 1, 1)
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO akce_typy (id_typu, typ_1p, typ_1pmn, url_typu_mn, stranka_o, poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu)
VALUES (9, 'mistrovství v DrD', 'mistrovství v DrD', 'drd', null, 7, 0, 'Dračák už nejspíš znáš. Ale znáš taky Mistrovství v Dračáku? Dej dohromady družinu a vyhraj tenhle šampionát ve třech soutěžních kolech.', 1, 1)
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO akce_typy (id_typu, typ_1p, typ_1pmn, url_typu_mn, stranka_o, poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu)
VALUES (10, 'technická', 'organizační výpomoc', 'organizacni-vypomoc', null, -1, 0, '', 1, 0)
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO akce_typy (id_typu, typ_1p, typ_1pmn, url_typu_mn, stranka_o, poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu)
VALUES (11, 'epic', 'epické deskovky', 'epic', null, 3, 1, 'Chceš si zahrát nějakou velkou strategickou nebo atmosférickou hru? Tady si určitě vybereš!', 1, 1)
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO akce_typy (id_typu, typ_1p, typ_1pmn, url_typu_mn, stranka_o, poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu)
VALUES (12, 'doprovodný program', 'doprovodný program', 'doprovodny-program', null, 11, 0, 'Přijde ti to všechno málo? Nevadí, kromě všeho ostatního tu máme i koncert a nějaké ty večírky. ', 1, 1)
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO akce_typy (id_typu, typ_1p, typ_1pmn, url_typu_mn, stranka_o, poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu)
VALUES (13, 'deskoherna', 'deskoherna', 'deskoherna', null, 1, 0, 'Deskoherna je zcela zdarma a otevřená téměř celý den. Navíc tu najdeš organizátory a vydavatele, kteří ti hry vysvětlí.', 1, 1)
SQL,
);

$this->q(<<<SQL
INSERT IGNORE INTO akce_typy (id_typu, typ_1p, typ_1pmn, url_typu_mn, stranka_o, poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu)
VALUES (102, 'brigádnická', 'brigádnické', 'brigadnicke', null, -3, 0, 'Placená výpomoc Gameconu', 1, 0)
SQL,
);
