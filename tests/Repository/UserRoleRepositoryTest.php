<?php

declare(strict_types=1);

namespace Gamecon\Tests\Repository;

use App\Entity\UserRole;
use App\Repository\UserRoleRepository;
use Doctrine\DBAL\Connection;
use Gamecon\Role\Role;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * All fixtures and assertions go through the Doctrine DBAL connection (not the
 * legacy dbQuery one) so everything shares a single connection. The repository
 * under test uses the Doctrine connection too; mixing it with the legacy
 * connection's open transaction would deadlock on the uzivatele_role rows.
 */
class UserRoleRepositoryTest extends AbstractTestDb
{
    // Legacy per-test transaction wrapping would isolate fixtures from the
    // Doctrine connection. Reset the DB after the class instead.
    protected static function keepTestClassDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    protected static function resetDbAfterClass(): bool
    {
        return true;
    }

    private function connection(): Connection
    {
        return $this->getContainer()->get('doctrine.orm.entity_manager')->getConnection();
    }

    private function repository(): UserRoleRepository
    {
        $repository = $this->getContainer()
            ->get('doctrine.orm.entity_manager')
            ->getRepository(UserRole::class);
        \assert($repository instanceof UserRoleRepository);

        return $repository;
    }

    private function vytvorUzivatele(string $suffix): int
    {
        $connection = $this->connection();
        $connection->executeStatement(
            <<<'SQL'
            INSERT INTO uzivatele_hodnoty
                SET login_uzivatele = :login,
                    email1_uzivatele = :email,
                    jmeno_uzivatele = 'Test',
                    prijmeni_uzivatele = 'UserRoleRepository'
            SQL,
            [
                'login' => 'test_switch_user_' . $suffix,
                'email' => 'test.switch.user.' . $suffix . '@example.org',
            ],
        );

        return (int) $connection->lastInsertId();
    }

    private function pridelRoli(int $idUzivatele, int $idRole): void
    {
        $this->connection()->executeStatement(
            'INSERT INTO uzivatele_role (id_uzivatele, id_role, posadil) VALUES (:user, :role, :user)',
            [
                'user' => $idUzivatele,
                'role' => $idRole,
            ],
        );
    }

    private function maRoli(int $idUzivatele, int $idRole): bool
    {
        return (bool) $this->connection()->fetchOne(
            'SELECT 1 FROM uzivatele_role WHERE id_uzivatele = :user AND id_role = :role',
            [
                'user' => $idUzivatele,
                'role' => $idRole,
            ],
        );
    }

    public function testGrantSwitchUserRoleToDevs(): void
    {
        $idSwitchUserRole = Role::LETOSNI_PREPINANI_UZIVATELE(ROCNIK);

        $dev = $this->vytvorUzivatele('dev');
        $this->pridelRoli($dev, Role::DEV);
        self::assertFalse(
            $this->maRoli($dev, $idSwitchUserRole),
            'Dev user does not have the switch-user role yet',
        );

        $nonDev = $this->vytvorUzivatele('non_dev');
        $this->pridelRoli($nonDev, Role::ORGANIZATOR);

        $granted = $this->repository()->grantSwitchUserRoleToDevs(ROCNIK);

        self::assertSame(1, $granted, 'Exactly one (Dev) user gets the role');
        self::assertTrue(
            $this->maRoli($dev, $idSwitchUserRole),
            'Dev user received the yearly switch-user role',
        );
        self::assertFalse(
            $this->maRoli($nonDev, $idSwitchUserRole),
            'Non-Dev user does not receive the switch-user role',
        );
    }

    public function testGrantSwitchUserRoleToDevsIsIdempotent(): void
    {
        $idSwitchUserRole = Role::LETOSNI_PREPINANI_UZIVATELE(ROCNIK);

        $dev = $this->vytvorUzivatele('dev_idempotence');
        $this->pridelRoli($dev, Role::DEV);

        $firstRun = $this->repository()->grantSwitchUserRoleToDevs(ROCNIK);
        $secondRun = $this->repository()->grantSwitchUserRoleToDevs(ROCNIK);

        self::assertSame(1, $firstRun, 'First run grants the role');
        self::assertSame(0, $secondRun, 'Second run grants nothing new');

        $count = (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM uzivatele_role WHERE id_uzivatele = :user AND id_role = :role',
            [
                'user' => $dev,
                'role' => $idSwitchUserRole,
            ],
        );
        self::assertSame(1, $count, 'The role is assigned exactly once');
    }
}
