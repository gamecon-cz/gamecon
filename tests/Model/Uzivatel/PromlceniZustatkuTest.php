<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Uzivatel;

use Gamecon\Logger\JobResultLogger;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\PromlceniZustatku;
use Gamecon\Uzivatel\UzivatelKPromlceni;
use PHPUnit\Framework\TestCase;
use Uzivatel;
use Gamecon\Uzivatel\Finance;

class PromlceniZustatkuTest extends TestCase
{
    /**
     * @test
     */
    public function Pocet_let_neplatnosti_je_definovan()
    {
        self::assertSame(3, PromlceniZustatku::getPocetLetNeplatnosti());
    }

    /**
     * @test
     */
    public function Vytvorim_CFO_report_s_prazdnym_seznamem_uzivatelu()
    {
        $systemoveNastaveni = $this->createMock(SystemoveNastaveni::class);
        $systemoveNastaveni->method('rocnik')->willReturn(2025);
        $systemoveNastaveni->method('poPrihlasovaniUcastniku')->willReturn(false);

        $jobResultLogger = $this->createMock(JobResultLogger::class);

        $promlceniZustatku = new PromlceniZustatku($systemoveNastaveni, $jobResultLogger);
        $report            = $promlceniZustatku->vytvorCfoReport([]);

        self::assertSame([], $report);
    }

    /**
     * @test
     */
    public function Vytvorim_CFO_report_s_jednim_uzivatelem()
    {
        $systemoveNastaveni = $this->createMock(SystemoveNastaveni::class);
        $systemoveNastaveni->method('rocnik')->willReturn(2025);
        $systemoveNastaveni->method('poPrihlasovaniUcastniku')->willReturn(false);

        $jobResultLogger = $this->createMock(JobResultLogger::class);

        $promlceniZustatku = new PromlceniZustatku($systemoveNastaveni, $jobResultLogger);

        // Mock Uzivatel
        $uzivatel = $this->createMock(Uzivatel::class);
        $uzivatel->method('id')->willReturn(123);
        $uzivatel->method('krestniJmeno')->willReturn('Jan');
        $uzivatel->method('prijmeni')->willReturn('Novák');
        $uzivatel->method('login')->willReturn('jan123');
        $uzivatel->method('mail')->willReturn('jan@example.com');

        $finance = $this->createMock(Finance::class);
        $finance->method('stav')->willReturn(500.0);
        $uzivatel->method('finance')->willReturn($finance);

        $uzivatele = [
            new UzivatelKPromlceni(
                uzivatel: $uzivatel,
                prihlaseniNaRocniky: '2020;2021',
                rokPosledniPlatby: 2021,
                mesicPosledniPlatby: 6,
                denPosledniPlatby: 15,
            ),
        ];

        $report = $promlceniZustatku->vytvorCfoReport($uzivatele);

        self::assertCount(1, $report);
        self::assertSame(123, $report[0]['id_uzivatele']);
        self::assertSame('Jan', $report[0]['jmeno']);
        self::assertSame('Novák', $report[0]['prijmeni']);
        self::assertSame('jan@example.com', $report[0]['email']);
        self::assertSame(500.0, $report[0]['promlcena_castka']);
        self::assertSame(2021, $report[0]['rok_posledni_platby']);
        self::assertSame(6, $report[0]['mesic_posledni_platby']);
        self::assertSame(15, $report[0]['den_posledni_platby']);
    }

    /**
     * @test
     */
    public function Vytvorim_CFO_report_s_uzivatelem_bez_ucasti()
    {
        $systemoveNastaveni = $this->createMock(SystemoveNastaveni::class);
        $systemoveNastaveni->method('rocnik')->willReturn(2025);
        $systemoveNastaveni->method('poPrihlasovaniUcastniku')->willReturn(false);

        $jobResultLogger = $this->createMock(JobResultLogger::class);

        $promlceniZustatku = new PromlceniZustatku($systemoveNastaveni, $jobResultLogger);

        // Mock Uzivatel
        $uzivatel = $this->createMock(Uzivatel::class);
        $uzivatel->method('id')->willReturn(456);
        $uzivatel->method('login')->willReturn('petra456');
        $uzivatel->method('krestniJmeno')->willReturn('Petra');
        $uzivatel->method('mail')->willReturn('petra@example.com');

        $finance = $this->createMock(Finance::class);
        $finance->method('stav')->willReturn(1000.0);
        $uzivatel->method('finance')->willReturn($finance);

        $uzivatele = [
            new UzivatelKPromlceni(
                uzivatel: $uzivatel,
                prihlaseniNaRocniky: '', // Žádná účast
                rokPosledniPlatby: null,
                mesicPosledniPlatby: null,
                denPosledniPlatby: null,
            ),
        ];

        $report = $promlceniZustatku->vytvorCfoReport($uzivatele);

        self::assertCount(1, $report);
        self::assertSame(456, $report[0]['id_uzivatele']);
        self::assertSame('Petra', $report[0]['jmeno']);
        self::assertSame(1000.0, $report[0]['promlcena_castka']);
        self::assertNull($report[0]['rok_posledni_platby']);
        self::assertNull($report[0]['mesic_posledni_platby']);
        self::assertNull($report[0]['den_posledni_platby']);

        // Zkontroluj, že všechny roky účasti jsou "ne"
        for ($rok = 2009; $rok <= 2024; $rok++) {
            $klic = 'účast ' . $rok;
            if (isset($report[0][$klic])) {
                self::assertSame('ne', $report[0][$klic], "Očekáváno 'ne' pro $klic");
            }
        }
    }
}
