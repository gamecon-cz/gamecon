<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Aktivity karet programu, o kterých už job po GC rozhodl (Gamecon\ArchivPrednasek\DoplneniZProgramu): kartu založil
// v archivu, nebo přeskočil, protože v něm už byla. Bez záznamu by zrušené spárování v adminu vedlo k nové přednášce
// pro tutéž kartu při dalším běhu. Zapisují se všechny aktivity karty, aby zůstala zpracovaná, i když se smaže její
// hlavní instance. Karta, která selhala, má chybu a job ji zkusí znovu za den (zpracovano je čas pokusu).
$this->q(<<<'SQL'
CREATE TABLE IF NOT EXISTS archiv_prednasek_zpracovane_aktivity
(
    id_akce    BIGINT(20) UNSIGNED NOT NULL,
    zpracovano DATETIME            NOT NULL,
    chyba      VARCHAR(255)                 DEFAULT NULL,
    PRIMARY KEY (id_akce),
    FOREIGN KEY (id_akce) REFERENCES akce_seznam (id_akce) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE = InnoDB
  DEFAULT CHARSET = utf8mb4
  COLLATE = utf8mb4_czech_ci
SQL,
);
