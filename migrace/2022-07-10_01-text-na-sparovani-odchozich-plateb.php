<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, aktivni, nazev, popis, datovy_typ, skupina, poradi)
    VALUES
        ('TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY', 'Vrácení zůstatku účastníka ID', 1, 'Text pro rozpoznání odchozí GC platby', 'Přesné znění "Zpráva pro příjemce", za kterém následuje ID účastníka GC, kterému odesíláme z banky peníze, abychom podle něj spárovali odchozí platbu (stačí nalými písmeny a bez diakritiky)', 'text', 'Finance',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi))
SQL
);
