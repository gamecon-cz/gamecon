<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
UPDATE systemove_nastaveni
SET poradi = poradi + 1
WHERE poradi > (SELECT poradi FROM systemove_nastaveni WHERE klic = 'HROMADNE_ODHLASOVANI_2')
SQL
);

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, aktivni, nazev, popis, datovy_typ, skupina, poradi)
    VALUES
        ('HROMADNE_ODHLASOVANI_3', '', 0 /* neaktivní, aby se vzala výchozí hodnota */, 'Třetí hromadné odhlašování', 'Kdy budou potřetí hromadně odhlášeni neplatiči', 'datetime', 'Časy',  (SELECT poradi + 1 FROM systemove_nastaveni AS predchozi WHERE klic = 'HROMADNE_ODHLASOVANI_2'))
SQL
);

$this->q(<<<SQL
UPDATE systemove_nastaveni
SET popis = 'Kdy budou podruhé hromadně odhlášeni neplatiči'
WHERE klic = 'HROMADNE_ODHLASOVANI_2'
SQL
);

$this->q(<<<SQL
UPDATE systemove_nastaveni
SET popis = 'Kdy budou poprvé hromadně odhlášeni neplatiči'
WHERE klic = 'HROMADNE_ODHLASOVANI_1'
SQL
);
