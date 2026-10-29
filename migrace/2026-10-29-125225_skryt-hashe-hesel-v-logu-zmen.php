<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Log změn osobních údajů ukládal u změny hesla starý i nový hash hesla. Nově
// zaznamená jen to, že se heslo změnilo (Uzivatel::SKRYTE_HODNOTY_V_LOGU), a tady se
// stejně přepíšou hashe, které už v logu jsou. Prázdná hodnota zůstane prázdná:
// ukazuje, že uživatel předtím heslo neměl.
$this->q(<<<'SQL'
UPDATE uzivatele_hodnoty_log
SET stara_hodnota = IF(stara_hodnota IS NULL OR stara_hodnota = '', stara_hodnota, '***'),
    nova_hodnota = IF(nova_hodnota IS NULL OR nova_hodnota = '', nova_hodnota, '***')
WHERE sloupec = 'heslo_md5'
SQL,
);
