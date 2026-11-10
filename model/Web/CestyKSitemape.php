<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\Aktivita\TypAktivity;
use Gamecon\ArchivPrednasek\ArchivPrednasekStranka;

/**
 * The home page, the public modules, the lines of the menu, the archive of lectures (a page under its line, not a module
 * of its own address), the pages made in the administration (it decides what is public, a redirect is not a page)
 * and the published blog posts. Organiser profiles, the days of the program and the
 * pages of a line's organisers (`vypraveci/<line>`) stay out, crawlers reach them by links.
 */
class CestyKSitemape
{
    /**
     * Which module is a public page is written nowhere else, so the list is kept by hand.
     *
     * @see \Gamecon\Tests\Model\Web\ModulyVSitemapeTest compares it with web/moduly
     */
    public const array VEREJNE_MODULY = ['program', 'novinky', 'blog', 'vypraveci', 'sponzori'];

    /**
     * Duplicates are removed by the Sitemap, which compares canonical addresses.
     * Only the address of a page or a post is read, as they carry whole markdown bodies.
     *
     * @see \Novinka::zTypu() its condition of a published post is repeated here
     *
     * @return list<string> paths below the base URL, the empty one is the home page
     */
    public function getCesty(): array
    {
        $cestyLinii = array_map(
            static fn (TypAktivity $typAktivity) => $typAktivity->url(),
            TypAktivity::zViditelnych(),
        );
        $cestyStranek = dbOneArray(<<<'SQL'
            SELECT url_stranky
            FROM stranky
            WHERE redirect = 0
            ORDER BY url_stranky
            SQL);
        $adresyBlogu = dbOneArray(<<<'SQL'
            SELECT url
            FROM novinky
            WHERE vydat <= NOW()
                AND typ = $0
            ORDER BY vydat DESC
            SQL, [\Novinka::BLOG]);
        $cestyBlogu = array_map(static fn (string $adresaBlogu) => 'blog/' . $adresaBlogu, $adresyBlogu);

        return [
            '',
            ...self::VEREJNE_MODULY,
            ...array_filter([...$cestyLinii, ...$cestyStranek], $this->isCestaKterouRouterVrati(...)),
            ...array_filter([ArchivPrednasekStranka::getCestaZDatabaze()]),
            ...array_filter($cestyBlogu, \Url::povolena(...)),
        ];
    }

    /**
     * The router answers the address with the page it was made for only when the address is allowed
     * and its first part is not a module (a file or a directory in web/moduly), because a module wins over a page.
     *
     * @see \Modul::zNazvu() where the router looks for a module
     */
    private function isCestaKterouRouterVrati(string $cesta): bool
    {
        if (! \Url::povolena($cesta)) {
            return false;
        }
        $prvniCast = explode('/', $cesta)[0];

        return ! is_file(WWW . "/moduly/{$prvniCast}.php")
            && ! is_dir(WWW . "/moduly/{$prvniCast}");
    }
}
