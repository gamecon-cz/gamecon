<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly\EmailovaSablona;

use Gamecon\Kanaly\EmailovaSablona\KodEmailoveSablonyEnum;
use Gamecon\Kanaly\EmailovaSablona\RozkladSymbolu;
use Gamecon\Kanaly\EmailovaSablona\Token;
use Gamecon\Kanaly\EmailovaSablona\UlozenaEmailovaSablona;
use Gamecon\Uzivatel\Dto\VlastniZneniUpominky;
use PHPUnit\Framework\TestCase;

class RozkladSymboluTest extends TestCase
{
    /**
     * @test
     */
    public function textBezProcentZustaneJedenToken(): void
    {
        self::assertSame(['TEXT:Ahoj světe, žádné symboly.'], $this->popis('Ahoj světe, žádné symboly.'));
    }

    /**
     * @test
     */
    public function prazdnyTextNemaZadneTokeny(): void
    {
        self::assertSame([], $this->popis(''));
    }

    /**
     * @test
     */
    public function prazdnyJeIJenBilyText(): void
    {
        self::assertTrue(RozkladSymbolu::jePrazdny(''));
        self::assertTrue(RozkladSymbolu::jePrazdny(" \t\r\n"));
        self::assertTrue(RozkladSymbolu::jePrazdny("\u{A0}\u{A0}\u{FEFF}"));
        self::assertTrue(RozkladSymbolu::jePrazdny("\xC3\x28"), 'invalid UTF-8 counts as blank');
        self::assertFalse(RozkladSymbolu::jePrazdny(' x '));
        self::assertFalse(RozkladSymbolu::jePrazdny('%%'));
    }

    /**
     * @test
     */
    public function ulozenaSablonaJenZNezlomitelnychMezerNeniVyplnena(): void
    {
        self::assertFalse((new UlozenaEmailovaSablona(KodEmailoveSablonyEnum::OBNOVA_HESLA, "\u{A0}", 'Text'))->jeVyplnena());
        self::assertFalse((new UlozenaEmailovaSablona(KodEmailoveSablonyEnum::OBNOVA_HESLA, 'Předmět', "\u{A0}"))->jeVyplnena());
        self::assertTrue((new UlozenaEmailovaSablona(KodEmailoveSablonyEnum::OBNOVA_HESLA, 'Předmět', 'Text'))->jeVyplnena());
        self::assertFalse((new VlastniZneniUpominky("\u{A0}", 'Text'))->jeVyplnene());
        self::assertFalse((new VlastniZneniUpominky('Předmět', "\u{A0}"))->jeVyplnene());
        self::assertTrue((new VlastniZneniUpominky('Předmět', 'Text'))->jeVyplnene());
    }

    /**
     * @test
     */
    public function symbolMeziProcentyJeSymbolASousedniTextZustane(): void
    {
        self::assertSame(
            ['TEXT:Ahoj ', 'SYMBOL:jmeno', 'TEXT:!'],
            $this->popis('Ahoj %jmeno%!'),
        );
    }

    /**
     * @test
     */
    public function symbolUprostredSlovaSeRozpoznaStejne(): void
    {
        self::assertSame(
            ['TEXT:Byl', 'SYMBOL:a', 'TEXT: jsi odhlášen', 'SYMBOL:a'],
            $this->popis('Byl%a% jsi odhlášen%a%'),
        );
    }

    /**
     * @test
     */
    public function systemovaHodnotaSeStejneJakoPromennaJenVelkymiPismeny(): void
    {
        self::assertSame(['SYMBOL:GC_BEZI_OD'], $this->popis('%GC_BEZI_OD%'));
    }

    /**
     * @test
     */
    public function dvaSymbolyVedleSebeNesdileProcento(): void
    {
        self::assertSame(['SYMBOL:jmeno', 'SYMBOL:prijmeni'], $this->popis('%jmeno%%prijmeni%'));
    }

    /**
     * @test
     */
    public function dvojiteProcentoJeJedenLiteralniZnak(): void
    {
        self::assertSame(['TEXT:Sleva 50% na vše'], $this->popis('Sleva 50%% na vše'));
    }

    /**
     * @test
     */
    public function dvojiteProcentoNeniZacatekSymbolu(): void
    {
        self::assertSame(['TEXT:%jmeno%'], $this->popis('%%jmeno%%'));
    }

    /**
     * @test
     *
     * @dataProvider holeProcentoProchazi
     */
    public function holeProcentoPredMezeroueNeboNaKonciJeLiteralni(string $text): void
    {
        self::assertSame(['TEXT:' . $text], $this->popis($text));
    }

    public static function holeProcentoProchazi(): array
    {
        return [
            'před mezerou'     => ['Vrátíme ti 50 % částky'],
            'na konci'         => ['Sleva 10%'],
            'před interpunkcí' => ['(sleva 10%), děkujeme'],
            'samo'             => ['%'],
        ];
    }

    /**
     * @test
     */
    public function symbolBezZaviraciHoProcentaJeNeuzavreny(): void
    {
        self::assertSame(['TEXT:Ahoj ', 'NEUZAVRENY_SYMBOL:jmen'], $this->popis('Ahoj %jmen'));
    }

    /**
     * @test
     */
    public function neuzavrenySymbolKonciPrvnimZnakemKterySeVSymboluNevyskytuje(): void
    {
        self::assertSame(
            ['NEUZAVRENY_SYMBOL:jmeno', 'TEXT: a ', 'SYMBOL:vs'],
            $this->popis('%jmeno a %vs%'),
        );
    }

    /**
     * @test
     */
    public function symbolZacinajiciCislicemJeCislicny(): void
    {
        self::assertSame(
            ['TEXT:Aktivita ', 'CISLICNY_SYMBOL:1', 'TEXT: v ', 'CISLICNY_SYMBOL:23'],
            $this->popis('Aktivita %1 v %23'),
        );
    }

    /**
     * @test
     */
    public function symbolSeSmiMichatPismenaCislicAPodtrzitka(): void
    {
        self::assertSame(['SYMBOL:odkaz_2'], $this->popis('%odkaz_2%'));
    }

    /**
     * @test
     */
    public function symbolSeSmiMichatVelkaAMalaPismenaAleNeniPromennouAniSystemovouHodnotou(): void
    {
        self::assertSame(['SYMBOL:Jmeno'], $this->popis('%Jmeno%'));
        self::assertFalse(RozkladSymbolu::jePromenna('Jmeno'));
        self::assertFalse(RozkladSymbolu::jeSystemovaHodnota('Jmeno'));
    }

    /**
     * @test
     */
    public function promennaMaMalaPismenaASystemovaHodnotaVelka(): void
    {
        self::assertTrue(RozkladSymbolu::jePromenna('jmeno_prijemce'));
        self::assertFalse(RozkladSymbolu::jeSystemovaHodnota('jmeno_prijemce'));
        self::assertTrue(RozkladSymbolu::jeSystemovaHodnota('GC_BEZI_OD'));
        self::assertFalse(RozkladSymbolu::jePromenna('GC_BEZI_OD'));
    }

    /**
     * @test
     */
    public function jakoZapsanoVratiPuvodniPodobuSymbolu(): void
    {
        $tokeny = RozkladSymbolu::rozloz('%jmeno% %jmen %1');

        self::assertSame(
            ['%jmeno%', ' ', '%jmen', ' ', '%1'],
            array_map(static fn (Token $token) => $token->jakoZapsano(), $tokeny),
        );
    }

    /**
     * @return list<string>
     */
    private function popis(string $text): array
    {
        return array_map(
            static fn (Token $token) => $token->typ->name . ':' . $token->hodnota,
            RozkladSymbolu::rozloz($text),
        );
    }
}
