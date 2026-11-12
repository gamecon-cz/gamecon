DROP TABLE _vars;
DROP TABLE migrations;
DROP INDEX id_akce_lokace ON akce_lokace;
ALTER TABLE akce_lokace DROP id_akce_lokace;
ALTER TABLE medailonky CHANGE id_uzivatele id_uzivatele BIGINT UNSIGNED NOT NULL;
ALTER TABLE kostym_rezervace DROP FOREIGN KEY FK_kostym_rezervace_prihlaseni;
