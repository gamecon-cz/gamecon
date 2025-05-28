<?php
declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import\Activities;

use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Aktivita\Lokace;

class ImportObjectsContainer
{

    /**
     * @var array|TypAktivity[][]
     */
    private $programLinesCache;
    /**
     * @var array|TypAktivity[][]
     */
    private $programLocationsCache;
    /**
     * @var array|\Tag[][]
     */
    private $tagsCache;
    /**
     * @var array|StavAktivity[][]
     */
    private $statesCache;

    public function __construct(private readonly ImportUsersCache $importUserCache)
    {
    }

    public function getProgramLineFromValue(string $programLineValue): ?TypAktivity
    {
        $programLineInt = ImportKeyUnifier::parseId($programLineValue);
        if ($programLineInt !== null) {
            return $this->getProgramLineById($programLineInt);
        }

        return $this->getProgramLineByName($programLineValue);
    }

    private function getProgramLineById(int $id): ?TypAktivity
    {
        return $this->getProgramLinesCache()['id'][$id] ?? null;
    }

    private function getProgramLineByName(string $name): ?TypAktivity
    {
        return $this->getProgramLinesCache()['keyFromName'][ImportKeyUnifier::toUnifiedKey($name, [])]
               ?? $this->getProgramLinesCache()['keyFromSingleName'][ImportKeyUnifier::toUnifiedKey($name, [])]
                  ?? $this->getProgramLinesCache()['keyFromUrl'][ImportKeyUnifier::toUnifiedKey($name, [])]
                     ?? null;
    }

    private function getProgramLinesCache(): array
    {
        if (!$this->programLinesCache) {
            $this->programLinesCache = ['id' => [], 'keyFromName' => [], 'keyFromSingleName' => [], 'keyFromUrl' => []];
            foreach (TypAktivity::zVsech() as $programLine) {
                $this->programLinesCache['id'][$programLine->id()] = $programLine;

                $keyFromName                                          = ImportKeyUnifier::toUnifiedKey($programLine->nazev(), array_keys($this->programLinesCache['keyFromName']));
                $this->programLinesCache['keyFromName'][$keyFromName] = $programLine;

                $keyFromSingleName                                                = ImportKeyUnifier::toUnifiedKey($programLine->nazevJednotnehoCisla(), array_keys($this->programLinesCache['keyFromSingleName']));
                $this->programLinesCache['keyFromSingleName'][$keyFromSingleName] = $programLine;

                $keyFromUrl                                         = ImportKeyUnifier::toUnifiedKey($programLine->url(), array_keys($this->programLinesCache['keyFromUrl']));
                $this->programLinesCache['keyFromUrl'][$keyFromUrl] = $programLine;
            }
        }

        return $this->programLinesCache;
    }

    public function getStateFromValue(string $stateValue): ?StavAktivity
    {
        $stateId = ImportKeyUnifier::parseId($stateValue);
        if ($stateId !== null) {
            return $this->getStateById($stateId);
        }

        return $this->getStateByName($stateValue);
    }

    private function getStateById(int $id): ?StavAktivity
    {
        return $this->getStatesCache()['id'][$id] ?? null;
    }

    private function getStateByName(string $name): ?StavAktivity
    {
        return $this->getStatesCache()['keyFromName'][ImportKeyUnifier::toUnifiedKey(mb_substr($name, 0, 3, 'UTF-8'), [])] ?? null;
    }

    private function getStatesCache(): array
    {
        if (!$this->statesCache) {
            $this->statesCache = ['id' => [], 'keyFromName' => []];
            foreach (StavAktivity::zVsech() as $stav) {
                $this->statesCache['id'][$stav->id()]           = $stav;
                $keyFromName                                    = ImportKeyUnifier::toUnifiedKey(mb_substr($stav->nazev(), 0, 3, 'UTF-8'), array_keys($this->statesCache['keyFromName']));
                $this->statesCache['keyFromName'][$keyFromName] = $stav;
            }
        }

        return $this->statesCache;
    }

    public function getUserFromValue(string $userValue): ?\Uzivatel
    {
        $userId = ImportKeyUnifier::parseId($userValue);
        if ($userId !== null) {
            return $this->importUserCache->getUserById($userId);
        }

        return $this->importUserCache->getUserByEmail($userValue)
               ?? $this->importUserCache->getUserByNameWithNick($userValue)
                  ?? $this->importUserCache->getUserByName($userValue)
                     ?? $this->importUserCache->getUserByNick($userValue);
    }

    public function getTagFromValue(string $tagValue): ?\Tag
    {
        /* intentionally no tag by ID, because there are tags named like "2400", also there is no need to import tags by IDs */
        return $this->getTagByName($tagValue);
    }

    private function getTagByName(string $name): ?\Tag
    {
        return $this->getTagsCache()['keyFromName'][ImportKeyUnifier::toUnifiedKey($name, [])] ?? null;
    }

    private function getTagsCache(): array
    {
        if (!$this->tagsCache) {
            $this->tagsCache = ['id' => [], 'keyFromName' => []];
            foreach (\Tag::zVsech() as $tag) {
                $this->tagsCache['id'][$tag->id()]            = $tag;
                $keyFromName                                  = ImportKeyUnifier::toUnifiedKey($tag->nazev(), array_keys($this->tagsCache['keyFromName']));
                $this->tagsCache['keyFromName'][$keyFromName] = $tag;
            }
        }

        return $this->tagsCache;
    }

    /**
     * @return array<string, Lokace|null>
     */
    public function getLocationsFromValue(string $locationsString): array
    {
        $locationValues = array_map('trim', explode(';', $locationsString));
        $locations      = [];
        foreach ($locationValues as $locationValue) {
            $locations[$locationValue] = $this->getLocationFromValue($locationValue);
        }

        return $locations;
    }


    private function getLocationFromValue(string $locationValue): ?Lokace
    {
        $locationId = ImportKeyUnifier::parseId($locationValue);
        if ($locationId !== null) {
            return $this->getProgramLocationById($locationId);
        }

        return $this->getProgramLocationByName($locationValue);
    }

    private function getProgramLocationById(int $id): ?Lokace
    {
        return $this->getProgramLocationsCache()['id'][$id] ?? null;
    }

    private function getProgramLocationByName(string $name): ?Lokace
    {
        $unifiedKey      = ImportKeyUnifier::toUnifiedKey($name, [], ImportKeyUnifier::UNIFY_UP_TO_NUMBERS_AND_LETTERS);
        $programLocation = $this->getProgramLocationsCache()['keyFromName'][$unifiedKey] ?? null;
        if ($programLocation) {
            return $programLocation;
        }
        $keysFromFullNames         = array_keys($this->getProgramLocationsCache()['keyFromName']);
        $matchingKeysFromFullNames = array_filter($keysFromFullNames, static function (
            string $keyFromFullName,
        ) use
        (
            $unifiedKey,
        ) {
            return str_starts_with($keyFromFullName, $unifiedKey); // given location name is a beginning of a location full name
        });
        if (count($matchingKeysFromFullNames) === 1) { // given name was a beginning of a single location name
            $unifiedKeyFromFullName = reset($matchingKeysFromFullNames);

            return $this->getProgramLocationsCache()['keyFromName'][$unifiedKeyFromFullName];
        }

        return null; // no or too many locations matched given name part
    }

    private function getProgramLocationsCache(): array
    {
        if (!$this->programLocationsCache) {
            $this->programLocationsCache = ['id' => [], 'keyFromName' => []];
            foreach (Lokace::zVsech() as $lokace) {
                $this->programLocationsCache['id'][$lokace->id()]         = $lokace;
                $keyFromName                                              = ImportKeyUnifier::toUnifiedKey($lokace->nazev(), array_keys($this->programLocationsCache['keyFromName']), ImportKeyUnifier::UNIFY_UP_TO_NUMBERS_AND_LETTERS);
                $this->programLocationsCache['keyFromName'][$keyFromName] = $lokace;
            }
        }

        return $this->programLocationsCache;
    }
}
