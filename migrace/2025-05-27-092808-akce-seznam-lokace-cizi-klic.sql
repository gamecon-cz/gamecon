ALTER TABLE akce_seznam
    ADD FOREIGN KEY FK_akce_seznam_akce_lokace (lokace) REFERENCES akce_lokace (id_lokace)
        ON DELETE SET NULL
        ON UPDATE CASCADE;
