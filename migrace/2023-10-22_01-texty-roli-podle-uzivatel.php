<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE IF NOT EXISTS `role_texty_podle_uzivatele` (
  `vyznam_role` VARCHAR(48) NOT NULL,
  `id_uzivatele` INT NOT NULL,
  `popis_role` text COLLATE utf8_czech_ci NULL,
  PRIMARY KEY (`vyznam_role`, `id_uzivatele`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8 COLLATE=utf8_czech_ci
SQL,
);

$result = $this->q(<<<SQL
SELECT id_uzivatele FROM uzivatele_hodnoty WHERE jmeno_uzivatele = 'Petr' AND prijmeni_uzivatele = 'Mazák'
SQL);

$sirienId = mysqli_fetch_column($result);

if (!$sirienId) {
    return;
}

$result = $this->q(<<<SQL
INSERT INTO `role_texty_podle_uzivatele` (vyznam_role, id_uzivatele, popis_role)
VALUES (
        'BRIGADNIK',
        @idSiriena,
        'Lopatička'
    ),
    (
        'DOBROVOLNIK_SENIOR',
        @idSiriena,
        'Krumpáček'
    ),
    (
        'HERMAN',
        @idSiriena,
        'Hraje a nezlobí'
    ),
    (
        'INFOPULT',
        @idSiriena,
        'Infík neni lumík!'
    ),
    (
        'NEDELNI_NOC_ZDARMA',
        @idSiriena,
        'Aby měl Flantovi kdo překážet'
    ),
    (
        'NEODHLASOVAT',
        @idSiriena,
        'STOP PANIC BUTTON'
    ),
    (
        'PARTNER',
        @idSiriena,
        'To sou ty který za účast na GC někdo ještě platí!'
    ),
    (
        'SOBOTNI_NOC_ZDARMA',
        @idSiriena,
        'Muzikanti'
    ),
    (
        'STREDECNI_NOC_ZDARMA',
        @idSiriena,
        'Schodoví pizzožrtouti'
    ),
    (
        'VYPRAVEC',
        @idSiriena,
        'Hraje a zlobí'
    ),
    (
        'CFO',
        @idSiriena,
        'Nešmatlej na to!'
    ),
    (
        'CESTNY_ORGANIZATOR',
        @idSiriena,
        'Zombieci, kostlivci, upíři a jiný revenanti'
    ),
    (
        'CLEN_RADY',
        @idSiriena,
        'Dříči a flákači'
    ),
    (
        'ORGANIZATOR_ZDARMA',
        @idSiriena,
        'Nažranej org'
    ),
    (
        'ADMIN',
        @idSiriena,
        'Vyhazovač'
    ),
    (
        'PUL_ORG_TRICKO',
        @idSiriena,
        'Homeless org'
    ),
    (
        'PUL_ORG_UBYTKO',
        @idSiriena,
        '(Polo)nahej org'
    ),
    (
        'SEF_INFOPULTU',
        @idSiriena,
        'GC Wikipedia'
    ),
    (
        'SEF_PROGRAMU',
        @idSiriena,
        'Prostě borec!'
    )
SQL,
);
