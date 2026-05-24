<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Shop\Shop;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\XTemplate\XTemplate;

class ShopUbytovaniNabizetDoTest extends AbstractTestDb
{
    private function vytvorUzivatele(string $suffix): \Uzivatel
    {
        dbQuery(<<<SQL
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'UbytovaniNabizetDo'
SQL,
            [
                0 => 'test_ubytovani_nabizet_do_' . $suffix,
                1 => 'test.ubytovani.nabizet.do.' . $suffix . '@example.org',
            ],
        );

        return \Uzivatel::zIdUrcite(dbInsertId());
    }

    private function vytvorPredmetUbytovaniSPrezitymNabizetDo(string $suffix): int
    {
        dbQuery(<<<SQL
INSERT INTO shop_predmety SET
    nazev = $0,
    kod_predmetu = $1,
    model_rok = $2,
    cena_aktualni = 500,
    stav = $3,
    nabizet_do = $4,
    kusu_vyrobeno = 10,
    typ = $5,
    ubytovani_den = $6
SQL,
            [
                0 => 'Spacák čtvrtek',
                1 => 'SPACAK_CTVRTEK_' . $suffix,
                2 => ROCNIK,
                3 => StavPredmetu::VEREJNY,
                4 => '2000-01-01 00:00:00', // historické datum, které by dřív položku zablokovalo
                5 => TypPredmetu::UBYTOVANI,
                6 => DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
            ],
        );

        return dbInsertId();
    }

    /**
     * @test
     */
    public function ubytovaniNeniZakazaneJenKvuliNabizetDoVUbytovaciPolozce(): void
    {
        $this->pripravXTemplateCache();
        $suffix = (string) uniqid();
        $uzivatel = $this->vytvorUzivatele($suffix);
        $idPredmetu = $this->vytvorPredmetUbytovaniSPrezitymNabizetDo($suffix);

        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $shop = new Shop($uzivatel, $uzivatel, $systemoveNastaveni);

        $html = $shop->ubytovaniHtml(true);
        preg_match(
            '~<input[^>]*class="shopUbytovani_radio"[^>]*value="' . preg_quote((string) $idPredmetu, '~') . '"[^>]*>~u',
            $html,
            $inputTag,
        );

        self::assertNotEmpty($inputTag, 'V HTML ubytování chybí input pro testovací položku');
        self::assertStringNotContainsString('disabled', $inputTag[0]);
    }

    private function pripravXTemplateCache(): void
    {
        $cacheDir = XTemplate::cache() ?: XTPL_CACHE_DIR;
        pripravCache($cacheDir);
        XTemplate::cache($cacheDir);
    }
}
