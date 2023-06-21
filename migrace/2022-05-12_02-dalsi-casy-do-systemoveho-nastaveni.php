<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
INSERT IGNORE INTO systemove_nastaveni (klic, hodnota, aktivni, nazev, popis, datovy_typ, skupina, poradi)
    VALUES
        -- \Gamecon\Cas\DateTimeGamecon::registraceUcastnikuOd
        ('REG_GC_OD', '', 0 /* neaktivní, aby se vzala výchozí hodnota */, 'Začátek registrací účastníků', 'Od kdy se mohou začít účastníci registrovat na Gamecon', 'datetime', 'cas',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi)),
        -- \Gamecon\Cas\DateTimeGamecon::zacatekPrvniVlnyOd
        ('REG_AKTIVIT_OD', '', 0 /* neaktivní, aby se vzala výchozí hodnota */, 'Začátek první vlny aktivit', 'Od kdy se účastníci mohou začít přihlašovat na aktivity', 'datetime', 'cas',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi)),
        -- \Gamecon\Cas\DateTimeGamecon::prvniHromadneOdhlasovaniOd
        ('HROMADNE_ODHLASOVANI', '', 0 /* neaktivní, aby se vzala výchozí hodnota */, 'První hromadné odhlašování', 'Kdy budou poprvé hromadně odhlášeni přihlášení neplatiči', 'datetime', 'cas',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi)),
        -- \Gamecon\Cas\DateTimeGamecon::druheHromadneOdhlasovaniOd
        ('HROMADNE_ODHLASOVANI_2', '', 0 /* neaktivní, aby se vzala výchozí hodnota */, 'Druhé hromadné odhlašování', 'Kdy budou podruhé hromadně odhlášeni přihlášení neplatiči', 'datetime', 'cas',  (SELECT MAX(poradi) + 1 FROM systemove_nastaveni AS predchozi))
SQL
);
