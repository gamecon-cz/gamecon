<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Query cache nahradily statické JSON soubory programu, takže triggery, které
// na každé tabulce udržovaly verzi dat, už nemají co obsluhovat. Zdržují ale
// každý INSERT/UPDATE/DELETE o zápis do sdílené tabulky, a v testech byly
// prokázanou příčinou náhodných deadlocků (DDL při opakovaném spuštění endless
// migrace se pralo o zámky s běžícími testy).
//
// Triggery hledáme podle toho, co dělají (sahají na _table_data_versions),
// ne podle jména – tím se nemůže stát, že smažeme cizí trigger, který by měl
// shodou okolností podobné jméno. Podtržítka jsou v LIKE zástupné znaky pro
// jeden znak, takže je escapujeme, aby vzor odpovídal jménu tabulky doslova.
$triggerNames = $this->q(<<<SQL
SELECT trigger_name
FROM information_schema.triggers
WHERE trigger_schema = DATABASE()
  AND action_statement LIKE '%\\_table\\_data\\_versions%'
SQL,
)->fetch_all();

foreach ($triggerNames as [$triggerName]) {
    $triggerNameEscaped = $this->connection->real_escape_string($triggerName);
    $this->q(<<<SQL
DROP TRIGGER IF EXISTS `{$triggerNameEscaped}`
SQL,
    );
}

// Pořadí je dané cizími klíči: _tables_used_in_view_data_versions odkazuje
// na _table_data_versions, takže musí padnout první.
$this->q(<<<SQL
DROP TABLE IF EXISTS _tables_used_in_view_data_versions
SQL,
);
$this->q(<<<SQL
DROP TABLE IF EXISTS _table_data_versions
SQL,
);
