<?php

declare(strict_types=1);

namespace Gamecon\Tests\Db\Migrace;

use Gamecon\Tests\Db\AbstractTestDb;
use Godric\DbMigrations\Migration;

/**
 * The English content of the photo galleries of the lines is made from the Czech one by REPLACE(), so it has to work
 * on any gallery: the embed code stays, the visible texts are English, and nothing else is touched.
 */
class AnglickeFotogalerieLiniiMigraceTest extends AbstractTestDb
{
    private const string MIGRACE = '2026-11-20-124420_anglicke-fotogalerie-linii.php';

    /**
     * the shape of a gallery on ostra: a heading and Instagram's embed with its placeholder, CRLF from the admin editor
     */
    private const string CESKA_GALERIE = "# Fotogalerie\r\n\r\n<div class=\"ig-container\">\r\n\r\n"
        . '<blockquote class="instagram-media" data-instgrm-permalink="https://www.instagram.com/p/TEST123/"><div>'
        . '<div style="font-weight:550;">Zobrazit příspěvek na Instagramu</div>'
        . '<p><a href="https://www.instagram.com/p/TEST123/" target="_blank">Příspěvek sdílený GameCon ???? (@gamecon_cz)</a></p>'
        . "</div></blockquote> <script async src=\"//www.instagram.com/embed.js\"></script>\r\n\r\n</div>\r\n";

    /**
     * @test
     */
    public function galerieDostaneAnglickyObsahZCeskeho(): void
    {
        $this->vlozStranku('testovacilinie/fotogalerie', self::CESKA_GALERIE, null);

        $this->spustMigraci();

        $anglicky = $this->obsahEn('testovacilinie/fotogalerie');
        self::assertSame(
            "# Photo gallery\r\n\r\n<div class=\"ig-container\">\r\n\r\n"
            . '<blockquote class="instagram-media" data-instgrm-permalink="https://www.instagram.com/p/TEST123/"><div>'
            . '<div style="font-weight:550;">View this post on Instagram</div>'
            . '<p><a href="https://www.instagram.com/p/TEST123/" target="_blank">A post shared by GameCon ???? (@gamecon_cz)</a></p>'
            . "</div></blockquote> <script async src=\"//www.instagram.com/embed.js\"></script>\r\n\r\n</div>\r\n",
            $anglicky,
        );
        self::assertSame(self::CESKA_GALERIE, $this->obsah('testovacilinie/fotogalerie'), 'Český obsah se nemění.');
    }

    /**
     * @test
     */
    public function prazdnyAnglickyObsahSeDoplni(): void
    {
        $this->vlozStranku('testovacilinie/fotogalerie', self::CESKA_GALERIE, '');

        $this->spustMigraci();

        self::assertStringStartsWith('# Photo gallery', (string) $this->obsahEn('testovacilinie/fotogalerie'), 'Formulář v adminu ukládá prázdný řetězec, ne NULL.');
    }

    /**
     * @test
     */
    public function vlastniAnglickyObsahZustane(): void
    {
        $this->vlozStranku('testovacilinie/fotogalerie', self::CESKA_GALERIE, "# Our photos\r\n\r\nHand-written.");

        $this->spustMigraci();

        self::assertSame("# Our photos\r\n\r\nHand-written.", $this->obsahEn('testovacilinie/fotogalerie'));
    }

    /**
     * @test
     */
    public function galerieSJinymNadpisemZustaneCeska(): void
    {
        $this->vlozStranku('testovacilinie/fotogalerie', str_replace('# Fotogalerie', '# Fotky z GameConu', self::CESKA_GALERIE), null);

        $this->spustMigraci();

        self::assertNull($this->obsahEn('testovacilinie/fotogalerie'), 'Anglický obsah by měl český nadpis a stránka by se hlásila jako přeložená.');
    }

    /**
     * @test
     */
    public function jinaStrankaSeNemeni(): void
    {
        $this->vlozStranku('testovaci-fotogalerie-mimo-linii', self::CESKA_GALERIE, null);

        $this->spustMigraci();

        self::assertNull($this->obsahEn('testovaci-fotogalerie-mimo-linii'));
    }

    private function spustMigraci(): void
    {
        (new Migration(__DIR__ . '/../../../migrace/' . self::MIGRACE, self::MIGRACE, dbConnect()))->apply();
    }

    private function vlozStranku(string $url, string $obsah, ?string $obsahEn): void
    {
        dbQuery(<<<'SQL'
INSERT INTO stranky (url_stranky, obsah, obsah_en, poradi) VALUES ($0, $1, $2, 0)
SQL,
            [
                0 => $url,
                1 => $obsah,
                2 => $obsahEn,
            ],
        );
    }

    private function obsah(string $url): string
    {
        return (string) dbOneCol('SELECT obsah FROM stranky WHERE url_stranky = $0', [
            0 => $url,
        ]);
    }

    private function obsahEn(string $url): ?string
    {
        $obsahEn = dbOneLine('SELECT obsah_en FROM stranky WHERE url_stranky = $0', [
            0 => $url,
        ])['obsah_en'] ?? null;

        return $obsahEn === null
            ? null
            : (string) $obsahEn;
    }
}
