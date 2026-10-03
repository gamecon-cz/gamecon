<?php

declare(strict_types=1);

/** @var Godric\DbMigrations\Migration $this */

// Quick reporty mají SQL uložené v databázi, takže je přepis čtení na pohled minul —
// ty, které sahají na `typ`, `podtyp`, `model_rok` nebo `je_letosni_hlavni`, spadnou
// na „Unknown column“, protože tyhle sloupce dopočítává až `shop_predmety_s_typem`.
//
// Přepisují se jen dotazy, které takový sloupec opravdu čtou: pohled má proti tabulce
// čtyři korelované poddotazy a počítá je na každý řádek, takže plošná náhrada by
// zdražila i dotazy, kterým tabulka stačí.
$sloupcePohledu = ['typ', 'podtyp', 'model_rok', 'je_letosni_hlavni'];

$reporty = $this->q(<<<SQL
SELECT id, dotaz
FROM reporty_quick
WHERE dotaz LIKE '%shop_predmety%'
SQL,
)->fetchAll(\PDO::FETCH_ASSOC);

foreach ($reporty as $report) {
    $dotaz = (string) $report['dotaz'];

    $ctePohled = false;
    foreach ($sloupcePohledu as $sloupec) {
        if (preg_match('/\b' . $sloupec . '\b/i', $dotaz) === 1) {
            $ctePohled = true;
            break;
        }
    }
    if (!$ctePohled) {
        continue;
    }

    // Záporný lookahead drží stranou `shop_predmety_s_typem`, aby opakované spuštění
    // nevyrobilo `shop_predmety_s_typem_s_typem`. Alias za jménem tabulky zůstává, jen
    // se posune — zbytek dotazu se na něj odkazuje.
    $prepsany = preg_replace('/\bshop_predmety\b(?!_s_typem)/i', 'shop_predmety_s_typem', $dotaz);
    if ($prepsany === null || $prepsany === $dotaz) {
        continue;
    }

    // `q()` neumí vázané parametry a uložené SQL obsahuje apostrofy, takže se hodnota
    // musí uvozovat přes PDO.
    $prepsanyProSql = $this->connection->quote($prepsany);
    $id             = (int) $report['id'];
    $this->q(<<<SQL
UPDATE reporty_quick SET dotaz = {$prepsanyProSql} WHERE id = {$id}
SQL,
    );
}
