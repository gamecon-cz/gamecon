<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly\EmailovaSablona;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Kanaly\EmailovaSablona\EmailovaPromenna;
use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaDefinice;
use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaVykreslovac;
use Gamecon\Kanaly\EmailovaSablona\Exceptions\ChybiPromennaSablony;
use Gamecon\Kanaly\EmailovaSablona\SystemoveHodnotyProSablony;
use Gamecon\Kanaly\EmailovaSablona\VykreslenaSablona;
use Gamecon\Kanaly\GcMail;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniKlice;
use Gamecon\Tests\Db\AbstractTestDb;

class EmailovaSablonaVykreslovacTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function dosadiPromenneSystemovouHodnotuAProcento(): void
    {
        $vykresleno = $this->vykresli(
            GcMail::FORMAT_TEXT,
            'Ahoj %jmeno% z GameConu %ROCNIK%',
            'Sleva 50%% pro %jmeno%, odkaz %odkaz%',
            [
                'jmeno' => 'Jana',
                'odkaz' => 'https://example.test/x',
            ],
        );

        self::assertSame('Ahoj Jana z GameConu ' . ROCNIK, $vykresleno->predmet);
        self::assertSame('Sleva 50% pro Jana, odkaz https://example.test/x', $vykresleno->text);
        self::assertSame(GcMail::FORMAT_TEXT, $vykresleno->format);
    }

    /**
     * @test
     */
    public function dosadiSePodleTypuCeskyFormatData(): void
    {
        $vykresleno = $this->vykresli(
            GcMail::FORMAT_TEXT,
            'Předmět',
            'Začátek: %GC_BEZI_OD%',
            [
                'jmeno' => 'x',
                'odkaz' => 'x',
            ],
        );

        self::assertSame(
            'Začátek: ' . (new DateTimeCz(GC_BEZI_OD))->formatCasNaMinutyStandard(),
            $vykresleno->text,
        );
    }

    /**
     * @test
     */
    public function neverejneNastaveniZustaneVTextuNedosazene(): void
    {
        $klic = SystemoveNastaveniKlice::PODEZRELE_VYSOKA_PLATBA_UCASTNIKA;

        $vykresleno = $this->vykresli(
            GcMail::FORMAT_TEXT,
            "Předmět %{$klic}%",
            "Hranice %{$klic}% korun",
            [
                'jmeno' => 'x',
                'odkaz' => 'x',
            ],
        );

        self::assertSame("Předmět %{$klic}%", $vykresleno->predmet);
        self::assertSame("Hranice %{$klic}% korun", $vykresleno->text);
    }

    /**
     * @test
     */
    public function konstantaZKonfiguraceKteraNeniPovolenaSeNikdyNedosadi(): void
    {
        self::assertTrue(defined('DB_PASS'), 'Test předpokládá, že konstanta existuje, jinak by nic neprokázal.');

        $vykresleno = $this->vykresli(
            GcMail::FORMAT_TEXT,
            'Předmět',
            'Heslo %DB_PASS%',
            [
                'jmeno' => 'x',
                'odkaz' => 'x',
            ],
        );

        self::assertSame('Heslo %DB_PASS%', $vykresleno->text);
    }

    /**
     * @test
     */
    public function promennaKterouPredmetNepovolujeSeVPredmetuNedosadi(): void
    {
        $vykresleno = $this->vykresli(
            GcMail::FORMAT_TEXT,
            'Odkaz %odkaz%',
            'Text',
            [
                'jmeno' => 'x',
                'odkaz' => 'https://example.test',
            ],
        );

        self::assertSame('Odkaz %odkaz%', $vykresleno->predmet);
    }

    /**
     * @test
     */
    public function nezavrenyACislicnySymbolZustanouTakJakJsou(): void
    {
        $vykresleno = $this->vykresli(
            GcMail::FORMAT_TEXT,
            'Předmět',
            'A %jmen a %1 a %jmeno%',
            [
                'jmeno' => 'Jana',
                'odkaz' => 'x',
            ],
        );

        self::assertSame('A %jmen a %1 a Jana', $vykresleno->text);
    }

    /**
     * @test
     */
    public function vHtmlSeHodnotyEscapujiAleHotoveHtmlPromennaNe(): void
    {
        $vykresleno = $this->vykresli(
            GcMail::FORMAT_HTML,
            'Předmět %jmeno%',
            'Ahoj %jmeno% <b>tučně</b> %odkaz%',
            [
                'jmeno' => 'Tom & <Jerry>',
                'odkaz' => '<a href="https://example.test">odkaz</a>',
            ],
            odkazJeHtml: true,
        );

        self::assertSame('Předmět Tom & <Jerry>', $vykresleno->predmet, 'Předmět je vždy prostý text');
        self::assertSame(
            'Ahoj Tom &amp; &lt;Jerry&gt; <b>tučně</b> <a href="https://example.test">odkaz</a>',
            $vykresleno->text,
        );
    }

    /**
     * @test
     */
    public function vHtmlSeUzEscapovanaHodnotaNeescapujeDvakrat(): void
    {
        $vykresleno = $this->vykresli(
            GcMail::FORMAT_HTML,
            'Předmět',
            '%jmeno%',
            [
                'jmeno' => 'Dungeons &amp; Dragons',
                'odkaz' => 'x',
            ],
        );

        self::assertSame('Dungeons &amp; Dragons', $vykresleno->text);
    }

    /**
     * @test
     */
    public function vHtmlSeNoveRadkyVSablonePrevedouNaBrAleVHodnotachZustanou(): void
    {
        $vykresleno = $this->vykresli(
            GcMail::FORMAT_HTML,
            'Předmět',
            "První\r\nDruhý\n\n%jmeno%",
            [
                'jmeno' => "Řádek\nDva",
                'odkaz' => 'x',
            ],
        );

        self::assertSame("První<br />Druhý<br /><br />Řádek\nDva", $vykresleno->text);
    }

    /**
     * @test
     */
    public function vTextovemEmailuSeNeEscapujeAniNeMeniNoveRadky(): void
    {
        $vykresleno = $this->vykresli(
            GcMail::FORMAT_TEXT,
            'Předmět',
            "Tom & <Jerry>\n%jmeno%",
            [
                'jmeno' => 'A & B',
                'odkaz' => 'x',
            ],
        );

        self::assertSame("Tom & <Jerry>\nA & B", $vykresleno->text);
    }

    /**
     * @test
     */
    public function chybejiciPromennaJeChybaVolajicihoKodu(): void
    {
        $this->expectException(ChybiPromennaSablony::class);
        $this->expectExceptionMessage('Chybí: [odkaz]');

        $this->vykresli(GcMail::FORMAT_TEXT, 'Předmět', 'Text', [
            'jmeno' => 'x',
        ]);
    }

    /**
     * @test
     */
    public function nadbytecnaPromennaJeChybaVolajicihoKodu(): void
    {
        $this->expectException(ChybiPromennaSablony::class);
        $this->expectExceptionMessage('navíc: [prekep]');

        $this->vykresli(GcMail::FORMAT_TEXT, 'Předmět', 'Text', [
            'jmeno'  => 'x',
            'odkaz'  => 'x',
            'prekep' => 'x',
        ]);
    }

    /**
     * @param array<string, string> $promenne
     */
    private function vykresli(
        string $format,
        string $predmet,
        string $text,
        array $promenne,
        bool $odkazJeHtml = false,
    ): VykreslenaSablona {
        $definice = new EmailovaSablonaDefinice(
            kod: 'test',
            skupina: 'Test',
            nazev: 'Test',
            kdyOdchazi: '',
            prijemce: '',
            format: $format,
            promenne: [
                new EmailovaPromenna('jmeno', 'jméno', 'Jan', vPredmetu: true),
                new EmailovaPromenna('odkaz', 'odkaz', 'x', html: $odkazJeHtml),
            ],
        );

        return (new EmailovaSablonaVykreslovac(new SystemoveHodnotyProSablony(SystemoveNastaveni::zGlobals())))
            ->vykresli($definice, $predmet, $text, $promenne);
    }
}
