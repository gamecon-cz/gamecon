<?php declare(strict_types=1);

namespace Gamecon\Admin\Modules\Aktivity\Import\Activities;

use Gamecon\Admin\Modules\Aktivity\Export\ExportAktivitSloupce;
use Gamecon\Aktivita\Aktivita;

class ImportValuesDescriber
{
    /**
     * @var string
     */
    private $editActivityUrlSkeleton;

    public function __construct(string $editActivityUrlSkeleton) {
        $this->editActivityUrlSkeleton = $editActivityUrlSkeleton;
    }

    public function describeActivityById(int $activityId): string {
        $activity = ImportModelsFetcher::fetchActivity($activityId);
        return $this->describeActivity($activity);
    }

    public function describeActivity(Aktivita $activity): string {
        return $this->getLinkToActivity($activity);
    }

    private function getLinkToActivity(Aktivita $activity): string {
        return $this->createLinkToActivity($activity->id(), $activity->nazev());
    }

    public function describeActivityByInputValues(array $activityValues, ?Aktivita $originalActivity): string {
        return $this->describeActivityByValues(
            empty($activityValues[ExportAktivitSloupce::ID_AKTIVITY])
                ? null
                : (int)$activityValues[ExportAktivitSloupce::ID_AKTIVITY],
            $activityValues[ExportAktivitSloupce::NAZEV] ?? null,
            $activityValues[ExportAktivitSloupce::URL] ?? null,
            $activityValues[ExportAktivitSloupce::KRATKA_ANOTACE] ?? null,
            $originalActivity
        );
    }

    public function describeActivityBySqlMappedValues(array $sqlMappedValues, ?Aktivita $originalActivity): string {
        return $this->describeActivityByValues(
            $sqlMappedValues[ActivitiesImportSqlColumn::ID_AKCE] ?? null,
            $sqlMappedValues[ActivitiesImportSqlColumn::NAZEV_AKCE] ?? null,
            $sqlMappedValues[ActivitiesImportSqlColumn::URL_AKCE] ?? null,
            $sqlMappedValues[ActivitiesImportSqlColumn::POPIS_KRATKY] ?? null,
            $originalActivity
        );
    }

    private function describeActivityByValues(?int $id, ?string $nazev, ?string $url, ?string $kratkaAnotace, ?Aktivita $originalActivity): string {
        if (!$id && $originalActivity) {
            $id = $originalActivity->id();
        }
        if (!$nazev && $originalActivity) {
            $nazev = $originalActivity->nazev();
        }
        if ($nazev) {
            $nazev = (string)$nazev;
        }
        if ($nazev && $id) {
            return $this->createLinkToActivity($id, $nazev);
        }
        if (!$url && $originalActivity) {
            $url = $originalActivity->urlId();
        }
        if ($nazev && $url) {
            return "'$nazev' s URL '$url'";
        }
        if ($nazev) {
            return "'$nazev'";
        }
        if ($url) {
            return "(bez názvu) s URL '$url'";
        }
        if (!$kratkaAnotace && $originalActivity) {
            $kratkaAnotace = $originalActivity->kratkyPopis();
        }
        if ($kratkaAnotace) {
            return "(bez názvu) '$kratkaAnotace'";
        }
        return '(bez názvu)';
    }

    private function createLinkToActivity(int $id, string $name): string {
        $nameWithId = sprintf("'%s' (ID %d)", $name, $id);
        return <<<HTML
<a target="_blank" href="{$this->editActivityUrlSkeleton}{$id}">{$nameWithId}</a>
HTML
            ;
    }

    public function describeLocationById(int $locationId): string {
        $location = ImportModelsFetcher::fetchLocation($locationId);
        return sprintf('%s (%s)', $location->nazev(), $location->id());
    }

    public function describeUserById(int $userId): string {
        $user = ImportModelsFetcher::fetchUser($userId);
        return $this->describeUser($user);
    }

    public function describeUser(\Uzivatel $user): string {
        return sprintf('%s (%s)', $user->jmenoNick(), $user->id());
    }
}
