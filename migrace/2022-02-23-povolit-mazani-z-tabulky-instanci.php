<?php
/** @var \Godric\DbMigrations\Migration $this */

$schema = DBM_NAME;
$mysqli = $this->q(<<<SQL
SELECT 1
FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
WHERE
  TABLE_NAME = 'akce_seznam'
  AND TABLE_SCHEMA = '$schema'
  AND CONSTRAINT_NAME = 'akce_seznam_ibfk_2';
SQL
);

$indexExists = mysqli_fetch_all($mysqli);
if ($indexExists) {
    $this->q(<<<SQL
ALTER TABLE `akce_seznam`
DROP FOREIGN KEY `akce_seznam_ibfk_2`;
SQL
    );
}

$this->q(<<<SQL
ALTER TABLE `akce_seznam`
    ADD CONSTRAINT `akce_seznam_ibfk_2` FOREIGN KEY (`patri_pod`) REFERENCES `akce_instance` (`id_instance`) ON UPDATE CASCADE ON DELETE SET NULL;
SQL
);
