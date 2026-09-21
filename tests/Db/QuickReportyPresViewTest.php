<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db;

/**
 * Quick reporty mají SQL uložené v databázi, ne v kódu, takže přepis čtení na pohled
 * se jich nedotkl a hrubý grep po repozitáři je nenajde. Ty, které sahají na sloupce
 * dopočítané až pohledem (`typ`, `podtyp`, `model_rok`, `je_letosni_hlavni`), padají
 * na „Unknown column“ místo aby vrátily data.
 */
class QuickReportyPresViewTest extends AbstractTestDb
{
    private const SLOUPCE_JEN_Z_POHLEDU = ['typ', 'podtyp', 'model_rok', 'je_letosni_hlavni'];

    /**
     * Na databázi postavené migracemi je `reporty_quick` prázdná, takže samotná
     * kontrola by prošla, aniž by cokoli ověřila. Fixtury proto kopírují tvary
     * z produkce: dva vadné (bez aliasu a s aliasem) a jeden, který se přepsat nesmí.
     */
    protected static array $initQueries = [
        <<<SQL
INSERT INTO reporty_quick SET id = 9401, nazev = 'Bez aliasu', dotaz = 'select * from shop_nakupy join shop_predmety ON shop_predmety.id_predmetu = shop_nakupy.id_predmetu AND shop_predmety.typ IN (1, 3)'
SQL,
        <<<SQL
INSERT INTO reporty_quick SET id = 9402, nazev = 'S aliasem', dotaz = 'select * from shop_nakupy_zrusene snp join shop_predmety sp on snp.id_predmetu = sp.id_predmetu where sp.typ = 4'
SQL,
        <<<SQL
INSERT INTO reporty_quick SET id = 9403, nazev = 'Bez sloupce pohledu', dotaz = 'select p.nazev from shop_nakupy n join shop_predmety p on p.id_predmetu = n.id_predmetu'
SQL,
    ];

    /**
     * Fixtury vzniknou až po migracích, takže je tenhle test přepíše stejným pravidlem
     * jako migrace — kontroluje se, že pravidlo sedí na reálné tvary uloženého SQL.
     *
     * @test
     */
    public function prepisPokryvaObaTvaryJoinu(): void
    {
        self::assertSame(
            'select * from shop_nakupy join shop_predmety_s_typem ON shop_predmety_s_typem.id_predmetu = shop_nakupy.id_predmetu AND shop_predmety_s_typem.typ IN (1, 3)',
            $this->prepis($this->dotaz(9401)),
        );
        self::assertSame(
            'select * from shop_nakupy_zrusene snp join shop_predmety_s_typem sp on snp.id_predmetu = sp.id_predmetu where sp.typ = 4',
            $this->prepis($this->dotaz(9402)),
            'Alias musí zůstat, jinak se zbytek dotazu nemá čeho chytit',
        );
    }

    /**
     * Opakované spuštění nesmí vyrobit `shop_predmety_s_typem_s_typem`.
     *
     * @test
     */
    public function prepisJeIdempotentni(): void
    {
        $jednou = $this->prepis($this->dotaz(9401));

        self::assertSame($jednou, $this->prepis($jednou));
    }

    /**
     * @test
     */
    public function zadnyProdukcniDotazNezustalNaZakladniTabulce(): void
    {
        $vadne = [];
        foreach (dbFetchAll('SELECT id, nazev, dotaz FROM reporty_quick WHERE id < 9000') as $report) {
            $dotaz = (string) $report['dotaz'];
            if (preg_match('/\bshop_predmety\b(?!_s_typem)/i', $dotaz) !== 1) {
                continue;
            }
            foreach (self::SLOUPCE_JEN_Z_POHLEDU as $sloupec) {
                if (preg_match('/\b' . $sloupec . '\b/i', $dotaz) === 1) {
                    $vadne[] = $report['id'] . ' (' . $report['nazev'] . ')';
                    break;
                }
            }
        }

        self::assertSame([], $vadne, 'Tyhle quick reporty spadnou na Unknown column');
    }

    private function prepis(string $dotaz): string
    {
        return (string) preg_replace('/\bshop_predmety\b(?!_s_typem)/i', 'shop_predmety_s_typem', $dotaz);
    }

    private function dotaz(int $id): string
    {
        return (string) dbFetchSingle('SELECT dotaz FROM reporty_quick WHERE id = $0', [
            0 => $id,
        ]);
    }
}
