<?php

use Gamecon\Web\ZpracovaneObrazky;
use Gamecon\XTemplate\XTemplate;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Správa log sponzorů a partnerů
 *
 * nazev: Loga 👻
 * pravo: 112
 */

// Handle CSV download requests
if (isset($_GET['action']) && ($_GET['action'] === 'download_pattern_csv' || $_GET['action'] === 'download_current_csv')) {
    $typ = $_GET['typ'] ?? '';
    $action = $_GET['action'];
    $csvFile = '';
    $filename = '';

    $baseDir = '';
    switch ($typ) {
        case 'sponzor_titulka':
            $baseDir = ZpracovaneObrazky::adresarSponzoruTitulka();
            break;
        case 'partner_titulka':
            $baseDir = ZpracovaneObrazky::adresarPartneruTitulka();
            break;
        case 'sponzor_prehled':
            $baseDir = ZpracovaneObrazky::adresarSponzoruPrehled();
            break;
        case 'partner_prehled':
            $baseDir = ZpracovaneObrazky::adresarPartneruPrehled();
            break;
        default:
            throw new \RuntimeException(sprintf('Neznámý typ %s', $typ));
    }

    if ($action === 'download_current_csv') {
        $csvFile = $baseDir . '/RAZENI.csv';
        $filename = 'RAZENI.csv';
    } else {
        $csvFile = $baseDir . '/RAZENI-VZOR.csv';
        $filename = 'RAZENI-VZOR.csv';
    }

    if ($csvFile && file_exists($csvFile)) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($csvFile));
        readfile($csvFile);
        exit;
    } else {
        if ($action === 'download_current_csv') {
            chyba("Soubor RAZENI.csv nebyl nalezen. Nejdříve nahrajte řazení.");
        } else {
            chyba("Soubor RAZENI-VZOR.csv nebyl nalezen.");
        }
    }
}

if ($_POST) {
    $action = $_POST['action'] ?? '';

    // Handle remove actions
    if (str_starts_with($action, 'remove_')) {
        $filename = $_POST['filename'] ?? '';
        if ($filename) {
            $targetDir = '';
            switch ($action) {
                case 'remove_sponzor_titulka':
                    $targetDir = ZpracovaneObrazky::adresarSponzoruTitulka();
                    break;
                case 'remove_partner_titulka':
                    $targetDir = ZpracovaneObrazky::adresarPartneruTitulka();
                    break;
                case 'remove_sponzor_prehled':
                    $targetDir = ZpracovaneObrazky::adresarSponzoruPrehled();
                    break;
                case 'remove_partner_prehled':
                    $targetDir = ZpracovaneObrazky::adresarPartneruPrehled();
                    break;
                default:
                    throw new \RuntimeException(sprintf('Neznámá akce: %s', $action));
            }

            $filePath = $targetDir . '/' . $filename;
            if (file_exists($filePath)) {
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

    // Handle CSV upload actions
    if (str_starts_with($action, 'upload_') && str_ends_with($action, '_razeni')) {
        if (isset($_FILES['razeni_file']) && $_FILES['razeni_file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFile = $_FILES['razeni_file'];
            $fileInfo = pathinfo($uploadedFile['name']);
            $extension = strtolower($fileInfo['extension']);

            if ($extension === 'csv') {
                $targetDir = '';
                switch ($action) {
                    case 'upload_sponzor_titulka_razeni':
                        $targetDir = ZpracovaneObrazky::adresarSponzoruTitulka();
                        break;
                    case 'upload_partner_titulka_razeni':
                        $targetDir = ZpracovaneObrazky::adresarPartneruTitulka();
                        break;
                    case 'upload_sponzor_prehled_razeni':
                        $targetDir = ZpracovaneObrazky::adresarSponzoruPrehled();
                        break;
                    case 'upload_partner_prehled_razeni':
                        $targetDir = ZpracovaneObrazky::adresarPartneruPrehled();
                        break;
                    default:
                        throw new \RuntimeException(sprintf('Neznámá akce: %s', $action));
                }

                if ($targetDir) {
                    (new Filesystem())->mkdir($targetDir);
                    $targetPath = $targetDir . '/RAZENI.csv';

                    if (move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
                        oznameni("Soubor RAZENI.csv byl úspěšně nahrán.");
                    } else {
                        chyba("Nepodařilo se nahrát soubor RAZENI.csv.");
                    }
                } else {
                    chyba("Neplatná akce.");
                }
            } else {
                chyba("Podporován je pouze CSV formát.");
            }
        } else {
            chyba("Nepodařilo se nahrát CSV soubor.");
        }
        return;
    }

    if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
        $url = trim($_POST['url'] ?? '');

        if ($url) {
            $uploadedFile = $_FILES['logo_file'];
            $fileInfo = pathinfo($uploadedFile['name']);
            $extension = strtolower($fileInfo['extension']);

            if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
                $sanitizedUrl = preg_replace('/^https?:\/\//', '', $url);
                $filename = $sanitizedUrl . '.' . $extension;

                $targetDir = '';
                switch ($action) {
                    case 'upload_sponzor_titulka':
                        $targetDir = ZpracovaneObrazky::adresarSponzoruTitulka();
                        break;
                    case 'upload_partner_titulka':
                        $targetDir = ZpracovaneObrazky::adresarPartneruTitulka();
                        break;
                    case 'upload_sponzor_prehled':
                        $targetDir = ZpracovaneObrazky::adresarSponzoruPrehled();
                        break;
                    case 'upload_partner_prehled':
                        $targetDir = ZpracovaneObrazky::adresarPartneruPrehled();
                        break;
                    default:
                        throw new \RuntimeException(sprintf('Neznámá akce: %s', $action));
                }

                if ($targetDir) {
                    (new Filesystem())->mkdir($targetDir);

                    $targetPath = $targetDir . '/' . $filename;

                    if (move_uploaded_file($uploadedFile['tmp_name'], $targetPath)) {
                        $successMessage = "Logo $filename bylo úspěšně nahráno.";
                    } else {
                        $errorMessage = "Nepodařilo se nahrát soubor.";
                    }
                } else {
                    $errorMessage = "Neplatná akce.";
                }
            } else {
                $errorMessage = "Podporované formáty: JPG, PNG, GIF, WebP.";
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
$t->assign('actionBaseUrl', $_SERVER['REQUEST_URI']);

// Načtení log z jednotlivých adresářů
$logaSponzoruTitulka = ZpracovaneObrazky::logaSponzoruTitulka();
$logaPartneruTitulka = ZpracovaneObrazky::logaPartneruTitulka();
$logaSponzoruPrehled = ZpracovaneObrazky::logaSponzoruPrehled();
$logaPartneruPrehled = ZpracovaneObrazky::logaPartneruPrehled();

// Výpis log sponzorů na titulce
$seznamSponzoruTitulka = $logaSponzoruTitulka->seznamObrazku();
foreach ($seznamSponzoruTitulka as $nazev => $data) {
    $t->assign([
        'nazev' => $nazev,
        'src'   => $data['src'],
        'url'   => $data['url'],
        'filename' => basename($data['src']->soubor()),
    ]);
    $t->parse('loga.sponzoriTitulka.logo');
}
$t->parse('loga.sponzoriTitulka');

// Výpis log partnerů na titulce
$seznamPartneruTitulka = $logaPartneruTitulka->seznamObrazku();
foreach ($seznamPartneruTitulka as $nazev => $data) {
    $t->assign([
        'nazev' => $nazev,
        'src'   => $data['src'],
        'url'   => $data['url'],
        'filename' => basename($data['src']->soubor()),
    ]);
    $t->parse('loga.partneriTitulka.logo');
}
$t->parse('loga.partneriTitulka');

// Výpis log sponzorů v přehledu
$seznamSponzoruPrehled = $logaSponzoruPrehled->seznamObrazku();
foreach ($seznamSponzoruPrehled as $nazev => $data) {
    $t->assign([
        'nazev' => $nazev,
        'src'   => $data['src'],
        'url'   => $data['url'],
        'filename' => basename($data['src']->soubor()),
    ]);
    $t->parse('loga.sponzoriPrehled.logo');
}
$t->parse('loga.sponzoriPrehled');

// Výpis log partnerů v přehledu
$seznamPartneruPrehled = $logaPartneruPrehled->seznamObrazku();
foreach ($seznamPartneruPrehled as $nazev => $data) {
    $t->assign([
        'nazev' => $nazev,
        'src'   => $data['src'],
        'url'   => $data['url'],
        'filename' => basename($data['src']->soubor()),
    ]);
    $t->parse('loga.partneriPrehled.logo');
}
$t->parse('loga.partneriPrehled');

$t->parse('loga');
$t->out('loga');
