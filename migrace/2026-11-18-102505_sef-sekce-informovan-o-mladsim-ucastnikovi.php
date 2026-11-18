<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Přihlášení mladšího účastníka (akce_prihlaseni_log), o kterém šéf sekce ví: e-mail mu opravdu odešel ('email'), nebo
// účastníka přihlásil někdo jiný, ten měl výjimku domluvenou ('prihlasil_jiny'). Podle toho se rozhoduje, jestli šéfovi
// sekce psát znovu (Aktivita::jeSefSekceInformovanOMladsimUcastnikovi()). Ze samotného logu přihlášení to nejde: nepozná,
// jestli e-mail odešel ani jestli byl účastník v době přihlášení mladší (štítek věku mohl přibýt až potom).
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS akce_mladsi_ucastnik_sef_informovan
(
    id_log BIGINT(20) UNSIGNED                NOT NULL,
    jak    ENUM ('email', 'prihlasil_jiny')   NOT NULL,
    kdy    DATETIME                           NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (id_log),
    FOREIGN KEY (id_log) REFERENCES akce_prihlaseni_log (id_log) ON DELETE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);
