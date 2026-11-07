<?php

declare(strict_types=1);

namespace App\Tests\Doctrine;

use App\Doctrine\EnumParameterStatement;
use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Tests\Doctrine\Fixture\PlainEnum;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;
use Doctrine\DBAL\ParameterType;
use PHPUnit\Framework\TestCase;

class EnumParameterStatementTest extends TestCase
{
    private function zaznamenavajiciStatement(): Statement
    {
        return new class implements Statement {
            /**
             * @var list<array{int|string, mixed, int}>
             */
            public array $navazano = [];

            public function bindValue($param, $value, $type = ParameterType::STRING): bool
            {
                $this->navazano[] = [$param, $value, $type];

                return true;
            }

            public function bindParam($param, &$variable, $type = ParameterType::STRING, $length = null): bool
            {
                return true;
            }

            public function execute($params = null): Result
            {
                throw new \LogicException('Tenhle test nic nespouští.');
            }
        };
    }

    /**
     * @return array{EnumParameterStatement, object}
     */
    private function obaleny(): array
    {
        $zaznam = $this->zaznamenavajiciStatement();

        return [new EnumParameterStatement($zaznam), $zaznam];
    }

    public function testRetezcovyEnumSeNavazeJakoRetezec(): void
    {
        [$statement, $zaznam] = $this->obaleny();

        $statement->bindValue('tag', ProductTagCode::JIDLO, ParameterType::STRING);

        self::assertSame([['tag', 'jidlo', ParameterType::STRING]], $zaznam->navazano);
    }

    public function testCiselnyEnumSeNavazeJakoCisloAUrciSiTyp(): void
    {
        [$statement, $zaznam] = $this->obaleny();

        $statement->bindValue('stav', ProductStateEnum::PUBLIC, ParameterType::STRING);

        self::assertSame([['stav', ProductStateEnum::PUBLIC->value, ParameterType::INTEGER]], $zaznam->navazano);
    }

    /**
     * `RETIRED` has the value 0, so the conversion must not rely on truthiness.
     */
    public function testCiselnyEnumSNulouSeNepoplete(): void
    {
        [$statement, $zaznam] = $this->obaleny();

        $statement->bindValue('stav', ProductStateEnum::RETIRED, ParameterType::STRING);

        self::assertSame([['stav', 0, ParameterType::INTEGER]], $zaznam->navazano);
    }

    public function testVyslovneZadanyTypMaPrednost(): void
    {
        [$statement, $zaznam] = $this->obaleny();

        $statement->bindValue('stav', ProductStateEnum::PUBLIC, ParameterType::INTEGER);
        $statement->bindValue('tag', ProductTagCode::JIDLO, ParameterType::BINARY);

        self::assertSame(
            [['stav', 1, ParameterType::INTEGER], ['tag', 'jidlo', ParameterType::BINARY]],
            $zaznam->navazano,
        );
    }

    public function testJinaHodnotaSeNemeni(): void
    {
        [$statement, $zaznam] = $this->obaleny();

        $statement->bindValue(1, 'text', ParameterType::STRING);
        $statement->bindValue(2, 5, ParameterType::INTEGER);
        $statement->bindValue(3, null, ParameterType::NULL);

        self::assertSame(
            [[1, 'text', ParameterType::STRING], [2, 5, ParameterType::INTEGER], [3, null, ParameterType::NULL]],
            $zaznam->navazano,
        );
    }

    public function testEnumBezHodnotyHodiSrozumitelnouChybu(): void
    {
        [$statement] = $this->obaleny();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(PlainEnum::class);

        $statement->bindValue('x', PlainEnum::ONE, ParameterType::STRING);
    }
}
