<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Report\KonfiguraceReportu;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * OpenSpout 4.x before 4.30 writes `showZeroes` into `<sheetView>`, but the XLSX specification
 * (ECMA-376) names the attribute `showZeros`. Excel skips what it does not know, strict readers
 * such as openpyxl refuse the whole file.
 */
class ReportXlsxSheetViewTest extends TestCase
{
    private string $soubor;

    protected function setUp(): void
    {
        parent::setUp();
        (new Filesystem())->mkdir(SPEC, 0775);
        $this->soubor = SPEC . '/report_xlsx_sheet_view_' . uniqid('', false) . '.xlsx';
    }

    protected function tearDown(): void
    {
        if (is_file($this->soubor)) {
            unlink($this->soubor);
        }

        parent::tearDown();
    }

    public function testPlatnyNazevAtributuProZobrazeniNul(): void
    {
        $sheetView = $this->sheetView(new KonfiguraceReportu());

        self::assertStringContainsString('showZeros="true"', $sheetView);
    }

    public function testNeplatnyNazevAtributuSeNepise(): void
    {
        $sheetView = $this->sheetView(new KonfiguraceReportu());

        self::assertStringNotContainsString('showZeroes', $sheetView);
    }

    public function testSpravnyAtributPrezijeIZmrazeniZahlavi(): void
    {
        $sheetView = $this->sheetView((new KonfiguraceReportu())->setRowToFreeze(1));

        self::assertStringContainsString('showZeros="true"', $sheetView);
        self::assertStringNotContainsString('showZeroes', $sheetView);
        self::assertStringContainsString('<pane', $sheetView, 'Zmrazení záhlaví se nesmí ztratit');
    }

    private function sheetView(KonfiguraceReportu $konfigurace): string
    {
        \Report::zPoli(['jmeno', 'castka'], [['Novák', '100'], ['Svoboda', '0']])
            ->tXlsx('test', $konfigurace->setDestinationFile($this->soubor));

        $archiv = new \ZipArchive();
        self::assertTrue($archiv->open($this->soubor), 'XLSX se musí dát otevřít jako ZIP');
        $xml = $archiv->getFromName('xl/worksheets/sheet1.xml');
        $archiv->close();
        self::assertIsString($xml);
        self::assertSame(1, preg_match('~<sheetViews>.*?</sheetViews>~s', $xml, $shody), 'List musí mít <sheetViews>');

        return $shody[0];
    }
}
