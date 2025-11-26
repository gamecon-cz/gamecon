<?php
// takzvaný BFGR (Big f**king Gandalf report)

use Gamecon\Report\BfgrReport;
use Gamecon\BackgroundProcess\BackgroundProcessService;

require __DIR__ . '/sdilene-hlavicky.php';

global $systemoveNastaveni, $u;

// AJAX endpoint pro kontrolu stavu BFGR procesu
if (get('ajax') === 'checkBfgrStatus') {
    header('Content-Type: application/json');

    $backgroundProcessService = BackgroundProcessService::vytvorZGlobals();
    $commandName = BackgroundProcessService::COMMAND_BFGR_REPORT;

    $isRunning = $backgroundProcessService->isProcessRunning($commandName);

    echo json_encode([
        'running' => $isRunning,
    ], JSON_UNESCAPED_UNICODE);

    exit;
}

$idUzivatele = get('id');

// Příprava poznámky pro lokální prostředí (používá se ve všech HTML výstupech)
$localEmailNote = '';
if (defined('MAILY_DO_SOUBORU') && MAILY_DO_SOUBORU && !jsmeNaOstre()) {
    $specDirEscaped = htmlspecialchars(
        ltrim(str_replace(realpath(PROJECT_ROOT_DIR), '', realpath(SPEC)), '\\/',
    ));
    $localEmailNote = <<<HTML
        <div class="info" style="background-color: #fff3cd; border-left: 4px solid #ffc107;">
            <strong>ℹ️ Poznámka pro lokální prostředí:</strong><br>
            Email nebude odeslán, ale bude uložen do souboru v adresáři <code>$specDirEscaped</code>.
        </div>
HTML;
}

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
        idUzivatele: $idUzivatele,
    );

    return;
}

// Asynchronní zpracování - spustíme background proces
$backgroundProcessService = BackgroundProcessService::vytvorZGlobals();
$commandName = BackgroundProcessService::COMMAND_BFGR_REPORT;

// Zkontroluj, jestli už proces neběží
if ($backgroundProcessService->isProcessRunning($commandName)) {
    $processInfo = $backgroundProcessService->getRunningProcessInfo($commandName);
    $elapsedSeconds = $processInfo['elapsed_seconds'];
    $remainingSeconds = $processInfo['estimated_remaining_seconds'] ?? 'null';
    $elapsedFormatted = BackgroundProcessService::formatDuration($processInfo['elapsed_seconds']);
    $remainingFormatted = $processInfo['estimated_remaining_seconds'] !== null
        ? BackgroundProcessService::formatDuration($processInfo['estimated_remaining_seconds'])
        : 'neznámá';

    echo <<<HTML
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>BFGR Report</title>
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
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            transition: border-left-color 0.3s ease;
        }
        .message-box.running {
            border-left: 4px solid #FF9800;
        }
        .message-box.completed {
            border-left: 4px solid #4CAF50;
        }
        .running-content h1 {
            color: #FF9800;
            margin-top: 0;
        }
        .completed-content h1 {
            color: #4CAF50;
            margin-top: 0;
        }
        .info {
            margin: 15px 0;
            padding: 10px;
            border-radius: 4px;
        }
        .running-content .info {
            background-color: #f9f9f9;
        }
        .completed-content .info {
            background-color: #e8f5e9;
        }
        .completed-content a {
            color: #4CAF50;
            text-decoration: underline;
        }
        .hidden {
            display: none;
        }
        code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
    </style>
</head>
<body>
    <div class="message-box running" id="message-box">
        <!-- Running state -->
        <div class="running-content" id="running-content">
            <h1>⚠ Generování BFGR reportu již běží</h1>
            <p>V tuto chvíli probíhá generování BFGR reportu.</p>
            <div class="info">
                <strong>Běží:</strong> <span id="elapsed-time">{$elapsedFormatted}</span><br>
                <strong>Zbývá cca:</strong> <span id="remaining-time">{$remainingFormatted}</span>
            </div>
            {$localEmailNote}
            <p>Počkejte prosím na dokončení aktuálního procesu. O výsledku budete informováni emailem.</p>
            <p>Tuto stránku můžete bezpečně zavřít.</p>
        </div>

        <!-- Completed state -->
        <div class="completed-content hidden" id="completed-content">
            <h1>✓ BFGR report byl úspěšně vygenerován</h1>
            <p>Generování BFGR reportu bylo dokončeno.</p>
            <div class="info">
                <strong>Status:</strong> Dokončeno<br>
                <strong>Report:</strong> Odeslán na váš email
            </div>
            {$localEmailNote}
            <p>Zkontrolujte prosím svou emailovou schránku.</p>
            <p><a href="">Spustit nový BFGR report</a></p>
        </div>
    </div>

    <script>
    (function() {
        var pollInterval = 3000; // 3 sekundy
        var ajaxUrl = window.location.pathname;

        // Lokální čítače pro plynulý countdown
        var elapsedSeconds = {$elapsedSeconds};
        var remainingSeconds = {$remainingSeconds};

        // Formátování času
        function formatDuration(seconds) {
            if (seconds === null || seconds < 0) {
                return 'neznámá';
            }

            if (seconds < 60) {
                return seconds + ' s';
            }

            var minutes = Math.floor(seconds / 60);
            var remainingSec = seconds % 60;

            if (minutes < 60) {
                return remainingSec > 0
                    ? minutes + ' min ' + remainingSec + ' s'
                    : minutes + ' min';
            }

            var hours = Math.floor(minutes / 60);
            var remainingMin = minutes % 60;

            return hours + ' h ' + remainingMin + ' min';
        }

        // Aktualizuj displej každou sekundu
        function updateLocalCountdown() {
            elapsedSeconds++;

            if (remainingSeconds !== null) {
                remainingSeconds = Math.max(0, remainingSeconds - 1);
            }

            // Aktualizuj zobrazení
            document.getElementById('elapsed-time').textContent = formatDuration(elapsedSeconds);
            document.getElementById('remaining-time').textContent = formatDuration(remainingSeconds);
        }

        // Spusť lokální countdown každou sekundu
        setInterval(updateLocalCountdown, 1000);

        // AJAX polling pro kontrolu dokončení
        function checkProcessStatus() {
            fetch(ajaxUrl + '?ajax=checkBfgrStatus')
                .then(function(response) { return response.json(); })
                .then(function(data) {
                    if (!data.running) {
                        // Proces dokončen, zobraz úspěšnou zprávu
                        showSuccess();
                    } else {
                        // Proces stále běží, plánuj další check
                        setTimeout(checkProcessStatus, pollInterval);
                    }
                })
                .catch(function(error) {
                    console.error('Chyba při kontrole stavu:', error);
                    // Zkus to znovu
                    setTimeout(checkProcessStatus, pollInterval);
                });
        }

        // Zobraz úspěšnou zprávu
        function showSuccess() {
            var messageBox = document.getElementById('message-box');
            var runningContent = document.getElementById('running-content');
            var completedContent = document.getElementById('completed-content');

            // Přepni třídy a zobrazení
            messageBox.classList.remove('running');
            messageBox.classList.add('completed');
            runningContent.classList.add('hidden');
            completedContent.classList.remove('hidden');
        }

        // Spusť AJAX polling
        setTimeout(checkProcessStatus, pollInterval);
    })();
    </script>
</body>
</html>
HTML;
    exit;
}

$workerScript = __DIR__ . '/workers/_bfgr-report-worker.php';

$userEmail = $u->mail();
$userName = $u->jmenoNick();

$backgroundProcessService->startBackgroundProcess(
    $commandName,
    $workerScript,
    [
        'recipientEmail'   => $userEmail,
        'recipientName'    => $userName,
        'includeNonPayers' => '',
    ],
    ['started_by' => $u->id(), 'rocnik' => $systemoveNastaveni->rocnik()],
);

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
        code {
            background-color: #f4f4f4;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
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
        {$localEmailNote}
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
