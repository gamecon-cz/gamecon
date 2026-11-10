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
                'DELETE FROM archiv_prednasek',
                <<<'SQL'
INSERT INTO archiv_prednasek(nazev, rok, youtube_id, skryta)
VALUES ('Viditelná přednáška v archivu', 2019, 'QaGjJIZJ4to', 0),
       ('Skrytá přednáška v archivu', 2019, NULL, 1)
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
