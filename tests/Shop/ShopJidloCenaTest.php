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

class ShopJidloCenaTest extends AbstractTestDb
{
    private function vytvorUzivatele(string $suffix): \Uzivatel
    {
        dbQuery(<<<SQL
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'JidloCena'
SQL,
            [
                0 => 'test_jidlo_cena_' . $suffix,
                1 => 'test.jidlo.cena.' . $suffix . '@example.org',
            ],
        );

        return \Uzivatel::zIdUrcite(dbInsertId());
    }

    private function vytvorPredmetJidlo(string $nazev, string $kodPredmetu, int $cena, int $den): int
    {
        dbQuery(<<<SQL
INSERT INTO shop_predmety SET
    nazev = $0,
    kod_predmetu = $1,
    model_rok = $2,
    cena_aktualni = $3,
    stav = $4,
    kusu_vyrobeno = NULL,
    typ = $5,
    ubytovani_den = $6
SQL,
            [
                0 => $nazev,
                1 => $kodPredmetu,
                2 => ROCNIK,
                3 => $cena,
                4 => StavPredmetu::VEREJNY,
                5 => TypPredmetu::JIDLO,
                6 => $den,
            ],
        );

        return dbInsertId();
    }

    /**
     * @test
     */
    public function zobraziCenuVecereIBezVecereVPoslednimDniTabulky(): void
    {
        $this->pripravXTemplateCache();
        $suffix = (string) uniqid();
        $uzivatel = $this->vytvorUzivatele($suffix);

        // Večeře není dostupná v posledním sloupci tabulky (neděle),
        // ale její cena se musí i tak zobrazit.
        $this->vytvorPredmetJidlo(
            'Večeře čtvrtek',
            'vecere_ctvrtek_' . $suffix,
            150,
            DateTimeGamecon::PORADI_HERNIHO_DNE_CTVRTEK,
        );
        $this->vytvorPredmetJidlo(
            'Snídaně neděle',
            'snidane_nedele_' . $suffix,
            80,
            DateTimeGamecon::PORADI_HERNIHO_DNE_NEDELE,
        );

        $shop = new Shop($uzivatel, $uzivatel, SystemoveNastaveni::zGlobals());
        $jidloHtml = $shop->jidloHtml(true);

        self::assertStringContainsString('Večeře', $jidloHtml);
        self::assertMatchesRegularExpression(
            '~Večeře\s*<div class="shop_popisCena">150&thinsp;Kč</div>~u',
            $jidloHtml,
        );
    }

    private function pripravXTemplateCache(): void
    {
        $cacheDir = XTemplate::cache() ?: XTPL_CACHE_DIR;
        pripravCache($cacheDir);
        XTemplate::cache($cacheDir);
    }
}
