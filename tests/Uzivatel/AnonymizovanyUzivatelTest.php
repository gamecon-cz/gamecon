<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\AnonymizovanyUzivatel;

class AnonymizovanyUzivatelTest extends AbstractTestDb
{
    private const int ID_ANONYMIZOVANEHO = 99541;
    private const int ID_JINEHO = 99542;

    protected static bool $disableStrictTransTables = true;

    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return [
            ...parent::getSetUpBeforeClassInitQueries(),
            sprintf(
                <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES
    (%1$d, 'test-anonymizace', 'Test', 'Anonymizace', 'test-anonymizace@example.invalid'),
    (%2$d, 'test-anonymizace-jiny', 'Test', 'Jiný', 'test-anonymizace-jiny@example.invalid')
SQL,
                self::ID_ANONYMIZOVANEHO,
                self::ID_JINEHO,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO medailonky(id_uzivatele, o_sobe, drd)
VALUES (%1$d, 'Zveřejněný text', ''), (%2$d, 'Text jiného', '')
SQL,
                self::ID_ANONYMIZOVANEHO,
                self::ID_JINEHO,
            ),
            sprintf(
                <<<'SQL'
INSERT INTO medailonky_ke_schvaleni(id_uzivatele, o_sobe, schvaleno)
VALUES (%1$d, 'Volejte na 777 123 456', NULL), (%2$d, 'Text jiného', NOW())
SQL,
                self::ID_ANONYMIZOVANEHO,
                self::ID_JINEHO,
            ),
        ];
    }

    /**
     * Text čekající na schválení by jedno kliknutí ve schvalování medailonků vrátilo na web.
     *
     * @test
     */
    public function smazeTextMedailonkuKeSchvaleni(): void
    {
        AnonymizovanyUzivatel::anonymizujUzivatele(\Uzivatel::zId(self::ID_ANONYMIZOVANEHO));

        self::assertSame('', dbOneCol(<<<'SQL'
SELECT o_sobe FROM medailonky WHERE id_uzivatele = $0
SQL, [
            0 => self::ID_ANONYMIZOVANEHO,
        ]));
        self::assertSame(
            [self::ID_JINEHO],
            array_map('intval', dbFetchColumn(<<<'SQL'
SELECT id_uzivatele FROM medailonky_ke_schvaleni WHERE id_uzivatele IN ($0)
SQL, [
                0 => [self::ID_ANONYMIZOVANEHO, self::ID_JINEHO],
            ])),
        );
    }
}
