<?php

/** @var \Godric\DbMigrations\Migration $this */

$this->q(<<<SQL
CREATE FUNCTION `konec_gc`(datum DATE) RETURNS DATE DETERMINISTIC
BEGIN
-- První den v červenci
    SET @first_day_of_july = DATE(CONCAT(YEAR(datum), '-07-01'));

-- První pondělí v červenci
SET @first_monday_of_july = DATE_ADD(
        @first_day_of_july,
        INTERVAL (IF(
                DAYOFWEEK(@first_day_of_july) <= 2, -- neděle nebo pondělí
                2 - DAYOFWEEK(@first_day_of_july), -- následující pondělí v tomto týdnu
                9 - DAYOFWEEK(@first_day_of_july)) -- pondělí v příštím týdnu
            ) DAY
                            );

-- Začátek třetího celého týdne v červenci
SET @third_week_monday = DATE_ADD(@first_monday_of_july, INTERVAL 14 DAY);

-- Neděle ve třetím celém týdnu = konec GC
RETURN DATE_ADD(@third_week_monday, INTERVAL 6 DAY);
END;
SQL
);
