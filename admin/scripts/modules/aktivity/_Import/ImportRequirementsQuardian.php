<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;

class ImportRequirementsQ
{

  /**
   * @var ImportObjectsContainer
   */
  private $importObjectsContainer;

  public function __construct(ImportObjectsContainer $importObjectsContainer) {
    $this->importObjectsContainer = $importObjectsContainer;
  }

  public function guardSingleProgramLineOnly(array $activitiesValues, string $processedFileName): ImportStepResult {
    $programLines = [];
    foreach ($activitiesValues as $row) {
      $programLine = null;
      $programLineId = null;
      $programLineValue = $row[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null;
      if ($programLineValue) {
        $programLine = $this->importObjectsContainer->getProgramLineFromValue((string)$programLineValue);
      }
      if (!$programLine && $row[ExportAktivitSloupce::ID_AKTIVITY]) {
        $activity = ImportModelsFetcher::fetchActivity($row[ExportAktivitSloupce::ID_AKTIVITY]);
        if ($activity && $activity->typ()) {
          $programLine = $activity->typ();
        }
      }
      if ($programLine && !array_key_exists($programLine->id(), $programLines)) {
        $programLines[$programLineId] = $programLine;
      }
    }
    if (count($programLines) > 1) {
      return ImportStepResult::error(sprintf(
        'Importovat lze pouze jednu programovou linii. Importní soubor %s jich má %d: %s.',
        $processedFileName,
        count($programLines),
        implode(
          ',',
          self::wrapByQuotes(array_map(static function (\Typ $typ) {
            return $typ->nazev();
          }, $programLines))
        )));
    }
    if (count($programLines) === 0) {
      return ImportStepResult::error('V importovaném souboru chybí programová linie, nebo alespoň existující aktivita s nastavenou programovou linií.');
    }
    return ImportStepResult::success(reset($programLines));
  }

  private static function wrapByQuotes(array $values): array {
    return array_map(static function ($value) {
      return "'$value'";
    }, $values);
  }

}
