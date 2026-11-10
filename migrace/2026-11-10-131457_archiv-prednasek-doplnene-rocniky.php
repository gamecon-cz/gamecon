<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Ročníky, jejichž proběhlé přednášky už job po GC založil v archivu (Gamecon\ArchivPrednasek\DoplneniZProgramu).
// Každý ročník se doplní jen jednou: kdyby admin spárování zrušil, další běh by pro stejnou aktivitu založil
// přednášku znovu.
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS archiv_prednasek_doplnene_rocniky
(
    rok      INT(11)   NOT NULL,
    doplneno TIMESTAMP NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (rok)
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);
