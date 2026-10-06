<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\Permission;
use App\Enum\PermissionEnum;
use App\Tests\AbstractDatabaseKernelTestCase;
use Doctrine\DBAL\Exception\DriverException;
use Gamecon\Pravo;
use Gamecon\Tests\Factory\PermissionFactory;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * A right is identified by its number, and its meaning lives in `Gamecon\Pravo` and
 * `PermissionEnum`. The code in the database is what ties them together; without a check nothing
 * notices when a constant and its row drift apart.
 */
class PermissionCodeTest extends AbstractDatabaseKernelTestCase
{
    public function testEveryPravoConstantHasARowWithItsNameAsCode(): void
    {
        $codesById = $this->connection()->fetchAllKeyValue('SELECT id_prava, kod_prava FROM r_prava_soupis');

        $wrong = [];
        foreach ($this->pravoConstants() as $name => $id) {
            if (($codesById[$id] ?? null) !== $name) {
                $wrong[] = sprintf('%s = %d, row has code %s', $name, $id, var_export($codesById[$id] ?? null, true));
            }
        }

        self::assertSame([], $wrong);
    }

    public function testEveryRowIsAPravoConstantOrAYearlyParticipationRight(): void
    {
        $constantIds = array_flip($this->pravoConstants());
        $unexplained = [];
        foreach ($this->connection()->fetchAllKeyValue('SELECT id_prava, kod_prava FROM r_prava_soupis') as $id => $code) {
            $isYearly = (int) $id < 0 && preg_match('~^GC\d{4}_(PRIHLASEN|PRITOMEN)$~', (string) $code) === 1;
            if (! isset($constantIds[(int) $id]) && ! $isYearly) {
                $unexplained[] = $id . ' ' . $code;
            }
        }

        self::assertSame([], $unexplained, 'A right in the database that no Pravo constant names (add the constant).');
    }

    public function testEveryPermissionEnumCaseIsThePravoConstantOfTheSameName(): void
    {
        $constants = $this->pravoConstants();

        foreach (PermissionEnum::cases() as $case) {
            self::assertSame($constants[$case->name] ?? null, $case->value, $case->name . ' differs from Pravo::' . $case->name);
        }
    }

    public function testEveryStoredPermissionPassesValidation(): void
    {
        $permissions = $this->entityManager()->getRepository(Permission::class)->findAll();
        self::assertNotEmpty($permissions, 'No rights loaded, the check would pass vacuously');

        $violations = [];
        foreach ($permissions as $permission) {
            foreach ($this->validator()->validate($permission) as $violation) {
                $violations[] = $permission->getId() . ': ' . $violation->getMessage();
            }
        }

        self::assertSame([], $violations);
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function codeShapeProvider(): iterable
    {
        yield 'upper case with underscores' => ['MUZE_PRETIZIT_UBYTOVANI', true];
        yield 'year and digits' => ['GC2023_PRIHLASEN', true];
        yield 'blank' => ['', false];
        yield 'only spaces' => ['   ', false];
        yield 'lower case' => ['muze_pretizit', false];
        yield 'starts with a digit' => ['2GC_PRIHLASEN', false];
        yield 'space inside' => ['MUZE PRETIZIT', false];
        yield 'diacritics' => ['MŮŽE_PŘETÍŽIT', false];
        yield 'longer than the column' => [str_repeat('A', 65), false];
        yield 'trailing newline' => ["MUZE_PRETIZIT\n", false];
    }

    /**
     * @dataProvider codeShapeProvider
     */
    public function testCodeShapeIsValidated(string $code, bool $valid): void
    {
        $permission = $this->permission($code);

        $violations = $this->validator()->validateProperty($permission, 'code');

        self::assertSame($valid, count($violations) === 0, (string) $violations);
    }

    public function testDuplicateCodeIsRefusedByValidationAndByTheDatabase(): void
    {
        $existing = $this->connection()->fetchOne('SELECT kod_prava FROM r_prava_soupis WHERE id_prava = :id', [
            'id' => Pravo::ADMINISTRACE_INFOPULT,
        ]);
        $duplicate = $this->permission((string) $existing);
        $duplicate->setId(990001);

        $violations = $this->validator()->validate($duplicate);
        self::assertCount(1, $violations);
        self::assertSame('code', $violations[0]->getPropertyPath());
        self::assertSame('Právo s tímto kódem již existuje', $violations[0]->getMessage());

        $this->expectException(DriverException::class);
        $this->connection()->executeStatement(
            "INSERT INTO r_prava_soupis (id_prava, kod_prava, jmeno_prava, popis_prava) VALUES (990002, :code, 'dup', '')",
            [
                'code' => $existing,
            ],
        );
    }

    public function testDatabaseRefusesAnExplicitNullCode(): void
    {
        $this->expectException(DriverException::class);

        $this->connection()->executeStatement("INSERT INTO r_prava_soupis (id_prava, kod_prava, jmeno_prava, popis_prava) VALUES (990005, NULL, 'null', '')");
    }

    public function testDatabaseRefusesARightWithoutCode(): void
    {
        $this->expectException(DriverException::class);

        $this->connection()->executeStatement("INSERT INTO r_prava_soupis (id_prava, jmeno_prava, popis_prava) VALUES (990003, 'bez kodu', '')");
    }

    /**
     * Non-strict mode (deploy migrations run in it) turns a missing value into an empty string
     * instead of an error, so the emptiness check is what has to catch it.
     */
    public function testDatabaseRefusesAnEmptyCode(): void
    {
        $this->expectException(DriverException::class);

        $this->connection()->executeStatement("INSERT INTO r_prava_soupis (id_prava, kod_prava, jmeno_prava, popis_prava) VALUES (990004, '', 'prazdny', '')");
    }

    public function testFactoryBuildsAValidRight(): void
    {
        $permission = PermissionFactory::createOne()->_real();

        self::assertCount(0, $this->validator()->validate($permission));
    }

    /**
     * @return array<string, int> constant name => right number
     */
    private function pravoConstants(): array
    {
        $constants = [];
        foreach ((new \ReflectionClass(Pravo::class))->getConstants(\ReflectionClassConstant::IS_PUBLIC) as $name => $value) {
            if (is_int($value)) {
                $constants[$name] = $value;
            }
        }

        return $constants;
    }

    private function permission(string $code): Permission
    {
        $permission = new Permission();
        $permission->setId(990000);
        $permission->setCode($code);
        $permission->setJmenoPrava('Test');
        $permission->setPopisPrava('');

        return $permission;
    }

    private function validator(): ValidatorInterface
    {
        return static::getContainer()->get('validator');
    }
}
