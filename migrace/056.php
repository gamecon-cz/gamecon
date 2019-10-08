<?php
try {
  /** @var \Godric\DbMigrations\Migration $this */
  $this->q(<<<SQL
ALTER TABLE stranky
ADD COLUMN url_prefix CHAR(10) NOT NULL DEFAULT '' AFTER url_stranky,
ADD INDEX url_prefix(url_prefix);
SQL
  );
  $this->q(<<<SQL
CREATE TEMPORARY TABLE IF NOT EXISTS url_prefixes_temp (
    new_url_prefix CHAR(10) PRIMARY KEY
);

INSERT IGNORE INTO url_prefixes_temp(new_url_prefix) VALUES ('drd'), ('legendy'), ('rpg'), ('larpy');

UPDATE stranky
    JOIN url_prefixes_temp
SET stranky.url_prefix = new_url_prefix
WHERE stranky.url_stranky LIKE CONCAT(url_prefixes_temp.new_url_prefix, '%')
SQL
  );
} catch (\Exception $exception) {
  throw new RuntimeException(
    sprintf("Migration %s failed: '%s'. Check it: \n%s", basename(__FILE__, '.php'), $exception->getMessage(), $query),
    $exception->getCode(),
    $exception
  );
}
