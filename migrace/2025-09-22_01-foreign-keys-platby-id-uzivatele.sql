ALTER TABLE platby
    ADD FOREIGN KEY FK_platby_id_uzivatele_to_uzivatele_hodnoty (id_uzivatele) REFERENCES uzivatele_hodnoty (id_uzivatele) ON UPDATE CASCADE ON DELETE RESTRICT;
