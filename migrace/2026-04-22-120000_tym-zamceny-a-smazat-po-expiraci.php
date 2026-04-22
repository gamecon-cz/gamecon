<?php

declare(strict_types=1);

$this->q(<<<SQL
    ALTER TABLE `akce_tym`
    ADD COLUMN `zamceny` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'zamčený tým nelze editovat'
SQL,
);

$this->q(<<<SQL
    ALTER TABLE `akce_seznam`
    ADD COLUMN `tym_smazat_po_expiraci` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'po expiraci rozpracovaného týmu: 1 = smazat, 0 = zveřejnit'
SQL,
);
