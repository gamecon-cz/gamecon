<?php
/** @var \Godric\DbMigrations\Migration $this */

use Granam\RemoveDiacritics\RemoveDiacritics;
use Gamecon\Role\Role;

$this->q(<<<SQL
ALTER TABLE r_zidle_soupis
ADD COLUMN kod_zidle VARCHAR(36) NULL UNIQUE AFTER id_zidle
SQL,
);

$result = $this->q(<<<SQL
SELECT id_zidle, jmeno_zidle
FROM r_zidle_soupis
SQL,
);

$letosniPrefix = Role::prefixRocniku(ROCNIK);
foreach ($result->fetch_all(MYSQLI_ASSOC) as ['id_zidle' => $idZidle, 'jmeno_zidle' => $jmenoZidle]) {
    $nazevZidleSPrefixem = $idZidle > 0
        ? $letosniPrefix . ' ' . $jmenoZidle // Infopult = GC2023 Infopult
        : $jmenoZidle; // GC2021 přihlášen
    // 'GC2023 Herman' = GC2023_HERMAN
    $kodZidle = RemoveDiacritics::toConstantLikeName($nazevZidleSPrefixem);
    if ($kodZidle === '' || $kodZidle === '_') {
        // stane se když je chybné NAMES pro SQL spojení a diakritika se načte jako nesmyslný znak
        throw new \RuntimeException("Nelze sestavit kód židle. Název: '$nazevZidleSPrefixem', výsledný kód: '$kodZidle'");
    }
    $this->q(<<<SQL
UPDATE r_zidle_soupis
SET kod_zidle = '$kodZidle'
WHERE id_zidle = $idZidle
SQL,
    );
}

$this->q(<<<SQL
ALTER TABLE r_zidle_soupis
MODIFY COLUMN kod_zidle VARCHAR(36) NOT NULL
SQL,
);
