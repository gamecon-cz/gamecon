<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni(klic, hodnota, vlastni, datovy_typ, nazev, popis, zmena_kdy, skupina, poradi, pouze_pro_cteni)
    VALUES
        ('HROMADNE_ODHLASOVANI_1', '', 0 /* neaktivní, aby se vzala výchozí hodnota */, 'datetime', 'První hromadné odhlašování', 'Kdy budou poprvé hromadně odhlášeni neplatiči', NOW(), 'Časy',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi WHERE skupina = 'Časy'), 0),
        ('HROMADNE_ODHLASOVANI_2', '', 0 /* neaktivní, aby se vzala výchozí hodnota */, 'datetime', 'Druhé hromadné odhlašování', 'Kdy budou podruhé hromadně odhlášeni neplatiči', NOW(), 'Časy',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi WHERE skupina = 'Časy'), 0),
        ('HROMADNE_ODHLASOVANI_3', '', 0 /* neaktivní, aby se vzala výchozí hodnota */, 'datetime', 'Třetí hromadné odhlašování', 'Kdy budou potřetí hromadně odhlášeni neplatiči', NOW(), 'Časy',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi WHERE skupina = 'Časy'), 0)
SQL,
);
