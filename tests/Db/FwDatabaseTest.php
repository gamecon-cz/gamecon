<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

use Gamecon\Uzivatel\Enum\TypUpominky;
use Gamecon\Uzivatel\Enum\UcastNaGc;
use Gamecon\Uzivatel\ZpusobZobrazeniNaWebu;
use PHPUnit\Framework\TestCase;

class FwDatabaseTest extends TestCase
{
    protected function setUp(): void
    {
        dbQuery('DROP TEMPORARY TABLE IF EXISTS tmp_fw_database_enum');
        dbQuery(<<<'SQL'
            CREATE TEMPORARY TABLE tmp_fw_database_enum (
                id    INT         NOT NULL,
                code  VARCHAR(20) NOT NULL,
                cislo INT         NOT NULL,
                PRIMARY KEY (id)
            )
            SQL);
        dbQuery(<<<'SQL'
            INSERT INTO tmp_fw_database_enum (id, code, cislo)
            VALUES (1, 'tyden', 0), (2, 'mesic', 1), (3, 'rucni', 2)
            SQL);
    }

    protected function tearDown(): void
    {
        dbQuery('DROP TEMPORARY TABLE IF EXISTS tmp_fw_database_enum');
    }

    public function testLegacyConnectionHoldsAMegabyteOfGroupConcat(): void
    {
        self::assertSame('1048576', dbOneCol('SELECT @@group_concat_max_len'));
    }

    public function testLegacyConnectionComparesStringsInCzech(): void
    {
        self::assertSame('utf8mb4_czech_ci', dbOneCol('SELECT @@collation_connection'));
    }

    public function testEmptyArrayParameterEscapedAsNull()
    {
        self::assertSame('NULL', dbQv([]));
    }

    public function testStringEnumIsQuotedAsItsValue(): void
    {
        self::assertSame('"tyden"', dbQv(TypUpominky::TYDEN));
    }

    public function testNumericEnumIsANumberNotAString(): void
    {
        self::assertSame('1', dbQv(ZpusobZobrazeniNaWebu::JMENO_A_PRIJMENI));
    }

    public function testNumericEnumWithZeroValueIsZero(): void
    {
        self::assertSame('0', dbQv(ZpusobZobrazeniNaWebu::POUZE_PREZDIVKA));
    }

    public function testEnumWithoutValueIsRejectedWithItsClassName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(UcastNaGc::class);

        dbQv(UcastNaGc::PRITOMEN);
    }

    public function testArrayOfEnumsBecomesTheListOfTheirValues(): void
    {
        self::assertSame(
            '0,2',
            dbQa([ZpusobZobrazeniNaWebu::POUZE_PREZDIVKA, ZpusobZobrazeniNaWebu::JMENO_S_PREZDIVKOU_A_PRIJMENI]),
        );
        self::assertSame('"tyden","mesic"', dbQa([TypUpominky::TYDEN, TypUpominky::MESIC]));
        self::assertSame('1', dbQv([ZpusobZobrazeniNaWebu::JMENO_A_PRIJMENI]));
    }

    public function testRawEscapingTreatsEnumsLikeQuoting(): void
    {
        self::assertSame('1', dbQRaw(ZpusobZobrazeniNaWebu::JMENO_A_PRIJMENI));
        self::assertSame('0', dbQRaw(ZpusobZobrazeniNaWebu::POUZE_PREZDIVKA));
        self::assertSame('tyden', dbQRaw(TypUpominky::TYDEN));
    }

    public function testRawEscapingRejectsEnumWithoutValue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(UcastNaGc::class);

        dbQRaw(UcastNaGc::NEDORAZIL);
    }

    public function testNumericEnumFillsBothKindsOfPlaceholders(): void
    {
        self::assertSame(
            'SELECT 1',
            dbQueryAssemble('SELECT $0', [ZpusobZobrazeniNaWebu::JMENO_A_PRIJMENI]),
        );
        self::assertSame(
            'SELECT 1',
            dbQueryAssemble('SELECT ?', [ZpusobZobrazeniNaWebu::JMENO_A_PRIJMENI]),
        );
        self::assertSame(
            'SELECT 1',
            dbQueryAssemble('SELECT $1', [
                1 => ZpusobZobrazeniNaWebu::JMENO_A_PRIJMENI,
            ]),
        );
    }

    public function testNumericEnumWorksAsLimit(): void
    {
        $limit = ZpusobZobrazeniNaWebu::JMENO_S_PREZDIVKOU_A_PRIJMENI;

        self::assertSame(['1', '2'], dbFetchColumn('SELECT id FROM tmp_fw_database_enum ORDER BY id LIMIT $0', [$limit]));
        self::assertSame(['1', '2'], dbFetchColumn('SELECT id FROM tmp_fw_database_enum ORDER BY id LIMIT ?', [$limit]));
        self::assertSame([], dbFetchColumn('SELECT id FROM tmp_fw_database_enum LIMIT $0', [ZpusobZobrazeniNaWebu::POUZE_PREZDIVKA]));
    }

    public function testReadHelpersAcceptEnums(): void
    {
        $limitOne = ZpusobZobrazeniNaWebu::JMENO_A_PRIJMENI;

        self::assertSame(
            'mesic',
            dbOneCol('SELECT code FROM tmp_fw_database_enum WHERE code = $0 LIMIT $1', [TypUpominky::MESIC, $limitOne]),
        );
        self::assertSame(
            [
                'id'    => '2',
                'code'  => 'mesic',
                'cislo' => '1',
            ],
            dbOneLine('SELECT * FROM tmp_fw_database_enum WHERE cislo = $0 LIMIT $1', [$limitOne, $limitOne]),
        );
        self::assertSame(
            [[
                'id' => '1',
            ], [
                'id' => '3',
            ]],
            dbFetchAll(
                'SELECT id FROM tmp_fw_database_enum WHERE cislo IN ($0) ORDER BY id',
                [[ZpusobZobrazeniNaWebu::POUZE_PREZDIVKA, ZpusobZobrazeniNaWebu::JMENO_S_PREZDIVKOU_A_PRIJMENI]],
            ),
        );
        self::assertSame(
            [
                'mesic' => '1',
                'rucni' => '2',
            ],
            dbFetchPairs('SELECT code, cislo FROM tmp_fw_database_enum WHERE cislo >= $0 ORDER BY id', [$limitOne]),
        );
    }

    public function testInsertAndUpdateStoreEnumValues(): void
    {
        dbInsert('tmp_fw_database_enum', [
            'id'    => 4,
            'code'  => TypUpominky::VLASTNI,
            'cislo' => ZpusobZobrazeniNaWebu::JMENO_A_PRIJMENI,
        ]);
        self::assertSame(
            [
                'code'  => 'vlastni',
                'cislo' => '1',
            ],
            dbOneLine('SELECT code, cislo FROM tmp_fw_database_enum WHERE id = 4'),
        );

        dbUpdate(
            'tmp_fw_database_enum',
            [
                'code'  => TypUpominky::RUCNI,
                'cislo' => ZpusobZobrazeniNaWebu::POUZE_PREZDIVKA,
            ],
            [
                'id' => 2,
            ],
        );
        self::assertSame(
            [
                'code'  => 'rucni',
                'cislo' => '0',
            ],
            dbOneLine('SELECT code, cislo FROM tmp_fw_database_enum WHERE id = 2'),
        );
    }

    public function testInsertUpdateStoresEnumValuesForNewAndExistingRow(): void
    {
        dbInsertUpdate('tmp_fw_database_enum', [
            'id'    => 1,
            'code'  => TypUpominky::MESIC,
            'cislo' => ZpusobZobrazeniNaWebu::JMENO_S_PREZDIVKOU_A_PRIJMENI,
        ]);
        dbInsertUpdate('tmp_fw_database_enum', [
            'id'    => 5,
            'code'  => TypUpominky::VLASTNI,
            'cislo' => ZpusobZobrazeniNaWebu::JMENO_A_PRIJMENI,
        ]);

        self::assertSame(
            [
                '1' => 'mesic',
                '5' => 'vlastni',
            ],
            dbFetchPairs('SELECT id, code FROM tmp_fw_database_enum WHERE id IN (1, 5) ORDER BY id'),
        );
        self::assertSame(
            ['2', '1'],
            dbFetchColumn('SELECT cislo FROM tmp_fw_database_enum WHERE id IN (1, 5) ORDER BY id'),
        );
    }

    public function testDeleteAndRecordExistsAcceptEnums(): void
    {
        self::assertTrue(dbRecordExists('tmp_fw_database_enum', [
            'code' => TypUpominky::TYDEN,
        ]));
        self::assertTrue(dbRecordExists('tmp_fw_database_enum', [
            'cislo' => ZpusobZobrazeniNaWebu::JMENO_A_PRIJMENI,
        ]));
        self::assertFalse(dbRecordExists('tmp_fw_database_enum', [
            'code' => TypUpominky::VLASTNI,
        ]));

        dbDelete('tmp_fw_database_enum', [
            'code' => TypUpominky::TYDEN,
        ]);
        dbDelete('tmp_fw_database_enum', [
            'cislo' => ZpusobZobrazeniNaWebu::JMENO_S_PREZDIVKOU_A_PRIJMENI,
        ]);

        self::assertSame(['2'], dbFetchColumn('SELECT id FROM tmp_fw_database_enum ORDER BY id'));
    }
}
