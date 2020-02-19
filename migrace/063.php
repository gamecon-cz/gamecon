<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE TABLE akce_instance(
    id INTEGER PRIMARY KEY AUTO_INCREMENT,
    id_hlavni_akce INTEGER NOT NULL,
    CONSTRAINT FOREIGN KEY FK_akce_instance_to_akce_seznam(id_hlavni_akce) REFERENCES akce_seznam(id_akce)
        ON UPDATE CASCADE ON DELETE CASCADE
);

INSERT INTO akce_instance(id, id_hlavni_akce)
SELECT patri_pod, MIN(id_akce)
FROM akce_seznam
WHERE patri_pod > 0
GROUP BY patri_pod;

ALTER TABLE akce_seznam
MODIFY COLUMN patri_pod INTEGER DEFAULT NULL;

UPDATE akce_seznam
SET patri_pod = NULL
WHERE patri_pod = 0;

ALTER TABLE akce_seznam
ADD CONSTRAINT FOREIGN KEY FK_akce_seznam_to_akce_instance(patri_pod) REFERENCES akce_instance(id)
        ON UPDATE CASCADE ON DELETE RESTRICT;
SQL
);
