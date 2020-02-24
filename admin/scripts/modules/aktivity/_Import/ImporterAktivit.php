<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\Exceptions\GoogleApiException;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleDriveService;
use Gamecon\Admin\Modules\Aktivity\GoogleSheets\GoogleSheetsService;
use Gamecon\Admin\Modules\Aktivity\Import\Exceptions\ImportAktivitException;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Vyjimkovac\Logovac;

class ImporterAktivit
{

  /**
   * @var GoogleDriveService
   */
  private $googleDriveService;
  /**
   * @var GoogleSheetsService
   */
  private $googleSheetsService;
  /**
   * @var int
   */
  private $userId;
  /**
   * @var int
   */
  private $currentYear;
  /**
   * @var \DateTimeInterface
   */
  private $now;
  /**
   * @var Logovac
   */
  private $logovac;
  /**
   * @var array|\Aktivita[]|null[]
   */
  private $oldActivities = [];
  /**
   * @var array|\Typ[][]
   */
  private $programLinesCache;
  /**
   * @var array|\Typ[][]
   */
  private $programLocationsCache;
  /**
   * @var array|\Tag[][]
   */
  private $tagsCache;

  public function __construct(
    int $userId,
    GoogleDriveService $googleDriveService,
    GoogleSheetsService $googleSheetsService,
    int $currentYear,
    \DateTimeInterface $now,
    Logovac $logovac
  ) {
    $this->googleDriveService = $googleDriveService;
    $this->googleSheetsService = $googleSheetsService;
    $this->userId = $userId;
    $this->currentYear = $currentYear;
    $this->now = $now;
    $this->logovac = $logovac;
  }

  public function importujAktivity(string $spreadsheetId): array {
    $result = [
      'importedCount' => 0,
      'processedFileName' => null,
      'messages' => [
        'notices' => [],
        'warnings' => [],
        'errors' => [],
      ],
    ];
    try {
      $result['processedFileName'] = $this->googleDriveService->getFileName($spreadsheetId);
      $activitiesValues = $this->getIndexedValues($spreadsheetId);

      ['error' => $singleProgramLineError] = $this->guardSingleProgramLineOnly($activitiesValues);
      if ($singleProgramLineError) {
        $result['messages']['errors'][] = $singleProgramLineError;
        return $result;
      }

      /** @var int|null $parentActivityId */
      $parentActivityId = null;
      foreach ($activitiesValues as $activityValues) {
        ['success' => $activityId, 'error' => $activityIdError] = $this->getActivityId($activityValues);
        if ($activityIdError) {
          $result['messages']['errors'][] = $activityIdError;
          continue;
        }
        if ($activityId) {
          ['success' => $importExistingActivitySuccess, 'error' => $importExistingActivityError] = $this->importExistingActivity($activityId, $activityValues);
          if ($importExistingActivityError) {
            $result['messages']['errors'][] = $importExistingActivityError;
            continue;
          }
          if ($importExistingActivitySuccess) {
            $result['messages']['notices'][] = $importExistingActivitySuccess;
          }
          $parentActivityId = $activityId;
        } else if ($parentActivityId && $this->mayBeInstance($parentActivityId, $activityValues)) {
          ['success' => $importInstanceSuccess, 'error' => $importInstanceError] = $this->importInstance($parentActivityId, $activityValues);
          if ($importInstanceError) {
            $result['messages']['errors'][] = $importInstanceError;
            continue;
          }
          if ($importInstanceSuccess) {
            $result['messages']['notices'][] = $importInstanceSuccess;
          }
        } else {
          ['success' => $importNewActivitySuccess, 'error' => $importNewActivityError] = $parentActivityId = $this->importNewActivity($activityValues);
          if ($importNewActivityError) {
            $result['messages']['errors'][] = $importNewActivityError;
            continue;
          }
          if ($importNewActivitySuccess) {
            $result['messages']['notices'][] = $importNewActivitySuccess;
          }
        }
      }
    } catch (\Google_Service_Exception $exception) {
      $result['messages']['errors'][] = 'Google sheets API je dočasně nedostupné. Zuste to prosím za chvíli znovu.';
      $this->logovac->zaloguj($exception);
      return $result;
    }
    return $result;
  }

  private function importInstance(int $parentActivityId, array $values): array {
    return $this->success("TODO Zatím nic s instancí mateřské aktivity $parentActivityId: " . implode(';', $values));
  }

  private function success($success): array {
    return ['success' => $success, 'error' => false];
  }

  private function error(string $error): array {
    return ['success' => false, 'error' => $error];
  }

  private function mayBeInstance(int $parentActivityId, array $values): bool {
    /** @noinspection PhpUnhandledExceptionInspection */
    $parentActivity = $this->getOldActivityById($parentActivityId);

    $programovaLinie = $values[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null;
    if ($programovaLinie) {
      $programovaLinieId = (int)$programovaLinie; // type may be a name or an ID
      if ($programovaLinieId) {
        if ($programovaLinieId !== $parentActivity->typId()) {
          return false; // different type ID, this can not be an instance
        }
      } else if ($programovaLinie !== $parentActivity->typ()->nazev()) {
        return false; // different type name, this can not be an instance
      }
    }

    $nazev = $values[ExportAktivitSloupce::NAZEV] ?? null;
    if ($nazev && $nazev !== $parentActivity->nazev()) {
      return false; // different activity name, this can not be an instance
    }

    $url = $values[ExportAktivitSloupce::URL] ?? null;
    if ($url && $url === $parentActivity->urlId()) {
      return false; // different activity URL, this can not be an instance
    }

    return true; // it seems that this activity can be an instance of given parent activity
  }

  private function getOldActivityById(int $id): \Aktivita {
    $oldActivity = $this->findOldActivityById($id);
    if ($oldActivity) {
      return $oldActivity;
    }
    /** @noinspection PhpUnhandledExceptionInspection */
    throw new ImportAktivitException("Activity of ID '$id has not ben found");
  }

  private function findOldActivityById(int $id): ?\Aktivita {
    if (!array_key_exists($id, $this->oldActivities)) {
      $this->oldActivities[$id] = \Aktivita::zId($id);
    }
    return $this->oldActivities[$id];
  }

  private function getActivityId(array $activityValues): array {
    if ($activityValues[ExportAktivitSloupce::ID_AKTIVITY]) {
      return $this->success((int)$activityValues[ExportAktivitSloupce::ID_AKTIVITY]);
    }
    $programovaLinie = $activityValues[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null; // may be name or ID
    $url = $activityValues[ExportAktivitSloupce::URL] ?? null;
    if ($url && $programovaLinie) {
      $id = dbOneCol(<<<SQL
SELECT id_akce
FROM akce_seznam
INNER JOIN akce_typy ON akce_typy.id_typu = akce_seznam.typ
WHERE url_akce = $1 AND rok = $2 AND (akce_typy.typ_1pmn = $3 OR akce_typy.id_typu = $3)
SQL
        , [$url, $this->currentYear, $programovaLinie]
      );
      if ($id) {
        return ['success' => (int)$id, 'error' => false];
      }
    }
    if ($url) {
      $ids = dbOneArray(<<<SQL
SELECT id_akce
FROM akce_seznam
WHERE url_akce = $1 AND rok = $2
SQL
        , [$url, $this->currentYear]
      );
      if (count($ids) === 1) { // without type there may be more IDs per URL-and-year
        return $this->success((int)current($ids));
      }
    }
    $nazevAkce = $activityValues[ExportAktivitSloupce::NAZEV];
    if ($nazevAkce) {
      $ids = dbOneArray(<<<SQL
SELECT id_akce
FROM akce_seznam
WHERE nazev_akce = $1 AND rok = $2
SQL
        , [$nazevAkce, $this->currentYear]
      );
      if (count($ids) === 1) { // there may be more IDs per name-and-year
        return $this->success((int)current($ids));
      }
    }
    return $this->success(null);
  }

  private function guardSingleProgramLineOnly(array $values): array {
    $programLines = [];
    foreach ($values as $row) {
      $programLine = $row[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null;
      if ($programLine !== null && $programLine !== '' && !in_array($programLine, $programLines, true)) {
        $programLines[] = $programLine;
      }
    }
    if (count($programLines) > 1) {
      return $this->error(sprintf(
        'Importovat lze pouze jednu programovou linii. Importní soubor jich má %d: %s',
        count($programLines),
        implode(',', self::wrapByQuotes($programLines))
      ));
    }
    if (count($programLines) === 0) {
      return $this->error('Import musí určit programovou linii.');
    }
    return $this->success(true);
  }

  private static function wrapByQuotes(array $values): array {
    return array_map(static function ($value) {
      return "'$value'";
    }, $values);
  }

  private function getIndexedValues(string $spreadsheetId): array {
    try {
      $values = $this->googleSheetsService->getSpreadsheetValues($spreadsheetId);
    } catch (GoogleApiException $exception) {
      $this->logovac->zaloguj($exception);
      return $this->error('Google Sheets API je dočasně nedostupné, zkuste to znovu za chvíli.');
    }
    $cleanseValuesResult = $this->cleanseValues($values);
    ['success' => $cleansedValues, 'error' => $error] = $cleanseValuesResult;
    if ($error) {
      return ['success' => false, 'error' => $error];
    }
    $cleansedHeaderResult = $this->getCleansedHeader($cleansedValues);
    ['success' => $cleansedHeader, 'error' => $error] = $cleansedHeaderResult;
    if ($error) {
      return ['success' => false, 'error' => $error];
    }
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
        return [
          'success' => false,
          'error' => sprintf('Některým sloupcům chybí název a to na pozicích %s', implode(',', $positionsOfValuesWithoutHeaders)),
        ];
      }
      $indexedValues[] = $indexedRow;
    }
    return $indexedValues;
  }

  private function getCleansedHeader(array $values): array {
    $cleanse = static function (string $value) {
      strtolower(odstranDiakritiku($value));
    };
    $cleansedKnownColumns = array_map($cleanse, ExportAktivitSloupce::getVsechnySloupce());
    $header = reset($values);
    $cleansedHeader = [];
    $unknownColumns = [];
    $emptyColumnsPositions = [];
    foreach ($header as $index => $value) {
      $cleansedValue = $cleanse($value);
      if (in_array($cleansedValue, $cleansedKnownColumns, true)) {
        $cleansedHeader[$index] = $cleansedValue;
      } else if ($value === '') {
        $emptyColumnsPositions[$index] = $index + 1;
      } else {
        $unknownColumns[] = $value;
      }
    }
    if (count($unknownColumns) > 0) {
      return $this->error(sprintf('Neznámé názvy sloupců %s', implode(',', array_map(static function (string $value) {
        return "'$value'";
      }, $unknownColumns))));
    }
    if (count($cleansedHeader) === 0) {
      return $this->error('Chybí názvy sloupců v prvním řádku');
    }
    if (count($emptyColumnsPositions) > 0 && max(array_keys($cleansedHeader)) > min(array_keys($emptyColumnsPositions))) {
      return $this->error(sprintf('Některé náxvy sloupců jsou prázdné a to na pozicích %s', implode(',', $emptyColumnsPositions)));
    }
    return $this->success($cleansedHeader);
  }

  private function cleanseValues(array $values): array {
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
      return $this->error('Žádná data. Import je prázdný.');
    }
    return $this->success($cleansedValues);
  }

  private function importExistingActivity(int $id, array $activityValues): array {
    $aktivita = $this->findOldActivityById($id);
    if (!$aktivita) {
      return $this->error(sprintf("Aktivita s ID '%s' neexistuje. Nelze ji proto importem upravit.", $id));
    }
    if (!$aktivita->bezpecneEditovatelna()) {
      return $this->error(sprintf("Aktivitu '%s' (%d) už nelze editovat importem, protože je ve stavu '%s'", $aktivita->nazev(), $id, $aktivita->getStavNazev()));
    }
    if ($aktivita->zacatek() && $aktivita->zacatek()->getTimestamp() <= $this->now->getTimestamp()) {
      return $this->error(sprintf("Aktivitu '%s' (%d) už nelze editovat importem, protože už začala (začátek v %s)", $aktivita->nazev(), $id, $aktivita->zacatek()->formatCasNaMinutyStandard()));
    }
    if ($aktivita->konec() && $aktivita->konec()->getTimestamp() <= $this->now->getTimestamp()) {
      return $this->error(sprintf("Aktivitu '%s' (%d) už nelze editovat importem, protože už skončila (konec v %s)", $aktivita->nazev(), $id, $aktivita->konec()->formatCasNaMinutyStandard()));
    }

    ['success' => $sanitizedValues, 'error' => $sanitizedValuesError] = $this->sanitizeValues($activityValues, $aktivita);
    if ($sanitizedValuesError) {
      return $this->error($sanitizedValuesError);
    }

    return $this->success('TODO Zatím nic s existující aktivitou ' . implode(';', $sanitizedValues));
  }

  private function sanitizeValues(array $activityValues, \Aktivita $aktivita): array {
    $sanitizedValues = $aktivita->rawDb();
    $tagsNames = null;
    $storytellersIds = null;

    ['success' => $programLineId, 'error' => $programLineIdError] = $this->getValidatedProgramLineId($activityValues, $aktivita);
    if ($programLineIdError) {
      return $this->error($programLineIdError);
    }
    $sanitizedValues[AktivitaSqlSloupce::TYP] = $programLineId;

    ['success' => $activityName, 'error' => $activityNameError] = $this->getValidatedActivityName($activityValues, $aktivita);
    if ($activityNameError) {
      return $this->error($activityNameError);
    }
    $sanitizedValues[AktivitaSqlSloupce::NAZEV_AKCE] = $activityName;

    ['success' => $activityUrl, 'error' => $activityUrlError] = $this->getValidatedUrl($activityValues, $aktivita);
    if ($activityUrlError) {
      return $this->error($activityUrlError);
    }
    $sanitizedValues[AktivitaSqlSloupce::URL_AKCE] = $activityUrl;

    ['success' => $shortAnnotation, 'error' => $shortAnnotationError] = $this->getValidatedShortAnnotation($activityValues, $aktivita);
    if ($shortAnnotationError) {
      return $this->error($shortAnnotationError);
    }
    $sanitizedValues[AktivitaSqlSloupce::POPIS_KRATKY] = $shortAnnotation;

    ['success' => $tagsNames, 'error' => $tagsError] = $this->getValidatedTags($activityValues, $aktivita);
    if ($tagsError) {
      return $this->error($tagsError);
    }

    ['success' => $longAnnotation, 'error' => $longAnnotationError] = $this->getValidatedLongAnnotation($activityValues, $aktivita);
    if ($longAnnotationError) {
      return $this->error($longAnnotationError);
    }
    $sanitizedValues[AktivitaSqlSloupce::POPIS] = $longAnnotation;

    ['success' => $activityBeginning, 'error' => $activityBeginningError] = $this->getValidatedBeginning($activityValues, $aktivita);
    if ($activityBeginningError) {
      return $this->error($activityBeginningError);
    }
    $sanitizedValues[AktivitaSqlSloupce::ZACATEK] = $activityBeginning;

    ['success' => $activityEnd, 'error' => $activityEndError] = $this->getValidatedEnd($activityValues, $aktivita);
    if ($activityEndError) {
      return $this->error($activityEndError);
    }
    $sanitizedValues[AktivitaSqlSloupce::KONEC] = $activityEnd;

    ['success' => $programLocationId, 'error' => $programLocationIdError] = $this->getValidatedProgramLocationId($activityValues, $aktivita);
    if ($programLocationIdError) {
      return $this->error($programLocationIdError);
    }
    $sanitizedValues[AktivitaSqlSloupce::LOKACE] = $programLocationId;

    ['success' => $storytellersIds, 'error' => $storytellersIdsError] = $this->getValidatedStorytellersIds($activityValues, $aktivita);
    if ($storytellersIdsError) {
      return $this->error($storytellersIdsError);
    }

    ['success' => $unisexCapacity, 'error' => $unisexCapacityError] = $this->getValidatedUnisexCapacity($activityValues, $aktivita);
    if ($unisexCapacityError) {
      return $this->error($unisexCapacityError);
    }
    $sanitizedValues[AktivitaSqlSloupce::KAPACITA] = $unisexCapacity;

    ['success' => $menCapacity, 'error' => $menCapacityError] = $this->getValidatedMenCapacity($activityValues, $aktivita);
    if ($menCapacityError) {
      return $this->error($menCapacityError);
    }
    $sanitizedValues[AktivitaSqlSloupce::KAPACITA_M] = $menCapacity;

    ['success' => $womenCapacity, 'error' => $womenCapacityError] = $this->getValidatedWomenCapacity($activityValues, $aktivita);
    if ($womenCapacityError) {
      return $this->error($womenCapacityError);
    }
    $sanitizedValues[AktivitaSqlSloupce::KAPACITA_F] = $womenCapacity;

    ['success' => $forTeam, 'error' => $forTeamError] = $this->getValidatedForTeam($activityValues, $aktivita);
    if ($forTeamError) {
      return $this->error($forTeamError);
    }
    $sanitizedValues[AktivitaSqlSloupce::TEAMOVA] = $forTeam;

    ['success' => $minimalTeamCapacity, 'error' => $minimalTeamCapacityError] = $this->getValidatedMinimalTeamCapacity($activityValues, $aktivita);
    if ($minimalTeamCapacityError) {
      return $this->error($minimalTeamCapacityError);
    }
    $sanitizedValues[AktivitaSqlSloupce::TEAM_MIN] = $minimalTeamCapacity;

    ['success' => $maximalTeamCapacity, 'error' => $maximalTeamCapacityError] = $this->getValidatedMaximalTeamCapacity($activityValues, $aktivita);
    if ($maximalTeamCapacityError) {
      return $this->error($maximalTeamCapacityError);
    }
    $sanitizedValues[AktivitaSqlSloupce::TEAM_MAX] = $maximalTeamCapacity;

    ['success' => $price, 'error' => $priceError] = $this->getValidatedPrice($activityValues, $aktivita);
    if ($priceError) {
      return $this->error($priceError);
    }
    $sanitizedValues[AktivitaSqlSloupce::CENA] = $price;

    ['success' => $withoutDiscount, 'error' => $withoutDiscountError] = $this->getValidatedWithoutDiscount($activityValues, $aktivita);
    if ($withoutDiscountError) {
      return $this->error($withoutDiscountError);
    }
    $sanitizedValues[AktivitaSqlSloupce::BEZ_SLEVY] = $withoutDiscount;

    // vybavevni
    // stav

    ['success' => $year, 'error' => $yearError] = $this->getValidatedYear($activityValues, $aktivita);
    if ($yearError) {
      return $this->error($yearError);
    }
    $sanitizedValues[AktivitaSqlSloupce::ROK] = $year;

    return $this->success($sanitizedValues);
  }

  private function getValidatedMinimalTeamCapacity(array $activityValues, \Aktivita $aktivita): array {
    $minimalTeamCapacityValue = $activityValues[ExportAktivitSloupce::MINIMALNI_KAPACITA_TYMU] ?? null;
    if ((string)$minimalTeamCapacityValue === '') {
      return $this->success($aktivita->tymMinKapacita());
    }
    $minimalTeamCapacity = (int)$minimalTeamCapacityValue;
    if ($minimalTeamCapacity > 0) {
      return $this->success($minimalTeamCapacity);
    }
    if ((string)$minimalTeamCapacityValue === '0') {
      return $this->success(0);
    }
    return $this->error(sprintf("Podivná minimální kapacita týmu '%s'. Očekáváme celé kladné číslo.", $minimalTeamCapacityValue));
  }

  private function getValidatedMaximalTeamCapacity(array $activityValues, \Aktivita $aktivita): array {
    $maximalTeamCapacityValue = $activityValues[ExportAktivitSloupce::MAXIMALNI_KAPACITA_TYMU] ?? null;
    if ((string)$maximalTeamCapacityValue === '') {
      return $this->success($aktivita->tymMaxKapacita());
    }
    $maximalTeamCapacity = (int)$maximalTeamCapacityValue;
    if ($maximalTeamCapacity > 0) {
      return $this->success($maximalTeamCapacity);
    }
    if ((string)$maximalTeamCapacityValue === '0') {
      return $this->success(0);
    }
    return $this->error(sprintf("Podivná maximální kapacita týmu '%s'. Očekáváme celé kladné číslo.", $maximalTeamCapacityValue));
  }

  private function getValidatedForTeam(array $activityValues, \Aktivita $aktivita): array {
    $forTeamValue = $activityValues[ExportAktivitSloupce::JE_TYMOVA] ?? null;
    if ((string)$forTeamValue === '') {
      return $this->success(
        $aktivita->tymova()
          ? 1
          : 0
      );
    }
    $forTeam = $this->parseBoolean($forTeamValue);
    if ($forTeam !== null) {
      return $this->success(
        $forTeam
          ? 1
          : 0
      );
    }
    return $this->error(sprintf("Podivný zápis, zda je aktivita týmová: '%s'. Očekáváme pouze 1, 0, ano, ne.", $forTeamValue));
  }

  private function getValidatedStorytellersIds(array $activityValues, \Aktivita $aktivita): array {
    $storytellersString = $activityValues[ExportAktivitSloupce::VYPRAVECI] ?? null;
    if (!$storytellersString) {
      return $this->success($aktivita->getOrganizatoriIds());
    }
    $storytellersIds = [];
    $invalidStorytellersValues = [];
    $storytellersValues = array_map('trim', explode(',', $storytellersString));
    foreach ($storytellersValues as $storytellerValue) {
      // TODO
      $storyteller = $this->getStorrytellerFromValue($storytellerValue);
      if (!$storyteller) {
        $invalidStorytellersValues[] = $storytellerValue;
      } else {
        $storytellersIds[] = $storyteller->id();
      }
    }
    if ($invalidStorytellersValues) {
      $this->error(
        sprintf('Neznámí vypravěči %s', implode(',', array_map(static function (string $invalidStorytellerValue) {
          return "'$invalidStorytellerValue'";
        }, $invalidStorytellersValues)))
      );
    }
    return $this->success($storytellersIds);
  }

  private function getValidatedLongAnnotation(array $activityValues, \Aktivita $aktivita): array {
    return $this->success($activityValues[ExportAktivitSloupce::DLOUHA_ANOTACE] ?: $aktivita->popis());
  }

  private function getValidatedTags(array $activityValues, \Aktivita $aktivita): array {
    $tagsString = $activityValues[ExportAktivitSloupce::TAGY] ?? '';
    if ($tagsString === '') {
      return $this->success($aktivita->tagy());
    }
    $tagNames = [];
    $invalidTagsValues = [];
    $tagsValues = array_map('trim', explode(',', $tagsString));
    foreach ($tagsValues as $tagValue) {
      $tag = $this->getTagFromValue($tagValue);
      if (!$tag) {
        $invalidTagsValues[] = $tagValue;
      } else {
        $tagNames[] = $tag->nazev();
      }
    }
    if ($invalidTagsValues) {
      $this->error(
        sprintf('Neznámé tagy %s', implode(',', array_map(static function (string $invalidTagValue) {
          return "'$invalidTagValue'";
        }, $invalidTagsValues)))
      );
    }
    return $this->success($tagNames);
  }

  private function getTagFromValue(string $tagValue): ?\Tag {
    $tagInt = (int)$tagValue;
    if ($tagInt > 0) {
      return $this->getTagById($tagInt);
    }
    return $this->getTagByName($tagValue);
  }

  private function getTagById(int $id): ?\Tag {
    return $this->getTagsCache()['id'][$id] ?? null;
  }

  private function getTagByName(string $name): ?\Tag {
    return $this->getTagsCache()['keyFromName'][$this->toUnifiedKey($name, [])] ?? null;
  }

  private function getTagsCache(): array {
    if (!$this->tagsCache) {
      $this->tagsCache = ['id' => [], 'keyFromName' => []];
      $tags = \Tag::zVsech();
      foreach ($tags as $tag) {
        $this->tagsCache['id'][$tag->id()] = $tag;
        $keyFromName = $this->toUnifiedKey($tag->nazev(), array_keys($this->tagsCache['keyFromName']));
        $this->tagsCache['keyFromName'][$keyFromName] = $tag;
      }
    }
    return $this->tagsCache;
  }

  private function getValidatedShortAnnotation(array $activityValues, \Aktivita $aktivita): array {
    return $this->success($activityValues[ExportAktivitSloupce::KRATKA_ANOTACE] ?: $aktivita->kratkyPopis());
  }

  private function getValidatedYear(array $activityValues, \Aktivita $aktivita): array {
    $year = $aktivita->zacatek()
      ? (int)$aktivita->zacatek()->format('Y')
      : null;
    if (!$year) {
      $year = $aktivita->konec()
        ? (int)$aktivita->konec()->format('Y')
        : null;
    }
    if ($year) {
      if ($year !== $this->currentYear) {
        $this->error(
          sprintf('Aktivita %s je pro ročník %d, ale teď je ročník %d', $this->describeActivity([], $aktivita), $year, $this->currentYear)
        );
      }
      return $this->success($year);
    }
    return $this->success($this->currentYear);
  }

  private function getValidatedWithoutDiscount(array $activityValues, \Aktivita $aktivita): array {
    $withoutDiscountValue = $activityValues[ExportAktivitSloupce::BEZ_SLEV] ?? null;
    if ((string)$withoutDiscountValue === '') {
      return $this->success(
        $aktivita->bezSlevy()
          ? 1
          : 0
      );
    }
    $withoutDiscount = $this->parseBoolean($withoutDiscountValue);
    if ($withoutDiscount !== null) {
      return $this->success(
        $withoutDiscount
          ? 1
          : 0
      );
    }
    return $this->error(sprintf("Podivný zápis 'bez slevy': '%s'. Očekáváme pouze 1, 0, ano, ne.", $withoutDiscountValue));
  }

  private function parseBoolean($value): ?bool {
    if (is_bool($value)) {
      return $value;
    }
    switch (substr((string)$value, 0, 1)) {
      case '0' :
      case 'n' :
      case 'f' :
        return false;
      case '1' :
      case 'a' :
      case 'y' :
        return true;
      default :
        return null;
    }
  }

  private function getValidatedUnisexCapacity(array $activityValues, \Aktivita $aktivita): array {
    $capacityValue = $activityValues[ExportAktivitSloupce::KAPACITA_UNISEX] ?? null;
    if ((string)$capacityValue === '') {
      return $this->success($aktivita->getKapacitaUnisex());
    }
    $capacityInt = (int)$capacityValue;
    if ($capacityInt > 0) {
      return $this->success($capacityInt);
    }
    if ((string)$capacityValue === '0') {
      return $this->success(0);
    }
    return $this->error(sprintf("Podivná unisex kapacita '%s'. Očekáváme celé kladné číslo.", $capacityValue));
  }

  private function getValidatedMenCapacity(array $activityValues, \Aktivita $aktivita): array {
    $capacityValue = $activityValues[ExportAktivitSloupce::KAPACITA_MUZI] ?? null;
    if ((string)$capacityValue === '') {
      return $this->success($aktivita->getKapacitaMuzu());
    }
    $capacityInt = (int)$capacityValue;
    if ($capacityInt > 0) {
      return $this->success($capacityInt);
    }
    if ((string)$capacityValue === '0') {
      return $this->success(0);
    }
    return $this->error(sprintf("Podivná kapacita mužů '%s'. Očekáváme celé kladné číslo.", $capacityValue));
  }

  private function getValidatedWomenCapacity(array $activityValues, \Aktivita $aktivita): array {
    $capacityValue = $activityValues[ExportAktivitSloupce::KAPACITA_ZENY] ?? null;
    if ((string)$capacityValue === '') {
      return $this->success($aktivita->getKapacitaZen());
    }
    $capacityInt = (int)$capacityValue;
    if ($capacityInt > 0) {
      return $this->success($capacityInt);
    }
    if ((string)$capacityValue === '0') {
      return $this->success(0);
    }
    return $this->error(sprintf("Podivná kapacita žen '%s'. Očekáváme celé kladné číslo.", $capacityValue));
  }

  private function getValidatedPrice(array $activityValues, \Aktivita $aktivita): array {
    $priceValue = $activityValues[ExportAktivitSloupce::CENA] ?? null;
    if ((string)$priceValue === '') {
      return $this->success($aktivita->cenaZaklad());
    }
    $priceFloat = (float)$priceValue;
    if ($priceFloat !== 0.0) {
      return $this->success($priceFloat);
    }
    if ((string)$priceFloat === '0' || (string)$priceFloat === '0.0') {
      return $this->success(0.0);
    }
    return $this->error(sprintf("Podivná cena aktivity '%s'. Očekáváme číslo.", $priceValue));
  }

  private function getValidatedProgramLocationId(array $activityValues, \Aktivita $aktivita): array {
    $locationValue = $activityValues[ExportAktivitSloupce::MISTNOST] ?? null;
    if (!$locationValue) {
      return $this->success($aktivita->lokaceId());
    }
    $location = $this->getProgramLocationFromValue((string)$locationValue);
    if ($location) {
      return $this->success($location->id());
    }
    return $this->error(sprintf("Neznámá lokace '%s'", $locationValue));
  }

  private function getProgramLocationFromValue(string $programLocationValue): ?\Lokace {
    $programLocationInt = (int)$programLocationValue;
    if ($programLocationInt > 0) {
      return $this->getProgramLocationById($programLocationInt);
    }
    return $this->getProgramLocationByName($programLocationValue);
  }

  private function getProgramLocationById(int $id): ?\Lokace {
    return $this->getProgramLocationsCache()['id'][$id] ?? null;
  }

  private function getProgramLocationByName(string $name): ?\Lokace {
    return $this->getProgramLocationsCache()['keyFromName'][$this->toUnifiedKey($name, [])] ?? null;
  }

  private function getProgramLocationsCache(): array {
    if (!$this->programLocationsCache) {
      $this->programLocationsCache = ['id' => [], 'keyFromName' => []];
      $locations = \Lokace::zVsech();
      foreach ($locations as $location) {
        $this->programLocationsCache['id'][$location->id()] = $location;
        $keyFromName = $this->toUnifiedKey($location->nazev(), array_keys($this->programLocationsCache['keyFromName']));
        $this->programLocationsCache['keyFromName'][$keyFromName] = $location;
      }
    }
    return $this->programLocationsCache;
  }

  private function getValidatedBeginning(array $activityValues, \Aktivita $aktivita): array {
    $beginning = $activityValues[ExportAktivitSloupce::ZACATEK] ?? null;
    if (!$beginning) {
      $beginningObject = $aktivita->zacatek();
      $beginning = $beginningObject
        ? $beginningObject->formatDb()
        : null;
      return $this->success($beginning);
    }
    if (empty($activityValues[ExportAktivitSloupce::DEN])) {
      return $this->error(
        sprintf(
          'U aktivity %s je sice začátek (%s), ale chybí u ní den.',
          $this->describeActivity($activityValues, $aktivita),
          $activityValues[ExportAktivitSloupce::ZACATEK]
        )
      );
    }
    return $this->createDateTimeFromRangeBorder($this->currentYear, $activityValues[ExportAktivitSloupce::DEN], $activityValues[ExportAktivitSloupce::ZACATEK]);
  }

  private function getValidatedEnd(array $activityValues, \Aktivita $aktivita): array {
    $activityEnd = $activityValues[ExportAktivitSloupce::KONEC] ?? null;
    if (!$activityEnd) {
      $activityEndObject = $aktivita->konec();
      $activityEnd = $activityEndObject
        ? $activityEndObject->formatDb()
        : null;
      return $this->success($activityEnd);
    }
    if (empty($activityValues[ExportAktivitSloupce::DEN])) {
      return $this->error(
        sprintf(
          'U aktivity %s je sice konec (%s), ale chybí u ní den.',
          $this->describeActivity($activityValues, $aktivita),
          $activityValues[ExportAktivitSloupce::KONEC]
        )
      );
    }
    return $this->createDateTimeFromRangeBorder($this->currentYear, $activityValues[ExportAktivitSloupce::DEN], $activityValues[ExportAktivitSloupce::ZACATEK]);
  }

  private function describeActivity(array $activityValues, ?\Aktivita $aktivita): string {
    $id = $activityValues[ExportAktivitSloupce::ID_AKTIVITY]
      ?? $aktivita
        ? $aktivita->id()
        : '';
    $nazev = $activityValues[ExportAktivitSloupce::NAZEV]
      ?? $aktivita
        ? $aktivita->nazev()
        : '';
    if ($id && $nazev) {
      return "$nazev ($id)";
    }
    $url = $activityValues[ExportAktivitSloupce::URL]
      ?? $aktivita
        ? $aktivita->urlId()
        : '';
    if ($nazev && $url) {
      return "$nazev s URL '$url'";
    }
    if ($nazev) {
      return $nazev;
    }
    $kratkaAnotace = $activityValues[ExportAktivitSloupce::KRATKA_ANOTACE]
      ?? $aktivita
        ? $aktivita->kratkyPopis()
        : '';
    return $kratkaAnotace ?: "'bez názvu'";
  }

  private function createDateTimeFromRangeBorder(int $year, string $dayName, string $hoursAndMinutes): array {
    try {
      $date = DateTimeGamecon::denKolemZacatkuGameconuProRok($dayName, $year);
    } catch (\Exception $exception) {
      return $this->error(sprintf("Nepodařilo se vytvořit datum z roku %d, dne '%s' a času '%s'. Chybný formát datumu. Detail: %s", $year, $dayName, $hoursAndMinutes, $exception->getMessage()));
    }

    if (!preg_match('~^(?<hours>\d+)\s*:\s*(?<minutes>\d*)$~', $hoursAndMinutes, $timeMatches)) {
      return $this->error(sprintf("Nepodařilo se nastavit čas u datumu z roku %d, dne '%s' a času '%s'. Chybný formát času.", $year, $dayName, $hoursAndMinutes));
    }
    $hours = (int)$timeMatches['hours'];
    $minutes = (int)($timeMatches['minutes'] ?? 0);
    $dateTime = $date->setTime($hours, $minutes, 0, 0);
    if (!$dateTime) {
      return $this->error(sprintf("Nepodařilo se nastavit čas u datumu z roku %d, dne '%s' a času '%s'. Chybný formát času.", $year, $dayName, $hoursAndMinutes));
    }
    return $this->success($dateTime);
  }

  private function getValidatedUrl(array $activityValues, \Aktivita $aktivita): array {
    $activityUrl = $activityValues[ExportAktivitSloupce::URL] ?? null;
    if (!$activityUrl) {
      return $this->success($aktivita->urlId());
    }
    $activityUrl = strtolower(odstranDiakritiku((string)$activityUrl));
    $occupiedByActivities = dbFetchAll('SELECT id_akce, nazev_akce FROM akce_seznam WHERE url_akce = $1 AND rok = $2', [$activityUrl, $this->currentYear]);
    if ($occupiedByActivities) {
      $occupiedByActivity = reset($occupiedByActivities);
      return $this->error(sprintf("URL aktivity '%s' už je obsazena aktivitou '%s' (%d)", $activityUrl, $occupiedByActivity['nazev_akce'], $occupiedByActivity['id_akce']));
    }
    return $this->success($activityUrl);
  }

  private function getValidatedActivityName(array $activityValues, \Aktivita $aktivita): array {
    $activityNameValue = $activityValues[ExportAktivitSloupce::NAZEV] ?? null;
    if (!$activityNameValue) {
      return $this->success($aktivita->nazev());
    }
    $occupiedByActivityId = dbOneCol('SELECT id_akce FROM akce_seznam WHERE nazev_akce = $1 AND rok = $2', [$activityNameValue, $this->currentYear]);
    if ($occupiedByActivityId) {
      return $this->error(sprintf("Název aktivity '%s' už je obsazený stejnojmennou aktivitou %d", $activityNameValue, $occupiedByActivityId));
    }
    return $this->success($activityNameValue);
  }

  private function getValidatedProgramLineId(array $activityValues, \Aktivita $aktivita): array {
    $programLineValue = $activityValues[ExportAktivitSloupce::PROGRAMOVA_LINIE] ?? null;
    if (!$programLineValue) {
      return $this->success($aktivita->typId());
    }
    $programLine = $this->getProgramLineFromValue((string)$programLineValue);
    if ($programLine) {
      return $this->success($programLine->id());
    }
    return $this->error(sprintf("Neznámá programová linie '%s'", $programLineValue));
  }

  private function getProgramLineFromValue(string $programLineValue): ?\Typ {
    $programLineInt = (int)$programLineValue;
    if ($programLineInt > 0) {
      return $this->getProgramLineById($programLineInt);
    }
    return $this->getProgramLineByName($programLineValue);
  }

  private function getProgramLineById(int $id): ?\Typ {
    return $this->getProgramLinesCache()['id'][$id] ?? null;
  }

  private function getProgramLineByName(string $name): ?\Typ {
    return $this->getProgramLinesCache()['keyFromName'][$this->toUnifiedKey($name, [])] ?? null;
  }

  private function getProgramLinesCache(): array {
    if (!$this->programLinesCache) {
      $this->programLinesCache = ['id' => [], 'keyFromName' => []];
      $programLines = \Typ::zVsech();
      foreach ($programLines as $programLine) {
        $this->programLinesCache['id'][$programLine->id()] = $programLine;
        $keyFromName = $this->toUnifiedKey($programLine->nazev(), array_keys($this->programLinesCache['keyFromName']));
        $this->programLinesCache['keyFromName'][$keyFromName] = $programLine;
      }
    }
    return $this->programLinesCache;
  }

  private function toUnifiedKey(string $value, array $occupiedKeys): string {
    $unifiedKey = strtolower(preg_replace('~\s*~', '', odstranDiakritiku($value)));
    if (array_key_exists($unifiedKey, $occupiedKeys)) {
      throw new \LogicException(
        sprintf(
          "Can not create unified key from '%s' as resulting key '%s' already exists: %s",
          $value,
          $unifiedKey,
          implode(';', array_map(static function (string $occupiedKey) {
            return "'$occupiedKey'";
          }, $occupiedKeys))
        )
      );
    }
    return $unifiedKey;
  }

  private function importNewActivity(array $activityValues): array {
    return $this->success('TODO Zatím nic snovou aktivitou ' . implode(';', $activityValues));
  }
}
