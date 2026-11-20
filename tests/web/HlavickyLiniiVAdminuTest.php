<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

/**
 * Kdo linii vede („šéf RPG“) se v adminu (Web → Hlavičky linií) zadává česky i anglicky, přes skutečnou administraci.
 */
class HlavickyLiniiVAdminuTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    /**
     * @var list<string>
     */
    private array $kUklidu = [];

    // admin zapisuje přes Apache, test čte vlastním spojením; v transakci by viděl jen stav ze svého prvního čtení
    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        return false;
    }

    /**
     * @test
     */
    public function anglickaSekceSeUkladaVedleCeske(): void
    {
        $idTypu = (int) dbFetchSingle(<<<'SQL'
SELECT id_typu FROM akce_typy WHERE zobrazit_v_menu = 1 ORDER BY id_typu LIMIT 1
SQL);
        self::assertGreaterThan(0, $idTypu, 'Testovací DB nemá linii zobrazenou v menu');
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        self::assertSame(303, $this->odesli($session, 'web/hlavicky-linii', $this->hlavicka($idTypu, 'head of the test line')));
        self::assertSame(
            [
                'sekce'    => 'šéfka testovací linie',
                'sekce_en' => 'head of the test line',
            ],
            dbOneLine('SELECT sekce, sekce_en FROM akce_typy_hlavicky WHERE id_typu = $0', [
                0 => $idTypu,
            ]),
        );
        $stranka = $this->stahni($session, 'web/hlavicky-linii');
        self::assertStringContainsString('name="sekce_en" value="head of the test line"', $stranka);

        // prázdná anglická = NULL, anglický web ukáže českou
        self::assertSame(303, $this->odesli($session, 'web/hlavicky-linii', $this->hlavicka($idTypu, '  ')));
        self::assertNull(dbOneCol('SELECT sekce_en FROM akce_typy_hlavicky WHERE id_typu = $0', [
            0 => $idTypu,
        ]));
    }

    /**
     * @test
     */
    public function hlavickaJenSAnglickouSekciSeNesmaze(): void
    {
        $idTypu = (int) dbFetchSingle(<<<'SQL'
SELECT id_typu FROM akce_typy WHERE zobrazit_v_menu = 1 ORDER BY id_typu LIMIT 1
SQL);
        $session = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        self::assertSame(303, $this->odesli($session, 'web/hlavicky-linii', [
            'action'   => 'save-line-header',
            'id_typu'  => (string) $idTypu,
            'sekce'    => '',
            'sekce_en' => 'head of the test line',
            'jmeno'    => '',
            'email'    => '',
        ]));
        self::assertSame(
            'head of the test line',
            dbOneCol('SELECT sekce_en FROM akce_typy_hlavicky WHERE id_typu = $0', [
                0 => $idTypu,
            ]),
            'Prázdné jsou jen ostatní texty, hlavička se proto nemaže.',
        );
    }

    /**
     * @return array<string, string>
     */
    private function hlavicka(int $idTypu, string $sekceEn): array
    {
        return [
            'action'   => 'save-line-header',
            'id_typu'  => (string) $idTypu,
            'sekce'    => 'šéfka testovací linie',
            'sekce_en' => $sekceEn,
            'jmeno'    => 'Testovací „Šéfka“',
            'email'    => 'linie@example.com',
        ];
    }
}
