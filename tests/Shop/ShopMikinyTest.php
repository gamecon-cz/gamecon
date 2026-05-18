<?php

declare(strict_types=1);

namespace Gamecon\Tests\Shop;

use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Shop\PodtypPredmetu;
use Gamecon\Shop\Shop;
use Gamecon\Shop\StavPredmetu;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\XTemplate\XTemplate;

class ShopMikinyTest extends AbstractTestDb
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
    prijmeni_uzivatele = 'Mikiny'
SQL,
            [
                0 => 'test_mikiny_' . $suffix,
                1 => 'test.mikiny.' . $suffix . '@example.org',
            ],
        );

        return \Uzivatel::zIdUrcite(dbInsertId());
    }

    private function vytvorPredmet(
        string $nazev,
        string $kodPredmetu,
        int $typ,
        ?string $podtyp = null,
        int $cena = 650,
    ): int {
        dbQuery(<<<SQL
INSERT INTO shop_predmety SET
    nazev = $0,
    kod_predmetu = $1,
    cena_aktualni = $2,
    stav = $3,
    kusu_vyrobeno = $4
SQL,
            [
                0 => $nazev,
                1 => $kodPredmetu,
                2 => $cena,
                3 => StavPredmetu::VEREJNY,
                4 => 10,
            ],
        );
        $idPredmetu = dbInsertId();

        $tagCode = match ($typ) {
            TypPredmetu::PREDMET           => 'predmet',
            TypPredmetu::UBYTOVANI         => 'ubytovani',
            TypPredmetu::TRICKO            => 'tricko',
            TypPredmetu::JIDLO             => 'jidlo',
            TypPredmetu::VSTUPNE           => 'vstupne',
            TypPredmetu::PARCON            => 'parcon',
            TypPredmetu::PROPLACENI_BONUSU => 'proplaceni-bonusu',
        };
        dbQuery(
            "INSERT INTO product_product_tag (product_id, tag_id) SELECT $0, id FROM product_tag WHERE code = $1",
            [0 => $idPredmetu, 1 => $tagCode],
        );

        if ($podtyp === PodtypPredmetu::MIKINA) {
            dbQuery(
                "INSERT INTO product_product_tag (product_id, tag_id) SELECT $0, id FROM product_tag WHERE code = 'mikina'",
                [0 => $idPredmetu],
            );
        }

        return $idPredmetu;
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
    public function vyberMikinSeZobraziJenPriImportovanychMikinach(): void
    {
        $this->pripravXTemplateCache();
        $suffix = (string) uniqid();
        $uzivatel = $this->vytvorUzivatele($suffix);

        $this->vytvorPredmet('Placka drak', 'placka_drak_' . $suffix, TypPredmetu::PREDMET, null, 50);

        $shopBezMikin = new Shop($uzivatel, $uzivatel, $this->systemoveNastaveniProShop());
        $htmlBezMikin = $shopBezMikin->predmetyHtml();
        self::assertStringNotContainsString('Mikina GameCon', $htmlBezMikin);

        $this->vytvorPredmet('Mikina černá S', 'mikina_cerna_s_' . $suffix, TypPredmetu::PREDMET, PodtypPredmetu::MIKINA, 900);
        $this->vytvorPredmet('Mikina černá M', 'mikina_cerna_m_' . $suffix, TypPredmetu::PREDMET, PodtypPredmetu::MIKINA, 900);

        $shopSMikinami = new Shop($uzivatel, $uzivatel, $this->systemoveNastaveniProShop());
        $htmlSMikinami = $shopSMikinami->predmetyHtml();

        self::assertStringContainsString('Mikina GameCon ' . ROCNIK, $htmlSMikinami);
        self::assertStringContainsString('name="shopM[0]"', $htmlSMikinami);
        self::assertStringContainsString('Mikina černá S', $htmlSMikinami);
        self::assertStringContainsString('Mikina černá M', $htmlSMikinami);
    }

    /**
     * @test
     */
    public function zpracujeVyberMikinyIBezTricekVeFormulari(): void
    {
        $suffix = (string) uniqid();
        $uzivatel = $this->vytvorUzivatele($suffix);
        $idMikiny = $this->vytvorPredmet('Mikina vínová L', 'mikina_vinova_l_' . $suffix, TypPredmetu::PREDMET, PodtypPredmetu::MIKINA, 950);

        $shop = new Shop($uzivatel, $uzivatel, $this->systemoveNastaveniProShop());

        $puvodniPost = $_POST;
        try {
            $_POST = [
                'shopM' => [
                    0 => (string) $idMikiny,
                ],
            ];

            $shop->zpracujPredmety();
        } finally {
            $_POST = $puvodniPost;
        }

        $pocetNakupu = (int) dbOneCol(
            'SELECT COUNT(*) FROM shop_nakupy WHERE id_uzivatele = $0 AND id_predmetu = $1 AND rok = $2',
            [$uzivatel->id(), $idMikiny, ROCNIK],
        );

        self::assertSame(1, $pocetNakupu);
    }

    /**
     * @test
     */
    public function koupenaMikinaSePocitaDoPrehleduNakupu(): void
    {
        $this->pripravXTemplateCache();
        $suffix = (string) uniqid();
        $uzivatel = $this->vytvorUzivatele($suffix);
        $idMikiny = $this->vytvorPredmet('Mikina šedá XL', 'mikina_seda_xl_' . $suffix, TypPredmetu::PREDMET, PodtypPredmetu::MIKINA, 990);
        $this->objednejPredmet($uzivatel, $idMikiny);

        $shop = new Shop($uzivatel, $uzivatel, $this->systemoveNastaveniProShop());

        self::assertTrue($shop->koupilNejakouVec());
        self::assertStringContainsString('Mikina šedá XL', $shop->koupeneVeciPrehledHtml());
    }
}
