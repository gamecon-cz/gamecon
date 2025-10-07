ALTER TABLE `uzivatele_hodnoty`
    MODIFY COLUMN `pohlavi` CHAR(1) NOT NULL DEFAULT 'm';

ALTER TABLE `akce_prihlaseni_log`
    MODIFY COLUMN `typ` VARCHAR(127) NOT NULL;
