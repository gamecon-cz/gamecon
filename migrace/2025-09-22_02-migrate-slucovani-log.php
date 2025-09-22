<?php
/** @var \Godric\DbMigrations\Migration $this */

// Parse existing main slucovani.log file and insert into the new SQL table
$logFile = LOGY . '/slucovani.log';

if (!file_exists($logFile)) {
    return;
}

$content = file_get_contents($logFile);
if (empty($content)) {
    return;
}

$lines           = explode("\n", $content);
$currentEntry    = [];
$entryInProgress = false;

$saveRecord = function (
    array $currentEntry,
) {
    // Insert the completed entry
    $idSmazaneho            = (int)$currentEntry['id_smazaneho'];
    $idNoveho               = (int)$currentEntry['id_noveho'];
    $zustatekSmazaneho      = (int)$currentEntry['zustatek_smazaneho'];
    $zustatekNovehoPuvodne  = (int)$currentEntry['zustatek_noveho_puvodne'];
    $emailSmazaneho         = mysqli_real_escape_string($this->connection, $currentEntry['email_smazaneho']);
    $emailNovehoPuvodne     = mysqli_real_escape_string($this->connection, $currentEntry['email_noveho_puvodne']);
    $zustatekNovehoAktualne = (int)$currentEntry['zustatek_noveho_aktualne'];
    $emailNovehoAktualne    = mysqli_real_escape_string($this->connection, $currentEntry['email_noveho_aktualne']);
    $timestamp              = mysqli_real_escape_string($this->connection, $currentEntry['timestamp']);

    $this->q("INSERT INTO uzivatele_slucovani_log (
                id_smazaneho_uzivatele,
                id_noveho_uzivatele,
                zustatek_smazaneho_puvodne,
                zustatek_noveho_puvodne,
                email_smazaneho,
                email_noveho_puvodne,
                zustatek_noveho_aktualne,
                email_noveho_aktualne,
                kdy
            ) VALUES (
                $idSmazaneho,
                $idNoveho,
                $zustatekSmazaneho,
                $zustatekNovehoPuvodne,
                '$emailSmazaneho',
                '$emailNovehoPuvodne',
                $zustatekNovehoAktualne,
                '$emailNovehoAktualne',
                '$timestamp'
            )");
};

foreach ($lines as $line) {
    $line = trim($line);
    if (empty($line)) {
        // Empty line signals end of entry
        if ($entryInProgress && count($currentEntry) >= 7) {
            $saveRecord($currentEntry);
        }
        $currentEntry    = [];
        $entryInProgress = false;
        continue;
    }

    // Parse main merge line: "2021-07-07 18:16:36 do id 326 sloučeno a smazáno id 4406"
    if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}) do [Ii][Dd] (\d+) sloučeno a smazáno [Ii][Dd] (\d+)$/', $line, $matches)) {
        $currentEntry    = [
            'timestamp'    => $matches[1],
            'id_noveho'    => (int)$matches[2],
            'id_smazaneho' => (int)$matches[3],
        ];
        $entryInProgress = true;
        continue;
    }

    if (!$entryInProgress) {
        continue;
    }

    // Parse detail lines
    if (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\s+původní zůstatek smazaného účtu:\s*(-?\d+)/', $line, $matches) ||
        preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\s+zůstatek z předchozích ročníků smazaného účtu:\s*(-?\d+)/', $line, $matches)) {
        $currentEntry['zustatek_smazaneho'] = (int)$matches[1];
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\s+původní zůstatek nového účtu:\s*(-?\d+)/', $line, $matches) ||
              preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\s+zůstatek z předchozích ročníků nového účtu:\s*(-?\d+)/', $line, $matches)) {
        $currentEntry['zustatek_noveho_puvodne'] = (int)$matches[1];
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\s+email smazaného účtu:\s*(.+)$/', $line, $matches)) {
        $currentEntry['email_smazaneho'] = trim($matches[1]);
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\s+email nového účtu:\s*(.+)$/', $line, $matches)) {
        $currentEntry['email_noveho_puvodne'] = trim($matches[1]);
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\s+aktuální nový zůstatek.*:\s*(-?\d+)/', $line, $matches)) {
        $currentEntry['zustatek_noveho_aktualne'] = (int)$matches[1];
    } elseif (preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\s+aktuální nový email:\s*(.+)$/', $line, $matches)) {
        $currentEntry['email_noveho_aktualne'] = trim($matches[1]);
    }
}

// Handle the last entry if file doesn't end with empty line
if ($entryInProgress && count($currentEntry) >= 7) {
    $saveRecord($currentEntry);
}
