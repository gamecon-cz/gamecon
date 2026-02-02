ALTER TABLE akce_lokace MODIFY id_akce_lokace BIGINT UNSIGNED NOT NULL;

DROP INDEX id_akce_lokace ON akce_lokace;
ALTER TABLE akce_lokace
    DROP id_akce_lokace,
    DROP je_hlavni;
ALTER TABLE akce_lokace RENAME INDEX fk_akce_lokace_lokace TO IDX_49CCDE68259B4755;
