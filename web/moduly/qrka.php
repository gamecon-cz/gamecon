<?php

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Logo\Logo;
use Symfony\Component\Filesystem\Filesystem;

/** @var Uzivatel|null $u */

// Generátor QR kódů je nástroj pro organizátory.
if (!$u || !$u->jeOrganizator()) {
    throw new \Neprihlasen();
}

$qrkaBasePath = realpath(__DIR__ . '/../soubory/blackarrow/qrka');
$qrkaGenDir   = $qrkaBasePath . DIRECTORY_SEPARATOR . 'generovane';
(new Filesystem())->mkdir($qrkaGenDir, 0775);

/**
 * Vygeneruje PNG QR kódu a uloží ho na disk.
 *
 * @param string      $obsah       kódovaný text / URL
 * @param string|null $logoCesta   absolutní cesta k logu doprostřed (NULL = bez loga)
 * @param string      $cilovaCesta absolutní cesta, kam PNG uložit
 */
function qrkaVygenerujSoubor(string $obsah, ?string $logoCesta, string $cilovaCesta): void
{
    $qrCode = new QrCode($obsah);
    $qrCode->setSize(300);
    $qrCode->setEncoding(new Encoding('UTF-8'));
    $qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevelHigh());
    $qrCode->setRoundBlockSizeMode(new RoundBlockSizeModeMargin());

    $logo = null;
    if ($logoCesta && is_file($logoCesta)) {
        [$puvodniSirka, $puvodniVyska] = getimagesize($logoCesta);

        // Logo se vejde do čtverce 100×100 se zachováním poměru stran.
        $novaSirka = 100;
        $novaVyska = 100;
        $pomer     = $puvodniSirka / $puvodniVyska;
        if ($novaSirka / $novaVyska > $pomer) {
            $novaSirka = $novaVyska * $pomer;
        } else {
            $novaVyska = $novaSirka / $pomer;
        }

        $logo = new Logo($logoCesta, (int)$novaSirka, (int)$novaVyska, punchoutBackground: false);
    }

    $vysledek = (new PngWriter())->write($qrCode, $logo);
    $vysledek->saveToFile($cilovaCesta);
}

// --- Zpracování POST akcí (přidání / smazání), pak PRG redirect ---
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $akce = $_POST['akce'] ?? '';

    if ($akce === 'smazat') {
        $id    = (int)($_POST['id'] ?? 0);
        $radek = dbOneLine('SELECT * FROM qr_kody WHERE id = $1', [$id]);
        if ($radek) {
            if (!empty($radek['soubor'])) {
                (new Filesystem())->remove($qrkaBasePath . DIRECTORY_SEPARATOR . $radek['soubor']);
            }
            dbDelete('qr_kody', ['id' => $id]);
        }
    } elseif ($akce === 'pridat') {
        $nazev  = trim((string)($_POST['nazev'] ?? ''));
        $obsah  = trim((string)($_POST['obsah'] ?? ''));
        $sLogem = !empty($_POST['s_logem']);

        if ($obsah !== '') {
            dbInsert('qr_kody', [
                'nazev'                 => $nazev !== '' ? $nazev : $obsah,
                'obsah'                 => $obsah,
                'logo_soubor'           => $sLogem ? 'logo.png' : null,
                'vytvoril_id_uzivatele' => $u->id(),
            ]);
            $id           = dbInsertId();
            $relativni    = 'generovane' . DIRECTORY_SEPARATOR . $id . '.png';
            $logoCesta    = $sLogem ? $qrkaBasePath . DIRECTORY_SEPARATOR . 'logo.png' : null;
            qrkaVygenerujSoubor($obsah, $logoCesta, $qrkaBasePath . DIRECTORY_SEPARATOR . $relativni);
            dbQuery('UPDATE qr_kody SET soubor = $1 WHERE id = $2', [$relativni, $id]);
        }
    }

    header('Location: ' . URL_WEBU . '/qrka');
    exit;
}

// --- Načtení seznamu + dogenerování chybějících PNG (např. naseedovaných) ---
$qrKody = dbFetchAll('SELECT * FROM qr_kody ORDER BY id');
foreach ($qrKody as &$qr) {
    $relativni    = $qr['soubor'] ?: ('generovane' . DIRECTORY_SEPARATOR . $qr['id'] . '.png');
    $absolutni    = $qrkaBasePath . DIRECTORY_SEPARATOR . $relativni;
    if (empty($qr['soubor']) || !is_file($absolutni)) {
        $logoCesta = $qr['logo_soubor']
            ? $qrkaBasePath . DIRECTORY_SEPARATOR . $qr['logo_soubor']
            : null;
        qrkaVygenerujSoubor($qr['obsah'], $logoCesta, $absolutni);
        dbQuery('UPDATE qr_kody SET soubor = $1 WHERE id = $2', [$relativni, $qr['id']]);
        $qr['soubor'] = $relativni;
    }
}
unset($qr);

// --- Výstup ---
?>
<div class="stranka">
    <h1>Generátor QR kódů</h1>

    <form method="post" class="qrka-form">
        <input type="hidden" name="akce" value="pridat">
        <p>
            <label>Název (nepovinný):<br>
                <input type="text" name="nazev" placeholder="např. web-registrace">
            </label>
        </p>
        <p>
            <label>Odkaz nebo text:<br>
                <input type="text" name="obsah" required placeholder="https://gamecon.cz/..." size="50">
            </label>
        </p>
        <p>
            <label>
                <input type="checkbox" name="s_logem" value="1" checked> s logem GameConu
            </label>
        </p>
        <p><button type="submit">Vygenerovat a uložit</button></p>
    </form>

    <hr>

    <div class="qrka-seznam">
        <?php foreach ($qrKody as $qr): ?>
            <?php
            $id         = (int)$qr['id'];
            $nazevText  = (string)$qr['nazev'];
            $nazev      = htmlspecialchars($nazevText, ENT_QUOTES);
            $cesta      = 'soubory/blackarrow/qrka/' . str_replace(DIRECTORY_SEPARATOR, '/', $qr['soubor']);
            $verze      = @filemtime($qrkaBasePath . DIRECTORY_SEPARATOR . $qr['soubor']) ?: 0;
            $imgSrc     = htmlspecialchars($cesta . '?v=' . $verze, ENT_QUOTES);
            $stahnout   = htmlspecialchars($nazevText !== '' ? $nazevText : ('qr-' . $id), ENT_QUOTES);
            ?>
            <div class="qrka-polozka" style="display:inline-block;vertical-align:top;text-align:center;margin:1em;">
                <div><strong><?= $nazev ?></strong></div>
                <div><img id="qrCode-<?= $id ?>" src="<?= $imgSrc ?>" alt="QR kód <?= $nazev ?>" style="max-width:300px;"></div>
                <div>
                    <button type="button" onclick="copyQrCode('qrCode-<?= $id ?>')">Zkopírovat</button>
                    <a href="<?= $imgSrc ?>" download="<?= $stahnout ?>.png"><button type="button">Stáhnout</button></a>
                    <form method="post" style="display:inline" onsubmit="return confirm('Opravdu smazat tento QR kód?');">
                        <input type="hidden" name="akce" value="smazat">
                        <input type="hidden" name="id" value="<?= $id ?>">
                        <button type="submit">Smazat</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script src="soubory/blackarrow/qrka/script.js"></script>
