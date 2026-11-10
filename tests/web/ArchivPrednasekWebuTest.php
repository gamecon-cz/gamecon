<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

/**
 * The archive lives under the line of lectures, the module alone is not a page.
 */
class ArchivPrednasekWebuTest extends AbstractTestWeb
{
    /**
     * @return array<int, string|array<int, mixed>>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        return array_merge(
            parent::getSetUpBeforeClassInitQueries(),
            [
                <<<'SQL'
DELETE FROM archiv_prednasek
SQL,
                <<<'SQL'
INSERT INTO archiv_prednasek(id_prednasky, nazev, rok, youtube_id, skryta)
VALUES (990501, 'Viditelná přednáška v archivu', 2019, 'QaGjJIZJ4to', 0),
       (990502, 'Skrytá přednáška v archivu', 2019, NULL, 1),
       (990503, 'Přednáška jiného přednášejícího', 2018, NULL, 0)
SQL,
                <<<'SQL'
INSERT INTO archiv_prednasek_prednasejici(id_prednasejiciho, jmeno) VALUES (990501, 'Lektor <b>webu</b>'), (990502, 'Jiný lektor')
SQL,
                <<<'SQL'
INSERT INTO archiv_prednasek_prednaska_prednasejici(id_prednasky, id_prednasejiciho, poradi) VALUES (990501, 990501, 0), (990503, 990502, 0)
SQL,
            ],
        );
    }

    /**
     * @test
     */
    public function archivJePodLiniiPrednasekSKanonickouAdresouBezFiltru(): void
    {
        $odpoved = $this->stahniVerejnouStranku('web/prednasky/archiv?rok=2019&stitky=rpg&utm_source=x');

        self::assertSame(200, $odpoved['status']);
        self::assertSame(1, preg_match('~<link rel="canonical" href="([^"]+)">~', $odpoved['telo'], $shoda));
        self::assertStringEndsWith('/web/prednasky/archiv', html_entity_decode($shoda[1]));
        self::assertStringContainsString('Viditelná přednáška v archivu', $odpoved['telo']);
        self::assertStringNotContainsString('Skrytá přednáška v archivu', $odpoved['telo']);
        // the player loads only after a click
        self::assertStringNotContainsString('<iframe', $odpoved['telo']);
        self::assertStringContainsString('youtube-nocookie.com/embed/QaGjJIZJ4to', $odpoved['telo']);
    }

    /**
     * @test
     *
     * Without JavaScript the name of a speaker links to ?prednasejici=ID, the page hides the other lectures itself.
     */
    public function bezJavascriptuFiltrujePodlePrednasejiciho(): void
    {
        $odpoved = $this->stahniVerejnouStranku('web/prednasky/archiv?prednasejici=990501');

        self::assertSame(200, $odpoved['status']);
        self::assertStringContainsString('href="prednasky/archiv?prednasejici=990501"', $odpoved['telo']);
        self::assertStringContainsString('Přednášky, kde přednáší Lektor &lt;b&gt;webu&lt;/b&gt;: 1.', $odpoved['telo']);
        self::assertMatchesRegularExpression('~<article [^>]*id="prednaska-990501"[^>]*data-id="990501">~', $odpoved['telo']);
        self::assertMatchesRegularExpression('~<article [^>]*id="prednaska-990503"[^>]*data-id="990503" hidden>~', $odpoved['telo']);
        self::assertMatchesRegularExpression('~<section [^>]*data-rok="2018" hidden>~', $odpoved['telo'], 'Rok bez přednášky se schová i s nadpisem');

        $bezFiltru = $this->stahniVerejnouStranku('web/prednasky/archiv?prednasejici=990599');
        self::assertStringContainsString('Přednášek v archivu: 2', $bezFiltru['telo'], 'Neznámý přednášející se zahodí');
        self::assertDoesNotMatchRegularExpression('~<(?:article|section) [^>]* hidden>~', $bezFiltru['telo']);
    }

    /**
     * @test
     *
     * @dataProvider provideAdresyMimoArchiv
     */
    public function jindeArchivNeni(string $cesta): void
    {
        self::assertSame(404, $this->stahniVerejnouStranku($cesta)['status']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideAdresyMimoArchiv(): array
    {
        return [
            'samotný modul'      => ['web/archivprednasek'],
            'jiná linie'         => ['web/rpg/archiv'],
            'podstránka archivu' => ['web/prednasky/archiv/neco'],
        ];
    }

    /**
     * @test
     */
    public function strankaLiniePrednasekOdkazujeNaArchiv(): void
    {
        $odpoved = $this->stahniVerejnouStranku('web/prednasky');

        self::assertSame(200, $odpoved['status']);
        self::assertStringContainsString('href="prednasky/archiv"', $odpoved['telo']);
    }
}
