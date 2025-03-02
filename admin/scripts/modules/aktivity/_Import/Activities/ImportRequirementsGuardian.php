<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import\Activities;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Admin\Modules\Aktivity\Import\Activities\Exceptions\ActivitiesImportException;
use Gamecon\Aktivita\TypAktivity;

class ImportRequirementsGuardian
{

    /**
     * @var ImportObjectsContainer
     */
    private $importObjectsContainer;

    public function __construct(ImportObjectsContainer $importObjectsContainer) {
        $this->importObjectsContainer = $importObjectsContainer;
    }

    /**
     * @param string[][] $activitiesValues
     * @param string $processedFileName
     * @return ImportStepResult
     */
    public function guardSingleProgramLineOnly(array $activitiesValues, string $processedFileName): ImportStepResult {
        $programLines = [];
        $unknownProgramLines = [];
        foreach ($activitiesValues as $row) {
            $programLine = null;
            $programLineId = null;
            $programLineValue = $row[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null;
            if ($programLineValue) {
                $programLine = $this->importObjectsContainer->getProgramLineFromValue($programLineValue);
                if (!$programLine) {
                    $unknownProgramLines[] = $programLineValue;
                    continue;
                }
            }
            if (!$programLine && !empty($row[ExportAktivitSloupce::ID_AKTIVITY])) {
                try {
                    $activity = ImportModelsFetcher::fetchActivity((int)$row[ExportAktivitSloupce::ID_AKTIVITY]);
                    if ($activity->typ()) {
                        $programLine = $activity->typ();
                    }
                } catch (ActivitiesImportException $activitiesImportException) {
                    /** invalid activity ID - not a responsibility of this method
                     * @see \Gamecon\Admin\Modules\Aktivity\Import\Activities\ImportValuesSanitizer::sanitizeValuesToImport
                     */
                }
            }
            if ($programLine && !array_key_exists($programLine->id(), $programLines)) {
                $programLines[$programLineId] = $programLine;
            }
        }
        if (count($programLines) > 1) {
            return ImportStepResult::error(
                sprintf(
                    'Importovat lze pouze jednu programovou linii. Importní soubor %s jich má %d: %s.',
                    $processedFileName,
                    count($programLines),
                    implode(
                        ',',
                        self::wrapByQuotes(array_map(static function (TypAktivity $typ) {
                            return $typ->nazev();
                        }, $programLines))
                    )
                )
            );
        }
        if (count($programLines) === 0) {
            return count($unknownProgramLines) > 0
                ? ImportStepResult::error(
                    sprintf(
                        'V importovaném souboru jsou neznámé programové linie %s',
                        implode(',', self::wrapByQuotes(array_unique($unknownProgramLines)))
                    )
                )
                : ImportStepResult::error('V importovaném souboru chybí programová linie, nebo alespoň existující aktivita s nastavenou programovou linií.');
        }
        return ImportStepResult::success(reset($programLines));
    }

    private static function wrapByQuotes(array $values): array {
        return array_map(static function ($value) {
            return "'$value'";
        }, $values);
    }

}
