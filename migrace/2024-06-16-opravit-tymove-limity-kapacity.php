<?php
/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
ALTER TABLE akce_seznam
    ADD COLUMN team_limit INT DEFAULT NULL NULL COMMENT 'uživatelem (vedoucím týmu) nastavený limit kapacity menší roven team_max, ale větší roven team_min. Prostřednictvím on update triggeru kontrolována tato vlastnost a je-li non-null, tak je tato kapacita nastavena do sloupce `kapacita`';
SQL
);

$this->q(<<<SQL
UPDATE akce_seznam
    SET team_limit = kapacita
    WHERE teamova = 1 AND kapacita <> team_max;
SQL
);

$this->q(<<<SQL
CREATE TRIGGER trigger_nastav_kapacitu_podle_team_limit
    BEFORE UPDATE
    ON akce_seznam
    FOR EACH ROW
BEGIN
    IF NEW.team_limit IS NOT NULL THEN -- vedoucí týmu nastavil (teď nmebo dříve) limit, aby se tam už nevešli nezvaní hosté
        IF NEW.team_limit < NEW.team_min OR NEW.team_limit > NEW.team_max THEN
            -- může se stát v případě, že z adminu někdo nastavil team_min nebo team_max mimo už nastavený team_limit
            SET NEW.team_limit = NULL;
        ELSE
            -- GC logika pro omezení týmové aktivity se řídí hodnotou z `kapacita`, proto nakonec použijeme limit týmu na kapacitu
            SET NEW.kapacita = NEW.team_limit;
        END IF;
    END IF;
END;
SQL
);
