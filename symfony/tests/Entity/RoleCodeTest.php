<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Role;
use App\Enum\RoleMeaning;
use App\Enum\RoleTypeEnum;
use App\Tests\AbstractDatabaseKernelTestCase;
use Doctrine\DBAL\Exception\DriverException;
use Doctrine\DBAL\Exception\NotNullConstraintViolationException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Gamecon\Role\Role as LegacyRole;
use Gamecon\Tests\Factory\RoleFactory;

/**
 * Roles are created only by SQL migrations, which never pass through the validator, and a later
 * migration finds a role by its code. A blank code makes such a migration match nothing,
 * silently.
 */
class RoleCodeTest extends AbstractDatabaseKernelTestCase
{
    public function testEveryStoredRolePassesValidation(): void
    {
        $this->assertEveryStoredEntityPassesValidation(Role::class);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function codeShapeProvider(): iterable
    {
        yield 'permanent role' => ['SEF_INFOPULTU', true];
        yield 'short' => ['DEV', true];
        yield 'yearly role' => ['GC2026_PREPINANI_NA_UZIVATELE', true];
        yield 'as long as the column' => [str_repeat('A', 36), true];
        yield 'blank' => ['', false];
        yield 'only spaces' => ['   ', false];
        yield 'lower case' => ['sef_infopultu', false];
        yield 'starts with a digit' => ['2026_PRIHLASEN', false];
        yield 'starts with an underscore' => ['_PRIHLASEN', false];
        yield 'space inside' => ['SEF INFOPULTU', false];
        yield 'hyphen' => ['SEF-INFOPULTU', false];
        yield 'dot' => ['GC2026.PRIHLASEN', false];
        yield 'diacritics' => ['ŠÉF_INFOPULTU', false];
        yield 'longer than the column' => [str_repeat('A', 37), false];
        yield 'trailing newline' => ["SEF_INFOPULTU\n", false];
    }

    /**
     * @dataProvider codeShapeProvider
     */
    public function testCodeShapeIsValidated(string $code, bool $valid): void
    {
        $violations = $this->validator()->validateProperty($this->role($code), 'kodRole');

        self::assertSame($valid, count($violations) === 0, (string) $violations);
    }

    public function testDuplicateCodeIsRefusedByValidationAndByTheDatabase(): void
    {
        $existing = (string) $this->connection()->fetchOne('SELECT kod_role FROM role_seznam WHERE id_role = :id', [
            'id' => LegacyRole::SEF_INFOPULTU,
        ]);
        self::assertSame('SEF_INFOPULTU', $existing);

        $violations = $this->validator()->validate($this->role($existing));
        self::assertCount(1, $violations);
        self::assertSame('kodRole', $violations[0]->getPropertyPath());
        self::assertSame('Role s tímto kódem již existuje', $violations[0]->getMessage());

        $this->expectException(UniqueConstraintViolationException::class);
        $this->connection()->executeStatement(
            <<<'SQL'
            INSERT INTO role_seznam (id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
            VALUES (990002, :code, 'Duplicitní kód', '', -1, 'trvala', 'ADMIN')
            SQL,
            [
                'code' => $existing,
            ],
        );
    }

    public function testDatabaseRefusesAnExplicitNullCode(): void
    {
        $this->expectException(NotNullConstraintViolationException::class);

        $this->connection()->executeStatement(<<<'SQL'
            INSERT INTO role_seznam (id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
            VALUES (990003, NULL, 'Null kód', '', -1, 'trvala', 'ADMIN')
            SQL);
    }

    /**
     * Non-strict mode, in which deploy migrations run, turns a missing value into an empty string
     * instead of an error, so the emptiness check is what has to catch it. The mode is set for this
     * one statement, so the test does not depend on how the server is configured.
     */
    public function testDatabaseRefusesARoleWithoutCodeInNonStrictMode(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessageMatches('~CONSTRAINT .CHK_kod_role_neprazdny. failed~');

        $this->connection()->executeStatement(<<<'SQL'
            SET STATEMENT sql_mode = '' FOR
            INSERT INTO role_seznam (id_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
            VALUES (990004, 'Bez kódu', '', -1, 'trvala', 'ADMIN')
            SQL);
    }

    public function testDatabaseRefusesAnEmptyCode(): void
    {
        $this->expectException(DriverException::class);
        $this->expectExceptionMessageMatches('~CONSTRAINT .CHK_kod_role_neprazdny. failed~');

        $this->connection()->executeStatement(<<<'SQL'
            INSERT INTO role_seznam (id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
            VALUES (990005, '', 'Prázdný kód', '', -1, 'trvala', 'ADMIN')
            SQL);
    }

    public function testFactoryBuildsAValidRole(): void
    {
        $role = RoleFactory::createOne()->_real();

        self::assertCount(0, $this->validator()->validate($role));
    }

    private function role(string $code): Role
    {
        $role = new Role();
        $role->setId(990001);
        $role->setKodRole($code);
        $role->setNazevRole('Test');
        $role->setPopisRole('');
        $role->setRocnikRole(-1);
        $role->setTypRole(RoleTypeEnum::PERMANENT);
        $role->setVyznamRole(RoleMeaning::ADMIN);

        return $role;
    }
}
