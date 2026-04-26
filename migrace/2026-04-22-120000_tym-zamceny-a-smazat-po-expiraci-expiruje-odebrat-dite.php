<?php

declare(strict_types=1);

$this->q(<<<SQL
    ALTER TABLE `akce_tym`
    ADD COLUMN `zamceny` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'zamčený tým nelze editovat',
    ADD COLUMN `expiruje` datetime DEFAULT NULL COMMENT 'čas expirace nezamčeného týmu; NULL = neexpiruje explicitně'
SQL,
);

$this->q(<<<SQL
    ALTER TABLE `akce_seznam`
    ADD COLUMN `tym_smazat_po_expiraci` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'po expiraci rozpracovaného týmu: 1 = smazat, 0 = zveřejnit',
    DROP COLUMN `dite`
SQL,
);

$this->q(<<<SQL
    UPDATE `akce_tym`
    SET `zamceny` = 1
    WHERE `zalozen` < '2026-01-01'
SQL,
);
