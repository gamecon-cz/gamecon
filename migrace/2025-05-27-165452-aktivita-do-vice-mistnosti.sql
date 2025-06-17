RENAME TABLE akce_lokace TO lokace;

UPDATE _table_data_versions
SET table_name = 'lokace'
WHERE table_name = 'akce_lokace';

CREATE TABLE akce_lokace
(
    id_akce_lokace SERIAL,
    id_akce        INT                  NOT NULL,
    id_lokace      INT                  NOT NULL,
    je_hlavni      TINYINT(1) DEFAULT 0 NOT NULL,
    CONSTRAINT FK_akce_lokace_akce_seznam FOREIGN KEY (id_akce) REFERENCES akce_seznam (id_akce),
    CONSTRAINT FK_akce_lokace_lokace FOREIGN KEY (id_lokace) REFERENCES lokace (id_lokace),
    PRIMARY KEY (id_akce, id_lokace)
);

INSERT INTO akce_lokace (id_akce, id_lokace)
SELECT id_akce, lokace
FROM akce_seznam
WHERE lokace IS NOT NULL;

ALTER TABLE akce_seznam
    DROP FOREIGN KEY FK_akce_seznam_akce_lokace;
ALTER TABLE akce_seznam
    DROP COLUMN lokace;
