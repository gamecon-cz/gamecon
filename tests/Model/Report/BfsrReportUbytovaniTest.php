<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use App\Enum\ProductTagCode;
use Gamecon\Report\BfsrReport;
use Gamecon\Shop\Predmet;
use Gamecon\Shop\SqlStruktura\PredmetSqlStruktura as PredmetSql;
use Gamecon\Shop\TypPredmetu;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\Uzivatel\Dto\PolozkaProBfgr;
use Gamecon\Uzivatel\Finance;

class BfsrReportUbytovaniTest extends AbstractTestDb
{
    /**
     * Sortiment roku 2026, na kterém report spadl.
     */
    private const PREDMETY = [
        ['Hs-1L-st', TypPredmetu::UBYTOVANI],
        ['Hs-2L-st', TypPredmetu::UBYTOVANI],
        ['Hd-1L-st', TypPredmetu::UBYTOVANI],
        ['Hd-2L-st', TypPredmetu::UBYTOVANI],
        ['Hdb-1L-st', TypPredmetu::UBYTOVANI],
        ['4L_chataRichor_st', TypPredmetu::UBYTOVANI],
        ['2_4L_penzionWitch_st', TypPredmetu::UBYTOVANI],
        ['vlastni_stan_st', TypPredmetu::UBYTOVANI],
        ['spacak_st', TypPredmetu::UBYTOVANI],
        ['3L_st', TypPredmetu::UBYTOVANI],
        ['2L_st', TypPredmetu::UBYTOVANI],
        ['1L_st', TypPredmetu::UBYTOVANI],
        ['mikina_2026_verne_l', TypPredmetu::PREDMET],
        ['taska_2022', TypPredmetu::PREDMET],
        ['nicknack_2019', TypPredmetu::PREDMET],
        ['blok_2023', TypPredmetu::PREDMET],
        ['ponozky_2021_vel_38_39', TypPredmetu::PREDMET],
        ['kostka_2026_verne', TypPredmetu::PREDMET],
        ['placka_2026_verne', TypPredmetu::PREDMET],
    ];

    protected static function getBeforeClassInitCallbacks(): array
    {
        return [
            static function () {
                foreach (self::PREDMETY as [$kodPredmetu, $typ]) {
                    dbInsert(PredmetSql::SHOP_PREDMETY_TABULKA, [
                        PredmetSql::NAZEV         => $kodPredmetu,
                        PredmetSql::KOD_PREDMETU  => $kodPredmetu,
                        PredmetSql::CENA_AKTUALNI => 100,
                        PredmetSql::STAV          => 1,
                        PredmetSql::POPIS         => '',
                    ]);

                    // Typ je nově tag a ročník se odvozuje z archived_at (NULL = letošní),
                    // takže ani jedno už nejde vložit jako sloupec.
                    $kodTagu = ProductTagCode::fromLegacyTyp($typ);
                    if ($kodTagu === null) {
                        throw new \LogicException('Pro typ předmětu ' . $typ . ' neexistuje tag');
                    }
                    $vlozeni = dbQuery(
                        'INSERT INTO product_product_tag (product_id, tag_id)
                         SELECT $0, id FROM product_tag WHERE code = $1',
                        [
                            0 => dbInsertId(),
                            1 => $kodTagu->value,
                        ],
                    );
                    // Bez tagu v databázi vloží INSERT ... SELECT tiše nula řádků a předmět
                    // pak z pohledu vyjde s typ = NULL — spadne až vzdálená assertion.
                    if (dbAffectedOrNumRows($vlozeni) !== 1) {
                        throw new \LogicException('Tag ' . $kodTagu->value . ' v databázi není');
                    }
                }
            },
        ];
    }

    /**
     * Report pro každý kód ubytování zvyšuje jeden čítač; kód, který nezná,
     * shodí celý report výjimkou.
     *
     * @test
     */
    public function kazdyKodUbytovaniVDatabaziSpadaDoNejakehoDruhu(): void
    {
        $kodyUbytovani = dbOneArray(<<<SQL
            SELECT DISTINCT kod_predmetu
            FROM shop_predmety_s_typem
            WHERE typ = $0
            SQL,
            [
                0 => TypPredmetu::UBYTOVANI,
            ],
        );
        self::assertNotEmpty($kodyUbytovani, 'V databázi není žádné ubytování, test by nic neověřil');

        $predpony = array_column(
            (new \ReflectionClass(BfsrReport::class))->getConstant('DRUHY_UBYTOVANI'),
            0,
        );

        foreach ($kodyUbytovani as $kodPredmetu) {
            $zname = false;
            foreach ($predpony as $predpona) {
                if (str_starts_with((string) $kodPredmetu, $predpona)) {
                    $zname = true;
                    break;
                }
            }
            self::assertTrue(
                $zname,
                "Kód ubytování '{$kodPredmetu}' nespadá do žádného druhu, report na něm spadne",
            );
        }
    }

    /**
     * Finance vydávají tyhle řádky společně s nákupy, ale nejsou to předměty –
     * mají prázdný kód, takže je report bez přeskočení nedokáže zařadit a spadne.
     *
     * @test
     *
     * @dataProvider ucetniRadkyMimoShop
     */
    public function ucetniRadkyInfopultuReportPreskoci(
        int $typ,
        string $nazev,
    ): void {
        $polozka = new PolozkaProBfgr(
            nazev: $nazev,
            pocet: '1',
            castka: -100.0,
            sleva: 0.0,
            typ: $typ,
            kodPredmetu: '',
            idPredmetu: '',
        );

        self::assertTrue(
            BfsrReport::jeUcetniRadekMimoShop($polozka),
            "Účetní řádek '{$nazev}' (typ {$typ}) report nepřeskočí a spadne na něm",
        );
    }

    /**
     * @return array<string, array{int, string}>
     */
    public static function ucetniRadkyMimoShop(): array
    {
        return [
            'zůstatek z minulých let' => [Finance::ZUSTATEK_Z_PREDCHOZICH_LET, 'Zůstatek z minulých let'],
            'aktivity'                => [Finance::AKTIVITY, 'Aktivita'],
            'připsané slevy'          => [Finance::PRIPSANE_SLEVY, 'Sleva'],
            'bonus za aktivity'       => [Finance::ORGSLEVA, 'Bonus za aktivity'],
            'brigádnická odměna'      => [Finance::BRIGADNICKA_ODMENA, 'Brigádnická odměna'],
            'platba'                  => [Finance::PLATBA, 'Platba'],
        ];
    }

    /**
     * Skutečný nákup v shopu se přeskočit nesmí, jinak by report přestal počítat.
     *
     * @test
     */
    public function nakupVShopuSeNepreskoci(): void
    {
        $polozka = new PolozkaProBfgr(
            nazev: 'Mikina L',
            pocet: '1',
            castka: 800.0,
            sleva: 0.0,
            typ: TypPredmetu::PREDMET,
            kodPredmetu: 'mikina_2026_verne_l',
            idPredmetu: '1',
        );

        self::assertFalse(BfsrReport::jeUcetniRadekMimoShop($polozka));
    }

    /**
     * @test
     */
    public function mikinaJeRozpoznanaPodleKodu(): void
    {
        self::assertTrue(Predmet::jeToMikina('mikina_2026_verne_l'));
        self::assertFalse(Predmet::jeToMikina('tricko_2026_verne_l'));
    }

    /**
     * Prodávaný předmět, který report nezná, shodí generování pro všechny.
     *
     * @test
     */
    public function kazdyLetosProdavanyPredmetJeReportuZnamy(): void
    {
        $predmety = dbFetchAll(<<<SQL
            SELECT kod_predmetu, typ
            FROM shop_predmety_s_typem
            WHERE typ = $0
            SQL,
            [
                0 => TypPredmetu::PREDMET,
            ],
        );
        self::assertNotEmpty($predmety, 'V databázi nejsou žádné předměty, test by nic neověřil');

        foreach ($predmety as $predmet) {
            $kodPredmetu = (string) $predmet[PredmetSql::KOD_PREDMETU];
            $typ = (int) $predmet[PredmetSql::TYP];

            $zname = Predmet::jeToTricko($kodPredmetu, $typ)
                || Predmet::jeToTilko($kodPredmetu, $typ)
                || Predmet::jeToPlacka($kodPredmetu)
                || Predmet::jeToKostka($kodPredmetu)
                || Predmet::jeToNicknack($kodPredmetu)
                || Predmet::jeToBlok($kodPredmetu)
                || Predmet::jeToPonozka($kodPredmetu)
                || Predmet::jeToTaska($kodPredmetu)
                || Predmet::jeToMikina($kodPredmetu);

            self::assertTrue(
                $zname,
                "Předmět '{$kodPredmetu}' report nezná, spadne na něm",
            );
        }
    }
}
