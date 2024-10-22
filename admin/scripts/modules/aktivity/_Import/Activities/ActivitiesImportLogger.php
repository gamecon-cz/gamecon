<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import\Activities;

use Gamecon\Cas\DateTimeCz;

class ActivitiesImportLogger
{
    public function logUsedSpreadsheet(int $userId, string $spreadsheetId, \DateTimeInterface $at) {
        dbQuery(<<<SQL
INSERT INTO akce_import (id_uzivatele, google_sheet_id, cas)
VALUES ($1, $2, $3)
SQL
            , [$userId, $spreadsheetId, (new DateTimeCz($at->format(DATE_ATOM)))->formatDb()]
        );
    }

    /**
     * @param string[] $spreadsheetIds
     * @return string[]
     */
    public function splitGoogleSheetIdsToUsedAndUnused(array $spreadsheetIds): array {
        if (count($spreadsheetIds) === 0) {
            return [
                'used' => [],
                'unused' => [],
            ];
        }
        $spreadsheetIdsSql = dbQa($spreadsheetIds);
        $usedSpreadsheetIds = dbOneArray(<<<SQL
SELECT google_sheet_id
FROM akce_import
WHERE google_sheet_id IN ($spreadsheetIdsSql);
SQL
        );
        return [
            'used' => $usedSpreadsheetIds,
            'unused' => array_diff($spreadsheetIds, $usedSpreadsheetIds),
        ];
    }

    public function getImportedAt(string $spreadsheetId, \DateTimeZone $timezone): ?DateTimeCz {
        $atString = dbOneCol(<<<SQL
SELECT cas
FROM akce_import
WHERE google_sheet_id = $1
SQL
            , [$spreadsheetId]
        );
        if (!$atString) {
            return null;
        }
        return DateTimeCz::createFromFormat(DateTimeCz::FORMAT_DB, $atString, $timezone);
    }

    public function wasImported(string $spreadsheetId): bool {
        return (bool)dbOneCol(<<<SQL
SELECT 1
FROM akce_import
WHERE google_sheet_id = $1
LIMIT 1
SQL
            , [$spreadsheetId]
        );
    }
}
