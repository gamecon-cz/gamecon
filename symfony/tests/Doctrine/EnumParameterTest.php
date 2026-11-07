<?php

declare(strict_types=1);

namespace App\Tests\Doctrine;

use App\Enum\ProductStateEnum;
use App\Enum\ProductTagCode;
use App\Tests\AbstractDatabaseKernelTestCase;
use App\Tests\Doctrine\Fixture\PlainEnum;
use Doctrine\DBAL\ArrayParameterType;

/**
 * A DBAL query against the real connection takes an enum as it is, without `->value`.
 */
class EnumParameterTest extends AbstractDatabaseKernelTestCase
{
    public function testRetezcovyEnumPojmenovanymParametrem(): void
    {
        self::assertSame('jidlo', $this->connection()->fetchOne(
            <<<'SQL'
            SELECT :tag
            SQL,
            [
                'tag' => ProductTagCode::JIDLO,
            ],
        ));
    }

    public function testRetezcovyEnumPozicnimParametrem(): void
    {
        self::assertSame('jidlo', $this->connection()->fetchOne(
            <<<'SQL'
            SELECT ?
            SQL,
            [ProductTagCode::JIDLO],
        ));
    }

    public function testCiselnyEnum(): void
    {
        $dotaz = <<<'SQL'
            SELECT :stav
            SQL;

        self::assertSame('1', $this->connection()->fetchOne($dotaz, [
            'stav' => ProductStateEnum::PUBLIC,
        ]));
        self::assertSame('0', $this->connection()->fetchOne($dotaz, [
            'stav' => ProductStateEnum::RETIRED,
        ]));
    }

    public function testPoleEnumuVInDotazu(): void
    {
        $kody = $this->connection()->fetchFirstColumn(
            <<<'SQL'
            SELECT code
            FROM product_tag
            WHERE code IN (:kody)
            ORDER BY code
            SQL,
            [
                'kody' => [ProductTagCode::UBYTOVANI, ProductTagCode::JIDLO],
            ],
            [
                'kody' => ArrayParameterType::STRING,
            ],
        );

        self::assertSame(['jidlo', 'ubytovani'], $kody);
    }

    public function testNullZustaneNull(): void
    {
        self::assertSame('1', $this->connection()->fetchOne(
            <<<'SQL'
            SELECT :x IS NULL
            SQL,
            [
                'x' => null,
            ],
        ));
    }

    public function testZapisSEnumem(): void
    {
        $connection = $this->connection();
        $connection->executeStatement(
            <<<'SQL'
            CREATE TEMPORARY TABLE tmp_enum_parameter (kod VARCHAR(50) NOT NULL, stav INT NOT NULL)
            SQL,
        );

        try {
            $connection->executeStatement(
                <<<'SQL'
                INSERT INTO tmp_enum_parameter (kod, stav) VALUES (:kod, :stav)
                SQL,
                [
                    'kod'  => ProductTagCode::SNIDANE,
                    'stav' => ProductStateEnum::PUBLIC,
                ],
            );

            self::assertSame(
                [
                    'snidane' => '1',
                ],
                $connection->fetchAllKeyValue(
                    <<<'SQL'
                    SELECT kod, stav FROM tmp_enum_parameter
                    SQL,
                ),
            );
        } finally {
            $connection->executeStatement(
                <<<'SQL'
                DROP TEMPORARY TABLE IF EXISTS tmp_enum_parameter
                SQL,
            );
        }
    }

    public function testEnumBezHodnotyHodiSrozumitelnouChybu(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(PlainEnum::class);

        $this->connection()->fetchOne(
            <<<'SQL'
            SELECT :x
            SQL,
            [
                'x' => PlainEnum::ONE,
            ],
        );
    }
}
