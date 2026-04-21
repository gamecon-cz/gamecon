<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Activity;
use App\Entity\ActivityOrganizer;
use App\Entity\ActivityRegistration;
use App\Entity\ActivityTag;
use App\Entity\CategoryTag;
use App\Entity\Tag;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Gamecon\Cache\ProgramStaticFileGenerator;
use Gamecon\Cache\ProgramStaticFileType;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Doctrine listener, který nastavuje dirty flagy statického JSON programu
 * při změnách entit, které se promítají do veřejného programu.
 *
 * Slouží jako "defense in depth" vedle invalidací v legacy kódu —
 * cokoli, co projde Symfony/Doctrine vrstvou, musí program cache invalidovat.
 *
 * Pro entitu User invaliduje pouze pokud:
 *   - se v changesetu změnilo jméno/příjmení/login (jinak se zobrazení nemění)
 *   - uživatel je vypravěčem alespoň jedné aktivity v aktuálním ročníku
 *     (single indexovaný EXISTS dotaz; pro běžné uživatele se přeskakuje)
 */
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
final class ProgramCacheInvalidationListener
{
    /**
     * Pole sledovaných polí entity User, jejichž změna mění zobrazované
     * jméno (vypraveci[] v aktivity.json).
     */
    private const USER_DISPLAY_FIELDS = ['login', 'jmeno', 'prijmeni'];

    /**
     * Příznak, že v aktuálním flushi došlo ke změně, která vyžaduje
     * spuštění workeru. Worker spouštíme až v postFlush (po commitu),
     * aby nový proces viděl commitnutá data.
     */
    private bool $shouldStartWorker = false;

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidateForEntity($args->getObject(), $args, isUpdate: false);
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidateForEntity($args->getObject(), $args, isUpdate: true);
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->invalidateForEntity($args->getObject(), $args, isUpdate: false);
    }

    private function invalidateForEntity(
        object $entity,
        PostPersistEventArgs|PostUpdateEventArgs|PostRemoveEventArgs $args,
        bool $isUpdate,
    ): void {
        $flags = match (true) {
            $entity instanceof Activity => [
                ProgramStaticFileType::AKTIVITY,
                ProgramStaticFileType::POPISY,
                ProgramStaticFileType::OBSAZENOSTI,
            ],
            $entity instanceof ActivityOrganizer    => [ProgramStaticFileType::AKTIVITY],
            $entity instanceof ActivityTag          => [ProgramStaticFileType::AKTIVITY],
            $entity instanceof Tag                  => [ProgramStaticFileType::TAGY],
            $entity instanceof CategoryTag          => [ProgramStaticFileType::TAGY],
            $entity instanceof ActivityRegistration => [ProgramStaticFileType::OBSAZENOSTI],
            $entity instanceof User                 => $this->flagsForUser($entity, $args, $isUpdate),
            default                                 => [],
        };

        if ($flags === []) {
            return;
        }

        $generator = new ProgramStaticFileGenerator(SystemoveNastaveni::zGlobals());
        foreach ($flags as $flag) {
            // tryStartWorker: false — worker nesmíme spustit uvnitř ještě
            // necommitnuté transakce (nový proces má vlastní connection
            // a viděl by stará data). Spouštíme ho až v postFlush.
            $generator->touchDirtyFlag($flag, tryStartWorker: false);
        }
        $this->shouldStartWorker = true;
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        if (! $this->shouldStartWorker) {
            return;
        }
        $this->shouldStartWorker = false;

        // postFlush běží po commitu transakce, takže spuštěný worker
        // uvidí aktuální data v DB.
        (new ProgramStaticFileGenerator(SystemoveNastaveni::zGlobals()))->tryStartWorker();
    }

    /**
     * @return list<ProgramStaticFileType>
     */
    private function flagsForUser(
        User $user,
        PostPersistEventArgs|PostUpdateEventArgs|PostRemoveEventArgs $args,
        bool $isUpdate,
    ): array {
        if ($isUpdate) {
            $changeSet = $args->getObjectManager()->getUnitOfWork()->getEntityChangeSet($user);
            $touched = array_intersect(self::USER_DISPLAY_FIELDS, array_keys($changeSet));
            if ($touched === []) {
                return [];
            }
        }

        if (! $this->isStoryteller($user, $args)) {
            return [];
        }

        return [ProgramStaticFileType::AKTIVITY];
    }

    private function isStoryteller(
        User $user,
        PostPersistEventArgs|PostUpdateEventArgs|PostRemoveEventArgs $args,
    ): bool {
        if (! $user->getId()) {
            return false;
        }

        $rocnik = SystemoveNastaveni::zGlobals()->rocnik();
        $sql = <<<SQL
            SELECT 1 FROM akce_organizatori
                JOIN akce_seznam ON akce_seznam.id_akce = akce_organizatori.id_akce
                WHERE akce_organizatori.id_uzivatele = :idUzivatele
                  AND akce_seznam.rok = :rocnik
                LIMIT 1
            SQL;

        return (bool) $args->getObjectManager()->getConnection()->fetchOne($sql, [
            'idUzivatele' => $user->getId(),
            'rocnik'      => $rocnik,
        ]);
    }
}
