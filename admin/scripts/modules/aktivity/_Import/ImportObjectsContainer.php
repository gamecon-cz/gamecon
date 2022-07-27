<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import;

use Gamecon\Aktivita\TypAktivity;
use Gamecon\Aktivita\StavAktivity;

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

    /**
     * @var ImportUsersCache
     */
    private $importUserCache;

    public function __construct(ImportUsersCache $importUserCache) {
        $this->importUserCache = $importUserCache;
    }

    public function getProgramLineFromValue(string $programLineValue): ?TypAktivity {
        $programLineInt = (int)$programLineValue;
        if ($programLineInt > 0) {
            return $this->getProgramLineById($programLineInt);
        }
        return $this->getProgramLineByName($programLineValue);
    }

    private function getProgramLineById(int $id): ?TypAktivity {
        return $this->getProgramLinesCache()['id'][$id] ?? null;
    }

    private function getProgramLineByName(string $name): ?TypAktivity {
        return $this->getProgramLinesCache()['keyFromName'][ImportKeyUnifier::toUnifiedKey($name, [])]
            ?? $this->getProgramLinesCache()['keyFromSingleName'][ImportKeyUnifier::toUnifiedKey($name, [])]
            ?? $this->getProgramLinesCache()['keyFromUrl'][ImportKeyUnifier::toUnifiedKey($name, [])]
            ?? null;
    }

    private function getProgramLinesCache(): array {
        if (!$this->programLinesCache) {
            $this->programLinesCache = ['id' => [], 'keyFromName' => [], 'keyFromSingleName' => [], 'keyFromUrl' => []];
            foreach (TypAktivity::zVsech() as $programLine) {
                $this->programLinesCache['id'][$programLine->id()] = $programLine;
                $keyFromName = ImportKeyUnifier::toUnifiedKey($programLine->nazev(), array_keys($this->programLinesCache['keyFromName']));
                $this->programLinesCache['keyFromName'][$keyFromName] = $programLine;
                $keyFromSingleName = ImportKeyUnifier::toUnifiedKey($programLine->nazevJednotnehoCisla(), array_keys($this->programLinesCache['keyFromSingleName']));
                $this->programLinesCache['keyFromSingleName'][$keyFromSingleName] = $programLine;
                $keyFromUrl = ImportKeyUnifier::toUnifiedKey($programLine->url(), array_keys($this->programLinesCache['keyFromUrl']));
                $this->programLinesCache['keyFromUrl'][$keyFromUrl] = $programLine;
            }
        }
        return $this->programLinesCache;
    }

    public function getStateFromValue(string $StateValue): ?StavAktivity {
        $StateInt = (int)$StateValue;
        if ($StateInt > 0) {
            return $this->getStateById($StateInt);
        }
        return $this->getStateByName($StateValue);
    }

    private function getStateById(int $id): ?StavAktivity {
        return $this->getStatesCache()['id'][$id] ?? null;
    }

    private function getStateByName(string $name): ?StavAktivity {
        return $this->getStatesCache()['keyFromName'][ImportKeyUnifier::toUnifiedKey(mb_substr($name, 0, 3, 'UTF-8'), [])] ?? null;
    }

    private function getStatesCache(): array {
        if (!$this->statesCache) {
            $this->statesCache = ['id' => [], 'keyFromName' => []];
            foreach (StavAktivity::zVsech() as $stav) {
                $this->statesCache['id'][$stav->id()] = $stav;
                $keyFromName = ImportKeyUnifier::toUnifiedKey(mb_substr($stav->nazev(), 0, 3, 'UTF-8'), array_keys($this->statesCache['keyFromName']));
                $this->statesCache['keyFromName'][$keyFromName] = $stav;
            }
        }
        return $this->statesCache;
    }

    public function getUserFromValue(string $userValue): ?\Uzivatel {
        $userInt = (int)$userValue;
        if ($userInt > 0) {
            return $this->importUserCache->getUserById($userInt);
        }
        return $this->importUserCache->getUserByEmail($userValue)
            ?? $this->importUserCache->getUserByNameWithNick($userValue)
            ?? $this->importUserCache->getUserByName($userValue)
            ?? $this->importUserCache->getUserByNick($userValue);
    }

    public function getTagFromValue(string $tagValue): ?\Tag {
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
        return $this->getTagsCache()['keyFromName'][ImportKeyUnifier::toUnifiedKey($name, [])] ?? null;
    }

    private function getTagsCache(): array {
        if (!$this->tagsCache) {
            $this->tagsCache = ['id' => [], 'keyFromName' => []];
            foreach (\Tag::zVsech() as $tag) {
                $this->tagsCache['id'][$tag->id()] = $tag;
                $keyFromName = ImportKeyUnifier::toUnifiedKey($tag->nazev(), array_keys($this->tagsCache['keyFromName']));
                $this->tagsCache['keyFromName'][$keyFromName] = $tag;
            }
        }
        return $this->tagsCache;
    }

    public function getLocationFromValue(string $locationValue): ?\Lokace {
        $locationInt = (int)$locationValue;
        if ($locationInt > 0) {
            return $this->getProgramLocationById($locationInt);
        }
        return $this->getProgramLocationByName($locationValue);
    }

    private function getProgramLocationById(int $id): ?\Lokace {
        return $this->getProgramLocationsCache()['id'][$id] ?? null;
    }

    private function getProgramLocationByName(string $name): ?\Lokace {
        $unifiedKey = ImportKeyUnifier::toUnifiedKey($name, [], ImportKeyUnifier::UNIFY_UP_TO_NUMBERS_AND_LETTERS);
        $programLocation = $this->getProgramLocationsCache()['keyFromName'][$unifiedKey] ?? null;
        if ($programLocation) {
            return $programLocation;
        }
        $keysFromFullNames = array_keys($this->getProgramLocationsCache()['keyFromName']);
        $matchingKeysFromFullNames = array_filter($keysFromFullNames, static function (string $keyFromFullName) use ($unifiedKey) {
            return strpos($keyFromFullName, $unifiedKey) === 0; // given location name is a beginning of a location full name
        });
        if (count($matchingKeysFromFullNames) === 1) { // given name was a beginning of a single location name
            $unifiedKeyFromFullName = reset($matchingKeysFromFullNames);
            return $this->getProgramLocationsCache()['keyFromName'][$unifiedKeyFromFullName];
        }
        return null; // no or too many locations matched given name part
    }

    private function getProgramLocationsCache(): array {
        if (!$this->programLocationsCache) {
            $this->programLocationsCache = ['id' => [], 'keyFromName' => []];
            foreach (\Lokace::zVsech() as $lokace) {
                $this->programLocationsCache['id'][$lokace->id()] = $lokace;
                $keyFromName = ImportKeyUnifier::toUnifiedKey($lokace->nazev(), array_keys($this->programLocationsCache['keyFromName']), ImportKeyUnifier::UNIFY_UP_TO_NUMBERS_AND_LETTERS);
                $this->programLocationsCache['keyFromName'][$keyFromName] = $lokace;
            }
        }
        return $this->programLocationsCache;
    }
}
