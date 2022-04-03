<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Export;

use Gamecon\Admin\Modules\Aktivity\Export\Exceptions\ActivitiesExportException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleConnectionException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Cas\DateTimeCz;

class ActivitiesExporter
{

    private const EXPORT_DIR = '/%user%.admin.gamecon.cz/aktivity';
    private const EXPORT_DIR_TAG = 'root-export-dir';

    /**
     * @var GoogleDriveService
     */
    private $googleDriveService;
    /**
     * @var GoogleSheetsService
     */
    private $googleSheetsService;
    /**
     * @var \Uzivatel
     */
    private $uzivatel;

    public function __construct(
        \Uzivatel           $uzivatel,
        GoogleDriveService  $googleDriveService,
        GoogleSheetsService $googleSheetsService
    ) {
        $this->googleDriveService = $googleDriveService;
        $this->googleSheetsService = $googleSheetsService;
        $this->uzivatel = $uzivatel;
    }

    /**
     * @param array|\Aktivita[] $activities
     * @param string $prefix
     * @return string Name of exported file
     */
    public function exportActivities(array $activities, string $prefix): string {
        $activitySheetTitle = $this->getActivitySheetTitle($activities);
        $spreadsheetTitle = $this->getSpreadsheetTitle($prefix, $activitySheetTitle);
        $spreadSheet = $this->createSheetForActivities($spreadsheetTitle, $activitySheetTitle);

        try {
            $activitiesData = $this->getActivitiesData($activities);
            $this->saveActivitiesData($activitiesData, $spreadSheet);

            $allTagsData = $this->getAllTagsData();
            $this->saveTagsData($allTagsData, $spreadSheet);

            $allStorytellersData = $this->getAllStorytellersData();
            $this->saveStorytellersData($allStorytellersData, $spreadSheet);

            $allRoomsData = $this->getAllRoomsData();
            $this->saveRoomsData($allRoomsData, $spreadSheet);

            $allActivityStatesData = $this->getAllActivityStatesData();
            $this->saveActivityStatesData($allActivityStatesData, $spreadSheet);

            $this->moveSpreadsheetToExportDir($spreadSheet);
        } catch (GoogleConnectionException|\Google_Service_Exception $connectionException) {
            try {
                $this->deleteSheet($spreadSheet);
            } catch (GoogleConnectionException|\Google_Service_Exception $deleteConnectionException) {
            }
            throw $connectionException;
        }
        return $spreadsheetTitle;
    }

    private function deleteSheet(\Google_Service_Sheets_Spreadsheet $spreadsheet) {
        $this->googleDriveService->deleteFile($spreadsheet->getSpreadsheetId());
    }

    /**
     * @param \Aktivita[] $aktivity
     * @return array
     */
    private function getActivitiesData(array $aktivity): array {
        $headerRow = ExportAktivitSloupce::vsechnySloupce();
        $data = [$headerRow];
        $oneDayInSeconds = 86400;
        foreach ($aktivity as $aktivita) {
            $zacatek = $aktivita->zacatek();
            $konec = $aktivita->konec();
            $zacatekDen = $zacatek
                ? $zacatek->format('l')
                : '';
            $konecDen = $konec
                ? $konec->format('l')
                : '';
            $zacatekCas = $zacatek
                ? $zacatek->format('G:i')
                : '';
            $zacatekCas = preg_replace('~:00$~', '', $zacatekCas);
            $konecCas = $konec
                ? $konec->format('G:i')
                : '';
            $konecCas = preg_replace('~:00$~', '', $konecCas);
            $endAtSameDayAtMidnight = $konecCas === '0' // midnight
                && $zacatek && $konec
                && $zacatek->modify('+1 day')->format('Ymd') === $konec->format('Ymd');
            if ($endAtSameDayAtMidnight) {
                $konecCas = '24'; // midnight
            }
            if ($aktivita->zacatek() && $aktivita->konec()) {
                $trvaniAktivity = $aktivita->konec()->getTimestamp() - $aktivita->zacatek()->getTimestamp();
                if ($trvaniAktivity > $oneDayInSeconds) {
                    throw new ActivitiesExportException(
                        "Aktivita by neměla začínat a končit v jiný den, nanejvýše o půlnoci: začátek '$zacatekDen':'$zacatekCas', konec '$konecDen':'$konecCas' u aktivity {$aktivita->id()} ({$aktivita->nazev()})"
                    );
                }
            }
            $unsortedDataRow = [
                ExportAktivitSloupce::ID_AKTIVITY => $aktivita->id(), // ID aktivity
                ExportAktivitSloupce::PROGRAMOVA_LINIE => $aktivita->typ()->nazev(), // Programová linie
                ExportAktivitSloupce::NAZEV => $aktivita->nazev(), // Název
                ExportAktivitSloupce::URL => $aktivita->urlId(), // URL
                ExportAktivitSloupce::KRATKA_ANOTACE => $aktivita->kratkyPopis(), // Krátká anotace
                ExportAktivitSloupce::TAGY => implode('; ', $aktivita->tagy()), // Tagy
                ExportAktivitSloupce::DLOUHA_ANOTACE => $aktivita->getPopisRaw(), // Dlouhá anotace
                ExportAktivitSloupce::DEN => $zacatekDen, // Den
                ExportAktivitSloupce::ZACATEK => $zacatekCas, // Začátek
                ExportAktivitSloupce::KONEC => $konecCas, // Konec
                ExportAktivitSloupce::MISTNOST => ($lokace = $aktivita->lokace())
                    ? $lokace->nazev()
                    : '', // Místnost
                ExportAktivitSloupce::VYPRAVECI => implode('; ', $aktivita->orgLoginy()->getArrayCopy()), // Vypravěči
                ExportAktivitSloupce::KAPACITA_UNISEX => $aktivita->getKapacitaUnisex(), // Kapacita unisex
                ExportAktivitSloupce::KAPACITA_MUZI => $aktivita->getKapacitaMuzu(), // Kapacita muži
                ExportAktivitSloupce::KAPACITA_ZENY => $aktivita->getKapacitaZen(), // Kapacita ženy
                ExportAktivitSloupce::JE_TYMOVA => $aktivita->tymova() // Je týmová
                    ? 'ano'
                    : 'ne',
                ExportAktivitSloupce::MINIMALNI_KAPACITA_TYMU => $aktivita->tymMinKapacita() ?? '', // Minimální kapacita týmu
                ExportAktivitSloupce::MAXIMALNI_KAPACITA_TYMU => $aktivita->tymMaxKapacita() ?? '', // Maximální kapacita týmu
                ExportAktivitSloupce::NASLEDUJICI_SEMIFINALE => implode(', ', array_map( // Následující (semi)finále
                    static function (\Aktivita $aktivita) {
                        // can not allow comma "," in a name as that is used on import as a values delimiter
                        return $aktivita->id() . ' - ' . str_replace(',', ' ', $aktivita->nazev());
                    },
                    $aktivita->deti()
                )),
                ExportAktivitSloupce::CENA => (float)$aktivita->cenaZaklad(), // Cena
                ExportAktivitSloupce::BEZ_SLEV => $aktivita->bezSlevy() // Bez slev
                    ? 'ano'
                    : 'ne',
                ExportAktivitSloupce::PRIPRAVA_MISTNOSTI => (string)$aktivita->vybaveni(), // Příprava místnosti
                ExportAktivitSloupce::STAV => $aktivita->stav()->nazev(), // Stav
                ExportAktivitSloupce::OBRAZEK => $aktivita->maObrazek()
                    ? $aktivita->urlObrazku()
                    : '', // Obrázek
            ];
            $data[] = $this->sortActivitiesDataToMatchHeader($unsortedDataRow, $headerRow);
        }
        return $data;
    }

    /**
     * To allow source data in any order, just indexed by header for easier human readability @see getActivitiesData
     */
    private function sortActivitiesDataToMatchHeader(array $unsortedDataRow, array $headerRow): array {
        $sortedActivityData = [];
        foreach ($headerRow as $headerKey) {
            $sortedActivityData[] = $unsortedDataRow[$headerKey];
        }
        return $sortedActivityData;
    }

    private function getAllTagsData(): array {
        $data[] = ExportTaguSloupce::vsechnySloupce();
        foreach (\Tag::zVsech() as $tag) {
            $data[] = [
                $tag->id(),
                $tag->nazev(),
                $tag->poznamka(),
                $tag->katregorieTagu()->nazev(),
            ];
        }
        return $data;
    }

    private function getAllStorytellersData(): array {
        $data[] = ExportVypravecuSloupce::vsechnySloupce();
        foreach (\Uzivatel::poradateleAktivit() as $poradatelAktivit) {
            $data[] = [
                $poradatelAktivit->id(),
                $poradatelAktivit->mail(),
                $poradatelAktivit->jmenoNick(),
            ];
        }
        return $data;
    }

    private function getAllRoomsData(): array {
        $data[] = ExportLokaciSloupce::vsechnySloupce();
        foreach (\Lokace::zVsech() as $jednaLokace) {
            $data[] = [
                $jednaLokace->id(),
                $jednaLokace->nazev(),
                $jednaLokace->dvere(),
                $jednaLokace->poznamka(),
            ];
        }
        return $data;
    }

    private function getAllActivityStatesData(): array {
        $data[] = ExportStavuAktivitSloupce::vsechnySloupce();
        foreach (\Stav::zVsech() as $stav) {
            $data[] = [
                $stav->nazev(),
            ];
        }
        return $data;
    }

    private function saveActivitiesData(array $activitiesData, \Google_Service_Sheets_Spreadsheet $spreadsheet) {
        $this->googleSheetsService->setValuesInSpreadsheet($activitiesData, $spreadsheet->getSpreadsheetId(), 1);
    }

    private function saveTagsData(array $tagsData, \Google_Service_Sheets_Spreadsheet $spreadsheet) {
        $this->googleSheetsService->setValuesInSpreadsheet($tagsData, $spreadsheet->getSpreadsheetId(), 2);
    }

    private function saveStorytellersData(array $storytellersData, \Google_Service_Sheets_Spreadsheet $spreadsheet) {
        $this->googleSheetsService->setValuesInSpreadsheet($storytellersData, $spreadsheet->getSpreadsheetId(), 3);
    }

    private function saveRoomsData(array $roomsData, \Google_Service_Sheets_Spreadsheet $spreadsheet) {
        $this->googleSheetsService->setValuesInSpreadsheet($roomsData, $spreadsheet->getSpreadsheetId(), 4);
    }

    private function saveActivityStatesData(array $activityStatesData, \Google_Service_Sheets_Spreadsheet $spreadsheet) {
        $this->googleSheetsService->setValuesInSpreadsheet($activityStatesData, $spreadsheet->getSpreadsheetId(), 5);
    }

    private function createSheetForActivities(string $sheetTitle, string $activitySheetTitle): \Google_Service_Sheets_Spreadsheet {
        $newSpreadsheet = $this->googleSheetsService->createNewSpreadsheet(
            $sheetTitle,
            [mb_ucfirst($activitySheetTitle), 'Tagy', 'Vypravěči', 'Místnosti', 'Stavy']
        );
        foreach ($newSpreadsheet->getSheets() as $sheet) {
            $this->googleSheetsService->setFirstRowAsHeader($newSpreadsheet->getSpreadsheetId(), $sheet->getProperties()->getSheetId());
        }
        return $newSpreadsheet;
    }

    private function getSpreadsheetTitle(string $prefix, string $baseTitle): string {
        return sprintf('%d %s - %s', $prefix, $baseTitle, (new DateTimeCz())->formatCasStandard());
    }

    private function getActivitySheetTitle(array $aktivity): string {
        $activitiesTypeNames = $this->getActivitiesUniqueTypeNames($aktivity);
        sort($activitiesTypeNames);
        return implode(' a ', $activitiesTypeNames);
    }

    /**
     * @param array $aktivity
     * @return array|string[]
     */
    private function getActivitiesUniqueTypeNames(array $aktivity): array {
        return array_unique(
            array_map(
                static function (\Aktivita $aktivita) {
                    return $aktivita->typ()->nazev();
                },
                $aktivity
            )
        );
    }

    private function moveSpreadsheetToExportDir(\Google_Service_Sheets_Spreadsheet $spreadsheet) {
        $this->googleDriveService->moveFileToDir($spreadsheet->getSpreadsheetId(), $this->getExportDirId());
    }

    private function getExportDirId(): string {
        $wrappedRootExportDir = $this->googleDriveService->getLocalDirsReferencesByUserIdAndTag($this->uzivatel->id(), self::EXPORT_DIR_TAG);
        if ($wrappedRootExportDir) {
            $rootExportDir = reset($wrappedRootExportDir);
            $rootExportDirId = $rootExportDir->getGoogleDirId();
            if ($this->googleDriveService->dirByIdExists($rootExportDirId)) {
                return $rootExportDirId;
            }
            $this->googleDriveService->deleteLocalDirReferenceByDirId($rootExportDirId);
        }
        $createdDir = $this->createOrReuseDirForGameconExport();
        return $createdDir->getId();
    }

    private function createOrReuseDirForGameconExport(): \Google_Service_Drive_DriveFile {
        $exportDirName = $this->getExportDirName();
        $existingDirs = $this->googleDriveService->getDirsByName($exportDirName);
        $existingDir = reset($existingDirs);
        if ($existingDir) {
            $this->googleDriveService->saveDirReferenceLocally($existingDir, $this->uzivatel->id(), self::EXPORT_DIR_TAG);
            return $existingDir;
        }
        $createdDir = $this->googleDriveService->createDir($exportDirName);
        $this->googleDriveService->saveDirReferenceLocally($createdDir, $this->uzivatel->id(), self::EXPORT_DIR_TAG);
        return $createdDir;
    }

    private function getExportDirName(): string {
        return str_replace('%user%', $this->getUserForDir(), self::EXPORT_DIR);
    }

    private function getUserForDir(): string {
        $userName = $this->uzivatel->nick() ?: $this->uzivatel->jmeno();
        return preg_replace('~[^a-zA-Z]+~', '_', odstranDiakritiku($userName));
    }
}
