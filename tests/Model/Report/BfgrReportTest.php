<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Report\BfgrReport;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\SqlStruktura\UzivatelSqlStruktura as Sql;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Helper\Escaper\XLSX;
use OpenSpout\Reader\Common\Creator\ReaderFactory;

class BfgrReportTest extends AbstractTestDb
{
    private static ?SystemoveNastaveni $systemoveNastaveni = null;
    private static ?\Uzivatel          $uzivatelSystem     = null;

    protected static bool $disableStrictTransTables = true;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$systemoveNastaveni = SystemoveNastaveni::vytvorZGlobals();
        self::$uzivatelSystem     = \Uzivatel::zId(\Uzivatel::SYSTEM);
    }

    /**
     * @test
     * @dataProvider provideZustatek
     */
    public function Zustatek_je_spravne($zustatek)
    {
        $idUzivatele = \Uzivatel::rychloreg(
            self::$systemoveNastaveni,
            [
                Sql::LOGIN_UZIVATELE  => 'foo@bar.example',
                Sql::EMAIL1_UZIVATELE => 'foo@bar.example',
            ],
            ['informovat' => false],
        );
        $uzivatel    = \Uzivatel::zId($idUzivatele);
        self::assertSame(0.0, $uzivatel->finance()->stav());

        $uzivatel->finance()->pripis($zustatek, self::$uzivatelSystem);
        $uzivatel = \Uzivatel::zId($idUzivatele, false); // opětovné načtení
        self::assertSame((float)$zustatek, $uzivatel->finance()->stav());

        $tempfile = sys_get_temp_dir() . '/' . uniqid('bfgr_test_', true) . '.xlsx';
        $bfgr     = new BfgrReport(self::$systemoveNastaveni);
        $bfgr->exportuj('xlsx', true, $tempfile);
        $reader = ReaderFactory::createFromFile($tempfile);
        $reader->open($tempfile);
        $data = [];
        foreach ($reader->getSheetIterator()->current()->getRowIterator() as $row) {
            /** @var Row $row */
            $data[] = $row->toArray();
        }

        $dataUzivatele = $this->filtrujUzivatele($idUzivatele, $data);
        $indexStavu    = $this->indexSloupce('Stav', $data);
        // XLSX rozlišuje int a float
        self::assertSame($zustatek, $dataUzivatele[$indexStavu]);
    }

    private function indexSloupce(string $nazevSloupce, array $data)
    {
        $nazvySloupcu = $data[1];
        return array_search($nazevSloupce, $nazvySloupcu);
    }

    private function filtrujUzivatele(int $idUzivatele, array $data): array
    {
        foreach ($data as $radek) {
            if ((int)$radek[0] === $idUzivatele) {
                return $radek;
            }
        }
        throw new \LogicException("Uživatel s ID $idUzivatele nenalezen v datech");
    }

    public static function provideZustatek(): array
    {
        return [
            'nula'              => [0],
            'záporné celé'      => [-1],
            'záporné desetinné' => [-1.23],
            'kladné celé'       => [1],
            'kladné desetinné'  => [1.23],
        ];
    }
}
