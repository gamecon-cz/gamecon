<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Jazyk\AktualniJazyk;
use Gamecon\Jazyk\Jazyk;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Web\PouzitiObrazku;
use Gamecon\Web\ZpracovaneObrazky;

/**
 * Stránka z CMS v češtině a angličtině: anglický web ukáže `obsah_en`, když ho stránka má, jinak český obsah.
 */
class StrankaTest extends AbstractTestDb
{
    private const string PRELOZENA = 'prelozena-testovaci-stranka';
    private const string NEPRELOZENA = 'neprelozena-testovaci-stranka';
    private const string S_PRAZDNYM_PREKLADEM = 'stranka-s-prazdnym-prekladem';
    private const string S_DATEM = 'stranka-s-datem-ze-zastupne-znacky';
    private const string OBRAZEK_JEN_V_ANGLICTINE = 'soubory/obsah/test-stranka-anglicky/jen-anglicky.png';

    /**
     * @return array<int, string|array{0: string, 1: array<int, mixed>}>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return [
            [
                <<<'SQL'
INSERT INTO stranky(url_stranky, obsah, obsah_en, poradi)
VALUES
    ($0, $1, $2, 0),
    ($3, $4, NULL, 0),
    ($5, $6, $7, 0),
    ($8, $9, $10, 0)
SQL,
                [
                    self::PRELOZENA,
                    "# Česky\n\nŘekni \"ahoj\".\n\n![](soubory/obsah/cesky.png)",
                    "# In English\n\nSay \"hello\".\n\n![](" . self::OBRAZEK_JEN_V_ANGLICTINE . ')',
                    self::NEPRELOZENA,
                    "# Jen česky\n\nŘekni \"ahoj\".",
                    self::S_PRAZDNYM_PREKLADEM,
                    '# Prázdný překlad',
                    // formulář adminu uloží prázdné pole jako prázdný řetězec, ne NULL
                    " \n ",
                    self::S_DATEM,
                    'GameCon začíná %GC_BEZI_OD|datum:FORMAT_ZACATEK_UDALOSTI%.',
                    'GameCon starts on %GC_BEZI_OD|datum:FORMAT_ZACATEK_UDALOSTI%.',
                ],
            ],
        ];
    }

    protected function tearDown(): void
    {
        AktualniJazyk::reset();
        parent::tearDown();
    }

    /**
     * @test
     */
    public function prelozenaStrankaDaVAnglictineAnglickyObsahSAnglickouTypografii(): void
    {
        $stranka = $this->stranka(self::PRELOZENA);

        self::assertTrue($stranka->isPrelozena());
        self::assertSame(Jazyk::ANGLICTINA, $stranka->getJazykObsahu(Jazyk::ANGLICTINA));
        self::assertStringContainsString('Say “hello”.', (string) $stranka->html(Jazyk::ANGLICTINA));
        self::assertSame('In English', $stranka->nadpis(Jazyk::ANGLICTINA));
        self::assertSame(self::OBRAZEK_JEN_V_ANGLICTINE, $stranka->obrazek(Jazyk::ANGLICTINA));
    }

    /**
     * @test
     */
    public function prelozenaStrankaZustavaVCestineCeska(): void
    {
        $stranka = $this->stranka(self::PRELOZENA);

        self::assertSame(Jazyk::CESTINA, $stranka->getJazykObsahu(Jazyk::CESTINA));
        self::assertStringContainsString('Řekni „ahoj“.', (string) $stranka->html(Jazyk::CESTINA));
        self::assertSame('Česky', $stranka->nadpis(Jazyk::CESTINA));
        self::assertSame('soubory/obsah/cesky.png', $stranka->obrazek(Jazyk::CESTINA));
    }

    /**
     * Jazyk stránky je bez parametru jazyk požadavku a HTML se pamatuje pro každý jazyk zvlášť.
     *
     * @test
     */
    public function bezParametruPlatiJazykPozadavku(): void
    {
        $stranka = $this->stranka(self::PRELOZENA);

        self::assertStringContainsString('Řekni „ahoj“.', (string) $stranka->html());
        self::assertSame('Česky', $stranka->nadpis());

        AktualniJazyk::nastav(Jazyk::ANGLICTINA);
        self::assertStringContainsString('Say “hello”.', (string) $stranka->html());
        self::assertSame('In English', $stranka->nadpis());
        self::assertSame(Jazyk::ANGLICTINA, $stranka->getJazykObsahu());
    }

    /**
     * @test
     */
    public function neprelozenaStrankaDaVAnglictineCeskyObsahSCeskouTypografii(): void
    {
        foreach ([self::NEPRELOZENA, self::S_PRAZDNYM_PREKLADEM] as $url) {
            $stranka = $this->stranka($url);

            self::assertFalse($stranka->isPrelozena(), $url);
            self::assertSame(Jazyk::CESTINA, $stranka->getJazykObsahu(Jazyk::ANGLICTINA), $url);
        }
        $neprelozena = $this->stranka(self::NEPRELOZENA);
        self::assertStringContainsString('Řekni „ahoj“.', (string) $neprelozena->html(Jazyk::ANGLICTINA));
        self::assertSame('Jen česky', $neprelozena->nadpis(Jazyk::ANGLICTINA));
    }

    /**
     * Obrázek, na který odkazuje jen anglický obsah, je pořád použitý: smazat ho (nebo převést bez přepsání odkazu)
     * by rozbilo anglickou stránku.
     *
     * @test
     */
    public function obrazekJenVAnglickemObsahuJePouzity(): void
    {
        $absolutniCesta = rtrim(ADRESAR_WEBU_S_OBRAZKY, '/') . '/' . self::OBRAZEK_JEN_V_ANGLICTINE;
        self::assertSame(self::OBRAZEK_JEN_V_ANGLICTINE, ZpracovaneObrazky::cestaVuciWebu($absolutniCesta));

        $pouziti = (new PouzitiObrazku())->kdeJePouzity($absolutniCesta);

        self::assertSame(['stránka ' . self::PRELOZENA], array_column($pouziti, 'popis'));
    }

    /**
     * Datum ze zástupné značky v anglickém textu (`%PRISTI_VLNA_KDY|datum:FORMAT_ZACATEK_UDALOSTI%`) je anglicky.
     *
     * @test
     */
    public function datumZeZastupneZnackyJeVJazyceTextu(): void
    {
        self::assertSame('15. 7. 2026 v 10:05', DateTimeCz::formatujProSablonu('2026-07-15 10:05:00', ['FORMAT_ZACATEK_UDALOSTI']));
        self::assertSame(
            '15 July 2026 at 10:05',
            DateTimeCz::formatujProSablonu('2026-07-15 10:05:00', ['FORMAT_ZACATEK_UDALOSTI'], Jazyk::ANGLICTINA),
        );
        self::assertSame('15 July', DateTimeCz::formatujProSablonu('2026-07-15', ['FORMAT_DATUM_LETOS'], Jazyk::ANGLICTINA));
        self::assertSame(
            '2026-07-15',
            DateTimeCz::formatujProSablonu('2026-07-15', ['FORMAT_DATUM_DB'], Jazyk::ANGLICTINA),
            'Strojový formát je ve všech jazycích stejný.',
        );
    }

    /**
     * @test
     */
    public function zastupnaZnackaVAnglickemObsahuStrankyDaAnglickeDatum(): void
    {
        $stranka = $this->stranka(self::S_DATEM);

        // celou cestou: Stranka::html() → nahradPlaceholderyZaNastaveni() → aplikujModifikatory() s jazykem obsahu
        self::assertMatchesRegularExpression(
            '~GameCon starts on \d{1,2} [A-Z][a-z]+ \d{4} at \d{2}:\d{2}\.~',
            (string) $stranka->html(Jazyk::ANGLICTINA),
        );
        self::assertMatchesRegularExpression(
            '~GameCon začíná \d{1,2}\. \d{1,2}\. \d{4} v \d{1,2}:\d{2}\.~u',
            (string) $stranka->html(Jazyk::CESTINA),
        );
    }

    private function stranka(string $url): \Stranka
    {
        $stranka = \Stranka::zUrl($url);
        self::assertNotNull($stranka, $url);

        return $stranka;
    }
}
