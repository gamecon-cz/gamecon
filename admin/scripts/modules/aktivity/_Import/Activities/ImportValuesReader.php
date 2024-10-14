<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import\Activities;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleApiException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Vyjimkovac\Logovac;
use Psr\Log\LoggerInterface;

class ImportValuesReader
{
    /**
     * @var GoogleSheetsService
     */
    private $googleSheetsService;
    /**
     * @var LoggerInterface
     */
    private $logovac;

    public function __construct(
        GoogleSheetsService $googleSheetsService,
        Logovac $logovac
    ) {
        $this->googleSheetsService = $googleSheetsService;
        $this->logovac = $logovac;
    }

    public function getIndexedValues(string $spreadsheetId): ImportStepResult {
        try {
            $rawValues = $this->googleSheetsService->getSpreadsheetValues($spreadsheetId);
        } catch (GoogleApiException | \Google_Service_Exception $exception) {
            $this->logovac->zaloguj($exception);
            return ImportStepResult::error('Google Sheets API je dočasně nedostupné. Zkus to za chvíli znovu.');
        }
        $cleansedValuesResult = $this->cleanseValues($rawValues);
        if ($cleansedValuesResult->isError()) {
            return ImportStepResult::error($cleansedValuesResult->getError());
        }
        $cleansedValues = $cleansedValuesResult->getSuccess();
        $cleansedHeaderResult = $this->getCleansedHeader($cleansedValues);
        if ($cleansedHeaderResult->isError()) {
            return ImportStepResult::error($cleansedHeaderResult->getError());
        }
        $cleansedHeader = $cleansedHeaderResult->getSuccess();
        unset($cleansedValues[array_key_first($cleansedValues)]); // remove row with header

        $indexedValues = [];
        $positionsOfValuesWithoutHeaders = [];
        foreach ($cleansedValues as $cleansedRow) {
            $indexedRow = [];
            foreach ($cleansedRow as $columnIndex => $cleansedValue) {
                $columnName = $cleansedHeader[$columnIndex] ?? false;
                if ($columnName) {
                    $indexedRow[$columnName] = $cleansedValue;
                } else if ($cleansedValue !== '') {
                    $positionsOfValuesWithoutHeaders[$columnIndex] = $columnIndex + 1;
                }
            }
            if (count($positionsOfValuesWithoutHeaders) > 0) {
                return ImportStepResult::error(
                    sprintf('Některým sloupcům chybí název a to na pozicích %s.', implode(',', $positionsOfValuesWithoutHeaders))
                );
            }
            $indexedValues[] = $indexedRow;
        }
        return ImportStepResult::success($indexedValues);
    }

    private function cleanseValues(array $values): ImportStepResult {
        $cleansedValues = [];
        foreach ($values as $row) {
            $cleansedRow = [];
            $rowIsEmpty = true;
            foreach ($row as $value) {
                $cleansedValue = trim($value);
                $cleansedRow[] = $cleansedValue;
                $rowIsEmpty = $rowIsEmpty && $cleansedValue === '';
            }
            if (!$rowIsEmpty) {
                $cleansedValues[] = $cleansedRow;
            }
        }
        if (count($cleansedValues) === 0) {
            return ImportStepResult::error('Žádná data. Import je prázdný.');
        }
        return ImportStepResult::success($cleansedValues);
    }

    private function getCleansedHeader(array $values): ImportStepResult {
        $unifiedKnownColumns = [];
        foreach (ExportAktivitSloupce::vsechnySloupceAktivity() as $knownColumn) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $keyFromColumn = ImportKeyUnifier::toUnifiedKey($knownColumn, $unifiedKnownColumns, ImportKeyUnifier::UNIFY_UP_TO_LETTERS);
            $unifiedKnownColumns[$keyFromColumn] = $knownColumn;
        }
        $header = reset($values);
        $cleansedHeader = [];
        $unknownColumns = [];
        $emptyColumnsPositions = [];
        foreach ($header as $index => $value) {
            /** @noinspection PhpUnhandledExceptionInspection */
            $unifiedValue = ImportKeyUnifier::toUnifiedKey($value, [], ImportKeyUnifier::UNIFY_UP_TO_LETTERS);
            if (array_key_exists($unifiedValue, $unifiedKnownColumns)) {
                $cleansedHeader[$index] = $unifiedKnownColumns[$unifiedValue];
            } else if ($value === '') {
                $emptyColumnsPositions[$index] = $index + 1;
            } else {
                $unknownColumns[] = $value;
            }
        }
        if (count($unknownColumns) > 0) {
            return ImportStepResult::error(
                sprintf('Neznámé názvy sloupců %s', implode(',', array_map(static function (string $value) {
                    return "'$value'";
                }, $unknownColumns)))
            );
        }
        if (count($cleansedHeader) === 0) {
            return ImportStepResult::error('Chybí názvy sloupců v prvním řádku (v záhlaví).');
        }
        if (count($emptyColumnsPositions) > 0 && max(array_keys($cleansedHeader)) > min(array_keys($emptyColumnsPositions))) {
            return ImportStepResult::error(sprintf('Některé názvy sloupců jsou prázdné a to na pozicích %s', implode(',', $emptyColumnsPositions)));
        }
        return ImportStepResult::success($cleansedHeader);
    }

}
