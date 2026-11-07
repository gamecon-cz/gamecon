<?php

use Gamecon\Web\ZpracovaneObrazky;
use Gamecon\Web\LogoUpload;
use Gamecon\Web\NahravaniObrazku;
use Gamecon\Web\NahravaniObrazkuException;
use Gamecon\XTemplate\XTemplate;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Správa log sponzorů a partnerů
 *
 * nazev: Loga
 * ikona: palette
 * pravo: 112
 */

$adresarePodleTypu = [
    'sponzor_titulka' => ZpracovaneObrazky::adresarSponzoruTitulka(),
    'partner_titulka' => ZpracovaneObrazky::adresarPartneruTitulka(),
    'sponzor_prehled' => ZpracovaneObrazky::adresarSponzoruPrehled(),
    'partner_prehled' => ZpracovaneObrazky::adresarPartneruPrehled(),
];
$blokyPodleTypu = [
    'sponzor_titulka' => 'sponzoriTitulka',
    'partner_titulka' => 'partneriTitulka',
    'sponzor_prehled' => 'sponzoriPrehled',
    'partner_prehled' => 'partneriPrehled',
];
$adresarProAkci = static function (string $akce) use ($adresarePodleTypu): string {
    preg_match('~^(?:remove|upload)_(.+)$~', $akce, $shoda);

    return $adresarePodleTypu[$shoda[1] ?? ''] ?? throw new \RuntimeException(sprintf('Neznámá akce: %s', $akce));
};

NahravaniObrazku::hlidejPrilisVelkyPozadavek();

if ($_POST) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_razeni') {
        $typ     = $_POST['typ'] ?? null;
        $adresar = is_string($typ)
            ? ($adresarePodleTypu[$typ] ?? null)
            : null;
        $odpoved = ['ok' => true];
        try {
            if ($adresar === null) {
                throw new \InvalidArgumentException('Neznámá skupina log.');
            }
            (new ZpracovaneObrazky($adresar))->ulozPoradi(
                array_values(array_filter((array)($_POST['poradi'] ?? []), 'is_string')),
            );
        } catch (\InvalidArgumentException $invalidArgumentException) {
            http_response_code(400);
            $odpoved = ['ok' => false, 'error' => $invalidArgumentException->getMessage()];
        } catch (\RuntimeException) {
            http_response_code(500);
            $odpoved = ['ok' => false, 'error' => 'Pořadí se nepodařilo uložit.'];
        }
        header('Content-Type: application/json');
        echo json_encode($odpoved, JSON_UNESCAPED_UNICODE);
        exit;
    }

    // Handle remove actions
    if (str_starts_with($action, 'remove_')) {
        $filename = $_POST['filename'] ?? '';
        if ($filename) {
            $targetDir = $adresarProAkci($action);

            try {
                // jen soubory přímo v adresáři log, bez `..` a skrytých souborů
                $filePath = (new NahravaniObrazku($targetDir))->cestaKSouboru(basename($filename));
            } catch (NahravaniObrazkuException) {
                $filePath = null;
            }
            if ($filePath !== null && is_file($filePath)) {
                if (unlink($filePath)) {
                    oznameni("Logo bylo úspěšně smazáno.");
                } else {
                    chyba("Nepodařilo se smazat logo.");
                }
            } else {
                chyba("Soubor neexistuje.");
            }
        }
        return;
    }

    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $url = trim($_POST['url'] ?? '');

        if ($url) {
            $uploadedFile = $_FILES['logo_file'];
            $fileInfo = pathinfo($uploadedFile['name']);
            $extension = strtolower($fileInfo['extension']);

            if (LogoUpload::jePodporovanaPripona($extension)) {
                $host = LogoUpload::hostZUrlProNazevSouboru($url);

                if ($host === '') {
                    $errorMessage = "Neplatná URL.";
                } elseif ($extension === 'svg' && ($svgError = LogoUpload::validujSvgSoubor($uploadedFile['tmp_name']))) {
                    $errorMessage = $svgError;
                } else {
                    $filename = $host . '.' . $extension;

                    $targetDir = $adresarProAkci($action);

                    (new Filesystem())->mkdir($targetDir);

                    $targetPath = $targetDir . '/' . $filename;

                    if (move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
                        $successMessage = "Logo $filename bylo úspěšně nahráno.";
                    } else {
                        $errorMessage = "Nepodařilo se nahrát soubor.";
                    }
                }
            } else {
                $errorMessage = 'Podporované formáty: ' . LogoUpload::getPopisFormatu() . '.';
            }
        } else {
            $errorMessage = "Název a URL jsou povinné.";
        }
    } else {
        $errorMessage = "Nepodařilo se nahrát soubor.";
    }

    if (isset($successMessage)) {
        oznameni($successMessage, false);
    }
    if (isset($errorMessage)) {
        chyba($errorMessage, false);
    }
}

$t = new XTemplate(__DIR__ . '/loga.xtpl');
// kotvy #sponzori-loga a #partneri-loga jsou v šablonách titulka.xtpl a sponzori.xtpl, hlídá je test
$t->assign([
    'urlSponzoriTitulka' => htmlspecialchars(URL_WEBU . '/#sponzori-loga'),
    'urlPartneriTitulka' => htmlspecialchars(URL_WEBU . '/#partneri-loga'),
    'urlSponzoriPrehled' => htmlspecialchars(URL_WEBU . '/sponzori#sponzori-loga'),
    'urlPartneriPrehled' => htmlspecialchars(URL_WEBU . '/sponzori#partneri-loga'),
]);

foreach ($blokyPodleTypu as $typ => $blok) {
    foreach ((new ZpracovaneObrazky($adresarePodleTypu[$typ]))->seznamObrazku() as $nazev => $data) {
        $t->assign([
            'nazev'    => $nazev,
            'src'      => $data['src'],
            'url'      => $data['url'],
            'filename' => htmlspecialchars(basename($data['src']->soubor())),
        ]);
        $t->parse("loga.{$blok}.logo");
    }
    $t->parse("loga.{$blok}");
}

$t->parse('loga');
$t->out('loga');
