<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UserRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UserRole>
 *
 * @method UserRole|null find($id, $lockMode = null, $lockVersion = null)
 * @method UserRole|null findOneBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null)
 * @method UserRole[]    findAll()
 * @method UserRole[]    findBy(array<string, mixed> $criteria, array<string, string>|null $orderBy = null, $limit = null, $offset = null)
 */
class UserRoleRepository extends ServiceEntityRepository
{
    /**
     * Význam (significance code) of the permanent "developer" role.
     *
     * @see \Gamecon\Role\Role::VYZNAM_DEV
     */
    private const ROLE_SIGNIFICANCE_DEV = 'DEV';

    /**
     * Význam of the yearly "may switch to any user in admin" role.
     *
     * @see \Gamecon\Role\Role::VYZNAM_PREPINANI_UZIVATELE
     */
    private const ROLE_SIGNIFICANCE_SWITCH_USER = 'PREPINANI_NA_UZIVATELE';

    /**
     * Id of the user representing the system itself (used as "granted by").
     *
     * @see \Uzivatel::SYSTEM
     */
    private const SYSTEM_USER_ID = 1;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UserRole::class);
    }

    /**
     * Grant the yearly "switch to any user" role to every user who holds the
     * permanent Dev role.
     *
     * Used after a fresh production DB is restored into a preview environment:
     * the switch-user role is yearly and resets each ročník, so a freshly
     * restored DB never carries it. Granting it to all developers lets them
     * impersonate any user on the preview right away.
     *
     * Idempotent — re-running skips users who already have the role (the
     * uzivatele_role UNIQUE(id_uzivatele, id_role) constraint backs the
     * INSERT IGNORE). Returns the number of newly granted assignments.
     */
    public function grantSwitchUserRoleToDevs(int $year): int
    {
        $connection = $this->getEntityManager()->getConnection();

        // INSERT ... SELECT copies the user list straight from the DB; no need
        // to hydrate entities just to re-insert them. INSERT IGNORE makes the
        // statement idempotent against the unique (user, role) constraint.
        return (int) $connection->executeStatement(
            <<<'SQL'
            INSERT IGNORE INTO uzivatele_role (id_uzivatele, id_role, posazen, posadil)
            SELECT
                dev_role.id_uzivatele,
                switch_user_role.id_role,
                NOW(),
                :systemUserId
            FROM uzivatele_role AS dev_role
            JOIN role_seznam AS dev_role_def
                ON dev_role_def.id_role = dev_role.id_role
                AND dev_role_def.vyznam_role = :devSignificance
            JOIN role_seznam AS switch_user_role
                ON switch_user_role.vyznam_role = :switchUserSignificance
                AND switch_user_role.rocnik_role = :year
            SQL,
            [
                'systemUserId'           => self::SYSTEM_USER_ID,
                'devSignificance'        => self::ROLE_SIGNIFICANCE_DEV,
                'switchUserSignificance' => self::ROLE_SIGNIFICANCE_SWITCH_USER,
                'year'                   => $year,
            ],
        );
    }

    public function save(UserRole $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UserRole $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}
