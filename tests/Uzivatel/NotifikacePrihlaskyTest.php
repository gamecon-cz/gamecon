<?php

declare(strict_types=1);

namespace Gamecon\Tests\Uzivatel;

use Gamecon\Uzivatel\NotifikacePrihlasky;
use PHPUnit\Framework\TestCase;

class NotifikacePrihlaskyTest extends TestCase
{
    private NotifikacePrihlasky $notifikacePrihlasky;

    protected function setUp(): void
    {
        $reflexe = new \ReflectionClass(NotifikacePrihlasky::class);
        $this->notifikacePrihlasky = $reflexe->newInstanceWithoutConstructor();
    }

    private function vyvolejPrivatniMetodu(string $nazevMetody, array $argumenty = []): mixed
    {
        $metoda = new \ReflectionMethod(NotifikacePrihlasky::class, $nazevMetody);
        $metoda->setAccessible(true);

        return $metoda->invokeArgs($this->notifikacePrihlasky, $argumenty);
    }

    /**
     * @test
     */
    public function formatCastkaVypiseCeleCisloBezDesetinnychMist(): void
    {
        self::assertSame('100 Kč', $this->vyvolejPrivatniMetodu('formatCastka', [100.0]));
    }

    /**
     * @test
     */
    public function formatCastkaVypiseNuluBezDesetinnychMist(): void
    {
        self::assertSame('0 Kč', $this->vyvolejPrivatniMetodu('formatCastka', [0.0]));
    }

    /**
     * @test
     */
    public function formatCastkaVypiseZapornouCastku(): void
    {
        self::assertSame('-150 Kč', $this->vyvolejPrivatniMetodu('formatCastka', [-150.0]));
    }

    /**
     * @test
     */
    public function formatCastkaVypiseDesetinneCisloSeDvemaPlatnymiCislicemi(): void
    {
        self::assertSame('100,55 Kč', $this->vyvolejPrivatniMetodu('formatCastka', [100.55]));
    }

    /**
     * @test
     */
    public function formatCastkaOreznePosledniNulu(): void
    {
        self::assertSame('100,5 Kč', $this->vyvolejPrivatniMetodu('formatCastka', [100.5]));
    }

    /**
     * @test
     */
    public function formatCastkaZaokrouhliNaDveDesetinnaMista(): void
    {
        self::assertSame('100,57 Kč', $this->vyvolejPrivatniMetodu('formatCastka', [100.567]));
    }

    /**
     * @test
     */
    public function formatCastkaZaokrouhliCastkuPodCele(): void
    {
        self::assertSame('100 Kč', $this->vyvolejPrivatniMetodu('formatCastka', [99.999]));
    }

    /**
     * @test
     */
    public function formatRozdilObjednavekHlasiNoZmen(): void
    {
        $snapshot = $this->prazdnySnapshot();

        $vystup = $this->vyvolejPrivatniMetodu('formatRozdilObjednavek', [$snapshot, $snapshot]);

        self::assertSame(
            'Nezaznamenali jsme změny v aktivitách, ubytování, jídle, merchi ani vstupném.',
            $vystup,
        );
    }

    /**
     * @test
     */
    public function formatRozdilObjednavekHlasiPouzePridane(): void
    {
        $predchozi = $this->prazdnySnapshot();
        $aktualni = $this->prazdnySnapshot();
        $aktualni['Aktivity']['Dračí doupě'] = 1;

        $vystup = $this->vyvolejPrivatniMetodu('formatRozdilObjednavek', [$predchozi, $aktualni]);

        self::assertSame(
            "Přidáno:\n+ Aktivity: Dračí doupě",
            $vystup,
        );
    }

    /**
     * @test
     */
    public function formatRozdilObjednavekHlasiPouzeOdebrane(): void
    {
        $predchozi = $this->prazdnySnapshot();
        $predchozi['Ubytování']['Pátek na neděli'] = 1;
        $aktualni = $this->prazdnySnapshot();

        $vystup = $this->vyvolejPrivatniMetodu('formatRozdilObjednavek', [$predchozi, $aktualni]);

        self::assertSame(
            "Odebráno:\n- Ubytování: Pátek na neděli",
            $vystup,
        );
    }

    /**
     * @test
     */
    public function formatRozdilObjednavekHlasiPridanaIOdebrana(): void
    {
        $predchozi = $this->prazdnySnapshot();
        $predchozi['Ubytování']['Pátek na neděli'] = 1;
        $aktualni = $this->prazdnySnapshot();
        $aktualni['Aktivity']['Dračí doupě'] = 1;

        $vystup = $this->vyvolejPrivatniMetodu('formatRozdilObjednavek', [$predchozi, $aktualni]);

        self::assertSame(
            "Přidáno:\n+ Aktivity: Dračí doupě\n\nOdebráno:\n- Ubytování: Pátek na neděli",
            $vystup,
        );
    }

    /**
     * @test
     */
    public function formatRozdilObjednavekZobraziMnozstviUVicePolozek(): void
    {
        $predchozi = $this->prazdnySnapshot();
        $aktualni = $this->prazdnySnapshot();
        $aktualni['Jídlo']['Oběd sobota'] = 3;

        $vystup = $this->vyvolejPrivatniMetodu('formatRozdilObjednavek', [$predchozi, $aktualni]);

        self::assertSame(
            "Přidáno:\n+ Jídlo: Oběd sobota (3x)",
            $vystup,
        );
    }

    /**
     * @test
     */
    public function formatRozdilObjednavekZobraziJenRozdilPriZmeneMnozstvi(): void
    {
        $predchozi = $this->prazdnySnapshot();
        $predchozi['Jídlo']['Oběd sobota'] = 1;
        $aktualni = $this->prazdnySnapshot();
        $aktualni['Jídlo']['Oběd sobota'] = 3;

        $vystup = $this->vyvolejPrivatniMetodu('formatRozdilObjednavek', [$predchozi, $aktualni]);

        self::assertSame(
            "Přidáno:\n+ Jídlo: Oběd sobota (2x)",
            $vystup,
        );
    }

    /**
     * @test
     */
    public function formatRozdilObjednavekZachovavaPoradiKategorii(): void
    {
        $predchozi = $this->prazdnySnapshot();
        $aktualni = $this->prazdnySnapshot();
        $aktualni['Dobrovolné vstupné']['Vstupné'] = 1;
        $aktualni['Aktivity']['Dračí doupě'] = 1;
        $aktualni['Merch']['Tričko'] = 1;
        $aktualni['Jídlo']['Oběd sobota'] = 1;
        $aktualni['Ubytování']['Pátek na neděli'] = 1;

        $vystup = $this->vyvolejPrivatniMetodu('formatRozdilObjednavek', [$predchozi, $aktualni]);

        self::assertSame(
            "Přidáno:\n"
            . "+ Aktivity: Dračí doupě\n"
            . "+ Ubytování: Pátek na neděli\n"
            . "+ Jídlo: Oběd sobota\n"
            . "+ Merch: Tričko\n"
            . '+ Dobrovolné vstupné: Vstupné',
            $vystup,
        );
    }

    /**
     * @test
     */
    public function formatRozdilObjednavekZobraziPoloznujenJednouPriShodnemPredchozimAAktualnimMnozstvi(): void
    {
        $predchozi = $this->prazdnySnapshot();
        $predchozi['Aktivity']['Dračí doupě'] = 1;
        $aktualni = $this->prazdnySnapshot();
        $aktualni['Aktivity']['Dračí doupě'] = 1;

        $vystup = $this->vyvolejPrivatniMetodu('formatRozdilObjednavek', [$predchozi, $aktualni]);

        self::assertSame(
            'Nezaznamenali jsme změny v aktivitách, ubytování, jídle, merchi ani vstupném.',
            $vystup,
        );
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function prazdnySnapshot(): array
    {
        return [
            'Aktivity'           => [],
            'Ubytování'          => [],
            'Jídlo'              => [],
            'Merch'              => [],
            'Dobrovolné vstupné' => [],
        ];
    }
}
