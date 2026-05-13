<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Pravo;
use Gamecon\Shop\Shop;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\XTemplate\XTemplate;

class ShopTrickaCenaTest extends AbstractTestDb
{
    private function systemoveNastaveniProShop(): SystemoveNastaveni
    {
        $systemoveNastaveni = SystemoveNastaveni::zGlobals(
            rocnik: ROCNIK,
            ted: new DateTimeImmutableStrict(ROCNIK . '-01-01 00:00:00'),
        );
        foreach ([
            'MIKINY_LZE_OBJEDNAT_A_MENIT_DO_DNE',
            'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE',
            'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE',
        ] as $klic) {
            try_define($klic, $systemoveNastaveni->dejVychoziHodnotu($klic));
        }

        return $systemoveNastaveni;
    }

    private function vytvorUzivatele(string $suffix): \Uzivatel
    {
        dbQuery(<<<SQL
INSERT INTO uzivatele_hodnoty SET
    login_uzivatele = $0,
    email1_uzivatele = $1,
    jmeno_uzivatele = 'Test',
    prijmeni_uzivatele = 'TrickaCena'
SQL,
            [
                0 => 'test_tricka_cena_' . $suffix,
                1 => 'test.tricka.cena.' . $suffix . '@example.org',
            ],
        );

        return \Uzivatel::zIdUrcite(dbInsertId());
    }

    private function pridelPravo(\Uzivatel $uzivatel, int $idPrava): \Uzivatel
    {
        $unique = uniqid('', false);
        $idRole = -random_int(100000, 999999);
        $kodRole = 'TEST_TRICKA_CENA_' . $idPrava . '_' . $unique;
        dbQuery(<<<SQL
INSERT IGNORE INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava)
VALUES ($0, $1, 'test')
SQL,
            [
                0 => $idPrava,
                1 => 'test_pravo_' . $idPrava,
            ],
        );
        dbQuery(<<<SQL
INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES ($0, $1, $2, '', -1, 'trvala', '')
SQL,
            [
                0 => $idRole,
                1 => $kodRole,
                2 => 'Test role ' . $unique,
            ],
        );
        dbQuery(
            'INSERT INTO prava_role(id_role, id_prava) VALUES ($0, $1)',
            [$idRole, $idPrava],
        );
        dbQuery(
            'INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES ($0, $1, $0)',
            [$uzivatel->id(), $idRole],
        );

        \Uzivatel::smazCache();

        return \Uzivatel::zIdUrcite($uzivatel->id());
    }

    private function vytvorTricko(
        string $nazev,
        string $kodPredmetu,
        int    $cena,
        int    $stav = StavPredmetu::VEREJNY,
    ): int {
        dbQuery(<<<SQL
INSERT INTO shop_predmety SET
    nazev = $0,
    kod_predmetu = $1,
    model_rok = $2,
    cena_aktualni = $3,
    stav = $4,
    kusu_vyrobeno = $5,
    typ = $6
SQL,
            [
                0 => $nazev,
                1 => $kodPredmetu,
                2 => ROCNIK,
                3 => $cena,
                4 => $stav,
                5 => 10,
                6 => TypPredmetu::TRICKO,
            ],
        );

        return dbInsertId();
    }

    private function objednejPredmet(\Uzivatel $uzivatel, int $idPredmetu): void
    {
        dbQuery(<<<SQL
INSERT INTO shop_nakupy SET
    id_uzivatele = $0,
    id_objednatele = $0,
    id_predmetu = $1,
    rok = $2,
    cena_nakupni = (SELECT cena_aktualni FROM shop_predmety WHERE id_predmetu = $1),
    datum = NOW()
SQL,
            [$uzivatel->id(), $idPredmetu, ROCNIK],
        );
    }

    private function pripravXTemplateCache(): void
    {
        $cacheDir = XTemplate::cache() ?: XTPL_CACHE_DIR;
        pripravCache($cacheDir);
        XTemplate::cache($cacheDir);
    }

    /**
     * @test
     */
    public function vybranaCervenaVariantaTrickaZobraziSvojiCenu(): void
    {
        $this->pripravXTemplateCache();
        $suffix = (string)uniqid();
        $uzivatel = $this->vytvorUzivatele($suffix);
        $uzivatel = $this->pridelPravo($uzivatel, Pravo::MUZE_OBJEDNAVAT_CERVENA_TRICKA);

        $this->vytvorTricko('Tričko účastnické pánské L', 'tricko_ucastnicke_l_' . $suffix, 400);
        $idCervenehoTricka = $this->vytvorTricko(
            'Tričko červené pánské L',
            'tricko_cervene_l_' . $suffix,
            200,
            StavPredmetu::PODPULTOVY,
        );
        $this->objednejPredmet($uzivatel, $idCervenehoTricka);

        $shop = new Shop($uzivatel, $uzivatel, $this->systemoveNastaveniProShop());
        $html = $shop->predmetyHtml();

        self::assertMatchesRegularExpression(
            '~<div class="shop_popisCena">200&thinsp;Kč</div>.*<option value="' . $idCervenehoTricka . '" data-cena="200&thinsp;Kč" selected>Tričko červené pánské L</option>~s',
            $html,
        );
    }

    /**
     * @test
     */
    public function barevneVariantyTricekMajiVeVyberuVlastniCeny(): void
    {
        $this->pripravXTemplateCache();
        $suffix = (string)uniqid();
        $uzivatel = $this->vytvorUzivatele($suffix);
        $uzivatel = $this->pridelPravo($uzivatel, Pravo::MUZE_OBJEDNAVAT_MODRA_TRICKA);
        $uzivatel = $this->pridelPravo($uzivatel, Pravo::MUZE_OBJEDNAVAT_CERVENA_TRICKA);

        $idUcastnickehoTricka = $this->vytvorTricko('Tričko účastnické pánské L', 'tricko_ucastnicke_l_' . $suffix, 400);
        $idModrehoTricka = $this->vytvorTricko(
            'Tričko modré pánské L',
            'tricko_modre_l_' . $suffix,
            200,
            StavPredmetu::PODPULTOVY,
        );
        $idCervenehoTricka = $this->vytvorTricko(
            'Tričko červené pánské L',
            'tricko_cervene_l_' . $suffix,
            200,
            StavPredmetu::PODPULTOVY,
        );

        $shop = new Shop($uzivatel, $uzivatel, $this->systemoveNastaveniProShop());
        $html = $shop->predmetyHtml();

        self::assertStringContainsString('data-vychozi-cena="200-400&thinsp;Kč"', $html);
        self::assertStringContainsString('value="' . $idUcastnickehoTricka . '" data-cena="400&thinsp;Kč"', $html);
        self::assertStringContainsString('value="' . $idModrehoTricka . '" data-cena="200&thinsp;Kč"', $html);
        self::assertStringContainsString('value="' . $idCervenehoTricka . '" data-cena="200&thinsp;Kč"', $html);
    }
}