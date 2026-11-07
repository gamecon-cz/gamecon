<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Web;

use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Web\CestyKSitemape;

class CestyKSitemapeTest extends AbstractTestDb
{
    /**
     * @test
     */
    public function obsahujeUvodniStrankuAVerejneModuly(): void
    {
        $cesty = (new CestyKSitemape())->getCesty();

        self::assertContains('', $cesty);
        foreach (['program', 'novinky', 'blog', 'vypraveci', 'sponzori'] as $modul) {
            self::assertContains($modul, $cesty);
        }
    }

    /**
     * @test
     */
    public function obsahujeJenLinieZobrazeneVMenu(): void
    {
        $cesty = (new CestyKSitemape())->getCesty();

        self::assertContains('larpy', $cesty);
        // linie s zobrazit_v_menu = 0 z migrací
        self::assertNotContains('workshopy', $cesty);
    }

    /**
     * @test
     */
    public function obsahujeStrankyZeSpravyKromePresmerovani(): void
    {
        dbQuery(
            <<<'SQL'
            INSERT INTO stranky (url_stranky, obsah, redirect)
            VALUES ('pro-sitemapu', '# Stránka', 0),
                   ('stary-odkaz', 'pro-sitemapu', 1)
            SQL,
        );

        $cesty = (new CestyKSitemape())->getCesty();

        self::assertContains('pro-sitemapu', $cesty);
        self::assertNotContains('stary-odkaz', $cesty);
    }

    /**
     * @test
     */
    public function nevypisujeStrankyKterymRouterNeodpovi(): void
    {
        dbQuery(
            <<<'SQL'
            INSERT INTO stranky (url_stranky, obsah, redirect)
            VALUES ('spatna_adresa', '# Stránka', 0),
                   ('cesta s mezerou', '# Stránka', 0),
                   ('program/podstranka', '# Stránka', 0),
                   ('api/stranka', '# Stránka', 0),
                   ('dobra/podstranka', '# Stránka', 0)
            SQL,
        );

        $cesty = (new CestyKSitemape())->getCesty();

        // Url::povolena() nepustí podtržítko ani mezeru a modul (soubor program.php, adresář api) vyhraje nad stránkou
        self::assertNotContains('spatna_adresa', $cesty);
        self::assertNotContains('cesta s mezerou', $cesty);
        self::assertNotContains('program/podstranka', $cesty);
        self::assertNotContains('api/stranka', $cesty);
        self::assertContains('dobra/podstranka', $cesty);
    }

    /**
     * @test
     */
    public function obsahujeVydaneBlogyAleNeBuduciAniNepovoleneAdresy(): void
    {
        dbQuery(
            <<<'SQL'
            INSERT INTO novinky (typ, vydat, url, nazev, text)
            VALUES (?, '2020-01-01 00:00:00', 'vydany-blog', 'Vydaný blog', 'text'),
                   (?, NOW() + INTERVAL 1 DAY, 'budouci-blog', 'Budoucí blog', 'text'),
                   (?, '2020-01-01 00:00:00', 'blog s mezerou', 'Blog s mezerou', 'text'),
                   (?, '2020-01-01 00:00:00', 'vydana-novinka', 'Vydaná novinka', 'text')
            SQL,
            [\Novinka::BLOG, \Novinka::BLOG, \Novinka::BLOG, \Novinka::NOVINKA],
        );

        $cesty = (new CestyKSitemape())->getCesty();

        self::assertContains('blog/vydany-blog', $cesty);
        self::assertNotContains('blog/budouci-blog', $cesty);
        self::assertNotContains('blog/blog s mezerou', $cesty);
        // novinka (ne blog) nemá vlastní stránku
        self::assertNotContains('blog/vydana-novinka', $cesty);
    }
}
