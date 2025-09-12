DELETE
FROM akce_seznam
WHERE zamcel IS NOT NULL
  AND NOT EXISTS(SELECT 1
                 FROM uzivatele_hodnoty
                 WHERE uzivatele_hodnoty.id_uzivatele = akce_seznam.zamcel);

ALTER TABLE akce_seznam
    ADD CONSTRAINT FK_akce_seznam_zamcel_to_uzivatele_hodnoty
        FOREIGN KEY (zamcel)
            REFERENCES uzivatele_hodnoty (id_uzivatele)
            ON DELETE RESTRICT
            ON UPDATE CASCADE;

ALTER TABLE platby
    DROP FOREIGN KEY FK_platby_to_uzivatele_hodnoty;

DELETE
FROM platby
WHERE provedl IS NOT NULL
  AND NOT EXISTS(SELECT 1
                 FROM uzivatele_hodnoty
                 WHERE uzivatele_hodnoty.id_uzivatele = platby.provedl);

ALTER TABLE platby
    ADD CONSTRAINT FK_platby_provedl_to_uzivatele_hodnoty
        FOREIGN KEY (provedl)
            REFERENCES uzivatele_hodnoty (id_uzivatele)
            ON DELETE RESTRICT
            ON UPDATE CASCADE;

DELETE
FROM role_texty_podle_uzivatele
WHERE NOT EXISTS(SELECT 1
                 FROM uzivatele_hodnoty
                 WHERE uzivatele_hodnoty.id_uzivatele = role_texty_podle_uzivatele.id_uzivatele);

ALTER TABLE role_texty_podle_uzivatele
    ADD CONSTRAINT FK_role_texty_podle_uzivatele_to_uzivatele_hodnoty
        FOREIGN KEY (id_uzivatele)
            REFERENCES uzivatele_hodnoty (id_uzivatele)
            ON DELETE CASCADE
            ON UPDATE CASCADE;

DELETE
FROM uzivatele_role_log
WHERE NOT EXISTS(SELECT 1
                 FROM uzivatele_hodnoty
                 WHERE uzivatele_hodnoty.id_uzivatele = uzivatele_role_log.id_uzivatele);

ALTER TABLE uzivatele_role_log
    ADD CONSTRAINT FK_uzivatele_role_log_to_uzivatele_hodnoty
        FOREIGN KEY (id_uzivatele)
            REFERENCES uzivatele_hodnoty (id_uzivatele)
            ON DELETE CASCADE
            ON UPDATE CASCADE;

DELETE
FROM uzivatele_role_podle_rocniku
WHERE NOT EXISTS(SELECT 1
                 FROM uzivatele_hodnoty
                 WHERE uzivatele_hodnoty.id_uzivatele = uzivatele_role_podle_rocniku.id_uzivatele);

ALTER TABLE uzivatele_role_podle_rocniku
    ADD CONSTRAINT FK_uzivatele_role_podle_rocniku_to_uzivatele_hodnoty
        FOREIGN KEY (id_uzivatele)
            REFERENCES uzivatele_hodnoty (id_uzivatele)
            ON DELETE CASCADE
            ON UPDATE CASCADE;
