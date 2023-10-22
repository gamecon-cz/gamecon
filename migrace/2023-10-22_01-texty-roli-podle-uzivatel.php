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
        {$sirienId},
        'Lopatička'
    ),
    (
        'DOBROVOLNIK_SENIOR',
        {$sirienId},
        'Krumpáček'
    ),
    (
        'HERMAN',
        {$sirienId},
        'Hraje a nezlobí'
    ),
    (
        'INFOPULT',
        {$sirienId},
        'Infík neni lumík!'
    ),
    (
        'NEDELNI_NOC_ZDARMA',
        {$sirienId},
        'Aby měl Flantovi kdo překážet'
    ),
    (
        'NEODHLASOVAT',
        {$sirienId},
        'STOP PANIC BUTTON'
    ),
    (
        'PARTNER',
        {$sirienId},
        'To sou ty který za účast na GC někdo ještě platí!'
    ),
    (
        'SOBOTNI_NOC_ZDARMA',
        {$sirienId},
        'Muzikanti'
    ),
    (
        'STREDECNI_NOC_ZDARMA',
        {$sirienId},
        'Schodoví pizzožrtouti'
    ),
    (
        'VYPRAVEC',
        {$sirienId},
        'Hraje a zlobí'
    ),
    (
        'CFO',
        {$sirienId},
        'Nešmatlej na to!'
    ),
    (
        'CESTNY_ORGANIZATOR',
        {$sirienId},
        'Zombieci, kostlivci, upíři a jiný revenanti'
    ),
    (
        'CLEN_RADY',
        {$sirienId},
        'Dříči a flákači'
    ),
    (
        'ORGANIZATOR_ZDARMA',
        {$sirienId},
        'Nažranej org'
    ),
    (
        'ADMIN',
        {$sirienId},
        'Vyhazovač'
    ),
    (
        'PUL_ORG_TRICKO',
        {$sirienId},
        'Homeless org'
    ),
    (
        'PUL_ORG_UBYTKO',
        {$sirienId},
        '(Polo)nahej org'
    ),
    (
        'SEF_INFOPULTU',
        {$sirienId},
        'GC Wikipedia'
    ),
    (
        'SEF_PROGRAMU',
        {$sirienId},
        'Prostě borec!'
    )
SQL,
);
