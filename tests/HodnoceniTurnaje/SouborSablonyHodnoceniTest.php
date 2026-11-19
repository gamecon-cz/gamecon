<?php

declare(strict_types=1);

namespace Gamecon\Tests\HodnoceniTurnaje;

use Gamecon\HodnoceniTurnaje\ChybaSablonyHodnoceni;
use Gamecon\HodnoceniTurnaje\ParserSablonyHodnoceni;
use Gamecon\HodnoceniTurnaje\SablonaHodnoceni;
use Gamecon\HodnoceniTurnaje\SouborSablonyHodnoceni;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\ODS\Writer as OdsWriter;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Soubor, který org turnaje načte do editoru kritérií: XLSX, ODS, CSV z Excelu (středník, Windows-1250) i odjinud
 * (čárka, UTF-8 s BOM), text. Formát se pozná podle přípony původního názvu.
 */
class SouborSablonyHodnoceniTest extends TestCase
{
    private string $slozka;

    protected function setUp(): void
    {
        $this->slozka = sys_get_temp_dir() . '/gamecon-kriteria-' . bin2hex(random_bytes(6));
        (new Filesystem())->mkdir($this->slozka);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->slozka);
    }

    public function testPrecteXlsx(): void
    {
        $cesta = $this->slozka . '/kriteria';
        $writer = new XlsxWriter();
        $writer->openToFile($cesta);
        foreach ([
            ['Kategorie', 'Kritérium'],
            // čísla v buňkách jsou v sešitu desetinná
            ['Škála', 1, 2, 3],
            ['Hraní rolí', 'Postavy'],
            ['', 'Spolupráce'],
            ['Řešení', 'Nápaditost'],
        ] as $radek) {
            $writer->addRow(Row::fromValues($radek));
        }
        $writer->close();

        // nahraný soubor má dočasný název bez přípony, formát řekne původní název
        $sablona = SouborSablonyHodnoceni::nacti($cesta, 'Kritéria.XLSX', [0, 1]);

        self::assertSame("Škála: 1 2 3\n# Hraní rolí\n## Postavy\n## Spolupráce\n# Řešení\n## Nápaditost", $sablona->getText());
    }

    public function testPrecteOdsSChybouNaSpravnemRadku(): void
    {
        $cesta = $this->slozka . '/kriteria.ods';
        $writer = new OdsWriter();
        $writer->openToFile($cesta);
        $writer->addRow(Row::fromValues(['Hraní', 'Postavy']));
        $writer->addRow(Row::fromValues(['', 'postavy']));
        $writer->close();

        self::assertSame(
            ['Řádek 2: kritérium „postavy“ už je v kategorii „Hraní“ na řádku 1.'],
            $this->getChyby($cesta, 'kriteria.ods'),
        );
    }

    public function testPrecteCsvZCeskehoExcelu(): void
    {
        $cesta = $this->zapis(iconv('UTF-8', 'WINDOWS-1250', "Kategorie;Kritérium\r\nŠkála;1 2 3 4 5\r\n\"Hraní; rolí\";Postavy\r\n;Spolupráce\r\n;;\r\nŘešení;Nápaditost\r\n"));

        $sablona = SouborSablonyHodnoceni::nacti($cesta, 'kriteria.csv', [0, 1]);

        self::assertSame([1, 2, 3, 4, 5], $sablona->skala);
        self::assertSame(['Hraní; rolí', 'Řešení'], array_column($sablona->kategorie, 'nazev'));
        self::assertSame(['Postavy', 'Spolupráce'], array_column($sablona->kategorie[0]->kriteria, 'nazev'));
    }

    public function testPrecteCsvSCarkouABom(): void
    {
        $cesta = $this->zapis("\xEF\xBB\xBFKategorie,Kritérium\nHraní,Postavy\nHraní,\"Hlas, zpěv\"\n");

        $sablona = SouborSablonyHodnoceni::nacti($cesta, 'kriteria.csv', [0, 1, 2]);

        self::assertSame([0, 1, 2], $sablona->skala, 'Bez škály v tabulce zůstane škála z editoru');
        self::assertSame(['Postavy', 'Hlas, zpěv'], array_column($sablona->kategorie[0]->kriteria, 'nazev'));
    }

    public function testOddelovacCsvNepocitaTextVUvozovkach(): void
    {
        // čárky ve škále a v názvu by vyhrály, kdyby se počítaly
        $sablona = SouborSablonyHodnoceni::nacti(
            $this->zapis("Škála;\"1, 2, 3\"\n\"Hraní, role\";Postavy\n"),
            'kriteria.csv',
            [0, 1],
        );
        self::assertSame([1, 2, 3], $sablona->skala);
        self::assertSame(['Hraní, role'], array_column($sablona->kategorie, 'nazev'));

        $carkami = SouborSablonyHodnoceni::nacti(
            $this->zapis("Kategorie,Kritérium\n\"Hraní; role\",Postavy\nHraní; role,Spolupráce\n"),
            'kriteria.csv',
            [0, 1],
        );
        self::assertSame(['Postavy', 'Spolupráce'], array_column($carkami->kategorie[0]->kriteria, 'nazev'));
    }

    public function testStazenaKriteriaJdouNacistZpatky(): void
    {
        // tak jako stažení na stránce: Report píše středník a BOM
        $sablona = ParserSablonyHodnoceni::rozeber(ParserSablonyHodnoceni::UKAZKA);
        $csv = "\xEF\xBB\xBF";
        foreach ([ParserSablonyHodnoceni::HLAVICKA_TABULKY, ...$sablona->getRadkyTabulky()] as $radek) {
            $csv .= implode(';', $radek) . "\n";
        }

        self::assertSame(
            ParserSablonyHodnoceni::UKAZKA,
            SouborSablonyHodnoceni::nacti($this->zapis($csv), 'kriteria.csv', [0, 1])->getText(),
        );
    }

    public function testPrecteText(): void
    {
        $sablona = SouborSablonyHodnoceni::nacti($this->zapis(ParserSablonyHodnoceni::UKAZKA), 'kriteria.txt', [0, 1]);

        self::assertInstanceOf(SablonaHodnoceni::class, $sablona);
        self::assertSame([1, 2, 3, 4, 5], $sablona->skala);
    }

    public function testNeznamyAPoskozenySouborHlasiSrozumitelne(): void
    {
        $cesta = $this->zapis('to není sešit');

        self::assertSame(
            ['Starý formát XLS neumím přečíst. V Excelu tabulku ulož jako XLSX nebo CSV.'],
            $this->getChyby($cesta, 'kriteria.xls'),
        );
        self::assertSame(
            ['Soubor .docx neumím přečíst. Kritéria načtu z tabulky XLSX, ODS nebo CSV, nebo z textového souboru .txt.'],
            $this->getChyby($cesta, 'kriteria.docx'),
        );
        self::assertSame(
            ['Soubor nejde přečíst jako tabulku XLSX. Je opravdu z Excelu nebo LibreOffice?'],
            $this->getChyby($cesta, 'kriteria.xlsx'),
        );
    }

    private function zapis(string $obsah): string
    {
        $cesta = $this->slozka . '/' . bin2hex(random_bytes(4));
        file_put_contents($cesta, $obsah);

        return $cesta;
    }

    /**
     * @return list<string>
     */
    private function getChyby(string $cesta, string $nazev): array
    {
        try {
            SouborSablonyHodnoceni::nacti($cesta, $nazev, [0, 1]);
        } catch (ChybaSablonyHodnoceni $chybaSablonyHodnoceni) {
            return $chybaSablonyHodnoceni->getChyby();
        }
        self::fail('Soubor měl jít načíst jen s chybou');
    }
}
