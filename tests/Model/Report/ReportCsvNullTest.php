<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use PHPUnit\Framework\TestCase;

class ReportCsvNullTest extends TestCase
{
    public function testPrazdnaBunkaSeVypiseJakoPrazdnyRetezec(): void
    {
        $report = \Report::zPoli(
            ['jmeno', 'poznamka', 'castka'],
            [
                ['Novák', null, '100'],
                ['Svoboda', 'bez dluhu', '0'],
            ],
        );

        self::assertSame(
            "jmeno;poznamka;castka\nNovák;;100\nSvoboda;\"bez dluhu\";0\n",
            $this->csv($report),
        );
    }

    /**
     * Samotný obsah CSV nestačí: `strip_tags(null)` řádek vypíše správně a teprve
     * deprecation ho v běžícím reportu zabije, protože Vyjimkovac z ní udělá výjimku
     * a vloží chybovou stránku doprostřed už odeslaného souboru.
     */
    public function testPrazdnaBunkaNevyvolaDeprecation(): void
    {
        $hlasky = [];
        set_error_handler(
            static function (int $uroven, string $zprava) use (&$hlasky): bool {
                $hlasky[] = $zprava;

                return true;
            },
            E_DEPRECATED,
        );

        try {
            $this->csv(\Report::zPoli(['a', 'b'], [['x', null]]));
        } finally {
            restore_error_handler();
        }

        self::assertSame([], $hlasky);
    }

    /**
     * Hodnota z databáze nemusí být řetězec — `SUM()` vrací číslo a `COUNT()` int.
     * Kdyby se převáděla jen `null`, spadlo by to o řádek dál na tomtéž místě.
     */
    public function testCislaProjdouBezeZmeny(): void
    {
        $report = \Report::zPoli(['kod', 'pocet', 'suma'], [['kostka', 7, 1234.5]]);

        self::assertStringContainsString('kostka;7;1234.5', $this->csv($report));
    }

    public function testZnackySeStaleOdstranuji(): void
    {
        $report = \Report::zPoli(['odkaz'], [['<a href="/x">detail</a>']]);

        self::assertStringContainsString('detail', $this->csv($report));
        self::assertStringNotContainsString('<a href', $this->csv($report));
    }

    /**
     * Výstup se sbírá přes buffer, protože `tCsv()` posílá hlavičky a píše rovnou
     * na `php://output`; v testu jde o samotné řádky, ne o stahovaný soubor.
     */
    private function csv(\Report $report): string
    {
        // Název staženého souboru se odvozuje z REQUEST_URI, které v CLI neexistuje.
        // Bez něj hlásí `nazevReportuZRequestu()` vlastní deprecation a test by měřil
        // prostředí, ne chybu, kterou ověřuje. Hodnota se musí zase uklidit: podle
        // ní pozná `KontextZobrazeni`, že neběžíme na webu, a ponechaná by shodila
        // každý pozdější test, který si staví Shop.
        $puvodniRequestUri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '/admin/reporty/test?format=csv';

        ob_start();
        try {
            $report->tCsv();
        } finally {
            $vystup = ob_get_clean();
            if ($puvodniRequestUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $puvodniRequestUri;
            }
        }

        // BOM na začátku je součást souboru pro Excel, ne dat.
        return str_replace("\u{FEFF}", '', $vystup);
    }
}
