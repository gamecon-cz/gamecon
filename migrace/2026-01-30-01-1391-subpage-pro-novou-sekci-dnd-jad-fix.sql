START TRANSACTION;

-- pojistka: cílové ID nesmí existovat
-- (když existuje, INSERT níže selže a transakce se rollbackne)
INSERT INTO akce_typy (
  id_typu, typ_1p, typ_1pmn, url_typu_mn, stranka_o,
  poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu, kod_typu
)
SELECT
  14, typ_1p, typ_1pmn, url_typu_mn, stranka_o,
  poradi, mail_neucast, popis_kratky, aktivni, zobrazit_v_menu, kod_typu
FROM akce_typy
WHERE id_typu = 104;

-- automaticky přepiš všechny FK reference (kde je akce_typy.id_typu rodič)
SELECT GROUP_CONCAT(
  CONCAT('UPDATE `', TABLE_NAME, '` SET `', COLUMN_NAME, '` = 14 WHERE `', COLUMN_NAME, '` = 104')
  SEPARATOR '; '
) INTO @sql
FROM information_schema.KEY_COLUMN_USAGE
WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
  AND REFERENCED_TABLE_NAME = 'akce_typy'
  AND REFERENCED_COLUMN_NAME = 'id_typu';

-- pokud nic nenajde, @sql bude NULL -> uděláme z toho prázdný string
SET @sql = COALESCE(@sql, '');

-- vykonej vygenerované UPDATEy (pokud jsou)
SET @sql = IF(@sql = '', 'SELECT 1', CONCAT(@sql, ';'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- smaž původní řádek
DELETE FROM akce_typy WHERE id_typu = 104;

COMMIT;
