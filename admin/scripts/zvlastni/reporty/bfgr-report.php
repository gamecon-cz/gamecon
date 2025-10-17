<?php
// takzvaný BFGR (Big f**king Gandalf report)

use Gamecon\Report\BfgrReport;

require __DIR__ . '/sdilene-hlavicky.php';

global $systemoveNastaveni, $u;

$idUzivatele = get('id');

// Pokud je požadován konkrétní uživatel, použijeme synchronní zpracování
// Pro export všech uživatelů použijeme asynchronní zpracování s emailem
if ($idUzivatele) {
    // Synchronní zpracování pro jednotlivého uživatele
    ini_set('memory_limit', '512M');
    set_time_limit(300);

    $bfgrReport = new BfgrReport($systemoveNastaveni);
    $bfgrReport->exportuj(
        format: 'xlsx',
        vcetneStavuNeplatice: true,
        idUzivatele: $idUzivatele
    );
} else {
    // Asynchronní zpracování - spustíme background proces
    $workerScript = __DIR__ . '/workers/_bfgr-report-worker.php';

    $userEmail = $u->mail();
    $userName = $u->jmenoNick();

    // Sestavení příkazu pro background proces
    $command = sprintf(
        'php %s --userEmail=%s --userName=%s --includeNonPayers > /dev/null 2>&1 &',
        escapeshellarg($workerScript),
        escapeshellarg($userEmail),
        escapeshellarg($userName)
    );

    // Spuštění background procesu
    $result = exec($command);
    if ($result === false) {
        http_response_code(500);
        echo "Chyba při spuštění generování reportu na pozadí.";
        exit;
    }

    // Okamžitá odpověď uživateli
    echo <<<HTML
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>BFGR Report - Generování zahájeno</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .message-box {
            background-color: white;
            border-left: 4px solid #4CAF50;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #4CAF50;
            margin-top: 0;
        }
        .info {
            margin: 15px 0;
            padding: 10px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }
    </style>
</head>
<body>
    <div class="message-box">
        <h1>✓ Generování BFGR reportu bylo zahájeno</h1>
        <p>Report je příliš velký na okamžité zobrazení, proto bude vygenerován na pozadí.</p>
        <div class="info">
            <strong>Email pro odeslání:</strong> {$userEmail}<br>
            <strong>Ročník:</strong> {$systemoveNastaveni->rocnik()}
        </div>
        <p><strong>Co se bude dít dál?</strong></p>
        <ul>
            <li>Report se právě generuje na pozadí</li>
            <li>Po dokončení obdržíte email s reportem v příloze na adresu <strong>{$userEmail}</strong></li>
            <li>Generování může trvat několik minut</li>
        </ul>
        <p>Tuto stránku můžete bezpečně zavřít.</p>
    </div>
</body>
</html>
HTML;
    exit;
}
