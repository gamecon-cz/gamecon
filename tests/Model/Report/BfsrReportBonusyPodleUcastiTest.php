<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Report;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Pravo;
use Gamecon\Report\BfsrReport;
use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveniKlice;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * The bonus rows follow what Finance credits to balances: leadership needs a registration for
 * the festival, participation on technical and part-time activities needs no GC role at all.
 * People registered who never arrived at the infopult are reported in one extra row, and only
 * once the festival has ended.
 */
class BfsrReportBonusyPodleUcastiTest extends AbstractTestDb
{
    private const ID_ORG_PRIHLASEN = 4801;
    private const ID_ORG_PRITOMEN = 4802;
    private const ID_ORG_NEPRIHLASEN = 4803;
    private const ID_FULLORG_PRIHLASEN = 4804;
    private const ID_FULLORG_PRITOMEN = 4805;
    private const ID_TECH_PRIHLASEN = 4806;
    private const ID_TECH_PRITOMEN = 4807;
    private const ID_TECH_NEPRIHLASEN = 4808;
    private const ID_BRIG_PRIHLASEN = 4809;
    private const ID_BRIG_PRITOMEN = 4810;
    private const ID_BRIG_NEPRIHLASEN = 4811;
    private const ID_ORG_BEZ_PRAVA_PORADAT = 4812;

    private const ID_AKTIVITA_VEDENI = 48001;
    private const ID_AKTIVITA_TECHNICKA = 48002;
    private const ID_AKTIVITA_BRIGADNICKA = 48003;
    private const ID_AKTIVITA_NEDAVA_BONUS = 48004;

    private const ID_ROLE_BEZ_BONUSU = -48010001;
    private const ID_ROLE_PORADANI_AKTIVIT = -48010002;

    private const CENA_TECHNICKE = 200;
    private const CENA_BRIGADNICKE = 300;

    /**
     * @return list<string|array{string, array<int, mixed>}>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $queries = [];

        foreach (self::vsichniUzivatele() as $idUzivatele) {
            $queries[] = [
                <<<SQL
INSERT INTO uzivatele_hodnoty SET id_uzivatele = $0, login_uzivatele = $1, jmeno_uzivatele = 'Bonusy', prijmeni_uzivatele = $1, email1_uzivatele = $2
SQL,
                [
                    0 => $idUzivatele,
                    1 => "BfsrBonusy{$idUzivatele}",
                    2 => "bfsr.bonusy.{$idUzivatele}@example.org",
                ],
            ];
        }

        // The test DB knows this year's registered/arrived roles from migrations, the part-timer role has to be created here.
        $idRoleBrigadnik = Role::LETOSNI_BRIGADNIK();
        $queries[] = [
            <<<SQL
INSERT IGNORE INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES($0, $1, 'Brigádník (test)', '', $2, 'rocnikova', $3)
SQL,
            [
                0 => $idRoleBrigadnik,
                1 => 'TEST_BRIGADNIK_' . ROCNIK,
                2 => ROCNIK,
                3 => Role::VYZNAM_BRIGADNIK,
            ],
        ];

        $queries[] = [
            "INSERT IGNORE INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava) VALUES ($0, 'test_bez_bonusu', 'test')",
            [
                0 => Pravo::BEZ_BONUSU_ZA_VEDENI_AKTIVIT,
            ],
        ];
        $queries[] = [
            <<<SQL
INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES($0, 'TEST_BFSR_BEZ_BONUSU', 'Test bez bonusu', '', -1, 'trvala', '')
SQL,
            [
                0 => self::ID_ROLE_BEZ_BONUSU,
            ],
        ];
        $queries[] = [
            'INSERT INTO prava_role(id_role, id_prava) VALUES ($0, $1)',
            [
                0 => self::ID_ROLE_BEZ_BONUSU,
                1 => Pravo::BEZ_BONUSU_ZA_VEDENI_AKTIVIT,
            ],
        ];

        $queries[] = [
            "INSERT IGNORE INTO r_prava_soupis(id_prava, jmeno_prava, popis_prava) VALUES ($0, 'test_poradani_aktivit', 'test')",
            [
                0 => Pravo::PORADANI_AKTIVIT,
            ],
        ];
        $queries[] = [
            <<<SQL
INSERT INTO role_seznam(id_role, kod_role, nazev_role, popis_role, rocnik_role, typ_role, vyznam_role)
VALUES($0, 'TEST_BFSR_PORADANI_AKTIVIT', 'Test poradani aktivit', '', -1, 'trvala', '')
SQL,
            [
                0 => self::ID_ROLE_PORADANI_AKTIVIT,
            ],
        ];
        $queries[] = [
            'INSERT INTO prava_role(id_role, id_prava) VALUES ($0, $1)',
            [
                0 => self::ID_ROLE_PORADANI_AKTIVIT,
                1 => Pravo::PORADANI_AKTIVIT,
            ],
        ];

        $prihlaseni = [
            self::ID_ORG_PRIHLASEN,
            self::ID_ORG_PRITOMEN,
            self::ID_ORG_BEZ_PRAVA_PORADAT,
            self::ID_FULLORG_PRIHLASEN,
            self::ID_FULLORG_PRITOMEN,
            self::ID_TECH_PRIHLASEN,
            self::ID_TECH_PRITOMEN,
            self::ID_BRIG_PRIHLASEN,
            self::ID_BRIG_PRITOMEN,
        ];
        $pritomni = [
            self::ID_ORG_PRITOMEN,
            self::ID_FULLORG_PRITOMEN,
            self::ID_TECH_PRITOMEN,
            self::ID_BRIG_PRITOMEN,
        ];
        $brigadnici = [
            self::ID_BRIG_PRIHLASEN,
            self::ID_BRIG_PRITOMEN,
            self::ID_BRIG_NEPRIHLASEN,
        ];

        foreach ($prihlaseni as $idUzivatele) {
            $queries[] = self::prideleniRole($idUzivatele, Role::PRIHLASEN_NA_LETOSNI_GC);
        }
        foreach ($pritomni as $idUzivatele) {
            $queries[] = self::prideleniRole($idUzivatele, Role::pritomenNaRocniku(ROCNIK));
        }
        foreach ($brigadnici as $idUzivatele) {
            $queries[] = self::prideleniRole($idUzivatele, $idRoleBrigadnik);
        }
        foreach ([self::ID_FULLORG_PRIHLASEN, self::ID_FULLORG_PRITOMEN] as $idUzivatele) {
            $queries[] = self::prideleniRole($idUzivatele, self::ID_ROLE_BEZ_BONUSU);
        }
        $vsichniKdoMohouPoradat = [
            self::ID_ORG_PRIHLASEN,
            self::ID_ORG_PRITOMEN,
            self::ID_ORG_NEPRIHLASEN,
            self::ID_FULLORG_PRIHLASEN,
            self::ID_FULLORG_PRITOMEN,
        ];
        foreach ($vsichniKdoMohouPoradat as $idUzivatele) {
            $queries[] = self::prideleniRole($idUzivatele, self::ID_ROLE_PORADANI_AKTIVIT);
        }

        $queries[] = self::aktivita(self::ID_AKTIVITA_VEDENI, TypAktivity::PREDNASKA, 0);
        $queries[] = self::aktivita(self::ID_AKTIVITA_TECHNICKA, TypAktivity::TECHNICKA, self::CENA_TECHNICKE);
        $queries[] = self::aktivita(self::ID_AKTIVITA_BRIGADNICKA, TypAktivity::BRIGADNICKA, self::CENA_BRIGADNICKE);
        $queries[] = self::aktivita(self::ID_AKTIVITA_NEDAVA_BONUS, TypAktivity::PREDNASKA, 0, nedavaBonus: true);

        foreach ([self::ID_ORG_PRIHLASEN, self::ID_ORG_PRITOMEN, self::ID_FULLORG_PRIHLASEN] as $idOrganizatora) {
            $queries[] = [
                'INSERT INTO akce_organizatori(id_akce, id_uzivatele) VALUES ($0, $1)',
                [
                    0 => self::ID_AKTIVITA_NEDAVA_BONUS,
                    1 => $idOrganizatora,
                ],
            ];
        }
        foreach ([
            self::ID_ORG_PRIHLASEN,
            self::ID_ORG_PRITOMEN,
            self::ID_ORG_NEPRIHLASEN,
            self::ID_ORG_BEZ_PRAVA_PORADAT,
            self::ID_FULLORG_PRIHLASEN,
            self::ID_FULLORG_PRITOMEN,
        ] as $idOrganizatora) {
            $queries[] = [
                'INSERT INTO akce_organizatori(id_akce, id_uzivatele) VALUES ($0, $1)',
                [
                    0 => self::ID_AKTIVITA_VEDENI,
                    1 => $idOrganizatora,
                ],
            ];
        }
        foreach ([self::ID_AKTIVITA_TECHNICKA, self::ID_AKTIVITA_BRIGADNICKA] as $idAktivity) {
            foreach ([self::ID_ORG_PRIHLASEN, self::ID_ORG_PRITOMEN, self::ID_ORG_NEPRIHLASEN] as $idOrganizatora) {
                $queries[] = [
                    'INSERT INTO akce_organizatori(id_akce, id_uzivatele) VALUES ($0, $1)',
                    [
                        0 => $idAktivity,
                        1 => $idOrganizatora,
                    ],
                ];
            }
        }
        foreach ([self::ID_TECH_PRIHLASEN, self::ID_TECH_PRITOMEN, self::ID_TECH_NEPRIHLASEN] as $idUcastnika) {
            $queries[] = self::prihlaska(self::ID_AKTIVITA_TECHNICKA, $idUcastnika);
        }
        foreach ([self::ID_BRIG_PRIHLASEN, self::ID_BRIG_PRITOMEN, self::ID_BRIG_NEPRIHLASEN] as $idUcastnika) {
            $queries[] = self::prihlaska(self::ID_AKTIVITA_BRIGADNICKA, $idUcastnika);
        }

        return $queries;
    }

    /**
     * Leaders count once registered, whether or not they arrived, and the unregistered leader never
     * counts. Participation counts everybody signed up with the right, registered or not, exactly as
     * Finance does. The rows do not change when the festival ends; only the no-show row appears.
     *
     * @test
     *
     * @dataProvider obdobiKolemKonceGc
     */
    public function bonusyZrcadliFinanceAPrihlaseniNedorazivsiSeHlasiZvlast(
        string $posunOdKonceGc,
        int $ocekavanychBlokuNedorazivsich,
    ): void {
        $bonusZaStandardniAktivitu = (int) SystemoveNastaveni::zGlobals()->dejHodnotu(SystemoveNastaveniKlice::BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU);
        self::assertGreaterThan(0, $bonusZaStandardniAktivitu, 'Test needs a non-zero bonus for a standard activity');
        $bonusZaTriHodinovouAktivitu = $bonusZaStandardniAktivitu * SystemoveNastaveni::getActivityStandardLengthCoefficient(3.0);

        $radky = $this->radkyReportu($posunOdKonceGc);

        // Two registered leaders (one arrived, one did not) on each of the three activities, the unregistered one is left out.
        self::assertEqualsWithDelta(2 * $bonusZaTriHodinovouAktivitu, $radky['Nr-BonusyCelkem'], 0.001, 'Leadership counts registered leaders, arrived or not');
        self::assertEqualsWithDelta(2 * $bonusZaTriHodinovouAktivitu, $radky['Nr-BonusyVedeniTech'], 0.001, 'Leading a technical activity follows the same rule');
        self::assertEqualsWithDelta(2 * $bonusZaTriHodinovouAktivitu, $radky['Nr-OdmenyVedeniBrigadnicke'], 0.001, 'Leading a part-time activity follows the same rule');
        self::assertEqualsWithDelta(
            2 * $bonusZaTriHodinovouAktivitu,
            $this->souctyRadkuSPrefixem($radky, 'Nr-UsetreneBonusy-'),
            0.001,
            'Saved bonuses of the two registered full orgs follow the same rule as the bonuses they would otherwise get',
        );

        // Finance checks no GC role for participation, so all three participants count, the unregistered one too.
        self::assertEqualsWithDelta(3 * self::CENA_TECHNICKE, $radky['Nr-BonusyUcastTech'], 0.001, 'Participation on technical activities needs no GC role');
        self::assertEqualsWithDelta(3 * self::CENA_BRIGADNICKE, $radky['Nr-OdmenyUcastBrigadnicke'], 0.001, 'Participation on part-time activities needs no GC role');

        // Only the registered leader who never arrived, and only after the festival has ended, on all three activities.
        self::assertEqualsWithDelta(
            $ocekavanychBlokuNedorazivsich * $bonusZaTriHodinovouAktivitu,
            $radky['Nr-BonusyVedeniNedorazili'],
            0.001,
            'The no-show row lists registered leaders who did not arrive, once the festival is over',
        );
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function obdobiKolemKonceGc(): array
    {
        return [
            'day before the festival ends' => ['-1 day', 0],
            'day after the festival ends'  => ['+1 day', 3],
        ];
    }

    /**
     * @return array<string, float>
     */
    private function radkyReportu(string $posunOdKonceGc): array
    {
        $konecGc = SystemoveNastaveni::zGlobals()->spocitanyKonecLetosnihoGameconu();
        $systemoveNastaveni = SystemoveNastaveni::zGlobals(
            ted: new DateTimeImmutableStrict($konecGc->modify($posunOdKonceGc)->format('Y-m-d H:i:s')),
        );

        $idAktivit = [
            self::ID_AKTIVITA_VEDENI,
            self::ID_AKTIVITA_TECHNICKA,
            self::ID_AKTIVITA_BRIGADNICKA,
            self::ID_AKTIVITA_NEDAVA_BONUS,
        ];
        $aktivity = array_filter(
            Aktivita::zFiltru(
                systemoveNastaveni: $systemoveNastaveni,
                filtr: [
                    FiltrAktivity::ROK => ROCNIK,
                ],
                prednacitat: true,
            ),
            static fn (Aktivita $aktivita): bool => in_array($aktivita->id(), $idAktivit, true),
        );
        self::assertCount(count($idAktivit), $aktivity, 'The fixture activities were not loaded');

        $radky = [];
        foreach ((new BfsrReport($systemoveNastaveni))->getBonusRows($aktivity) as [$kod, , $hodnota]) {
            $radky[$kod] = (float) $hodnota;
        }

        return $radky;
    }

    /**
     * @param array<string, float> $radky
     */
    private function souctyRadkuSPrefixem(
        array $radky,
        string $prefix,
    ): float {
        $soucet = 0.0;
        foreach ($radky as $kod => $hodnota) {
            if (str_starts_with($kod, $prefix)) {
                $soucet += $hodnota;
            }
        }

        return $soucet;
    }

    /**
     * @return list<int>
     */
    private static function vsichniUzivatele(): array
    {
        return [
            self::ID_ORG_PRIHLASEN,
            self::ID_ORG_PRITOMEN,
            self::ID_ORG_NEPRIHLASEN,
            self::ID_ORG_BEZ_PRAVA_PORADAT,
            self::ID_FULLORG_PRIHLASEN,
            self::ID_FULLORG_PRITOMEN,
            self::ID_TECH_PRIHLASEN,
            self::ID_TECH_PRITOMEN,
            self::ID_TECH_NEPRIHLASEN,
            self::ID_BRIG_PRIHLASEN,
            self::ID_BRIG_PRITOMEN,
            self::ID_BRIG_NEPRIHLASEN,
        ];
    }

    /**
     * @return array{string, array<int, mixed>}
     */
    private static function prideleniRole(
        int $idUzivatele,
        int $idRole,
    ): array {
        return [
            'INSERT INTO platne_role_uzivatelu(id_uzivatele, id_role, posadil) VALUES ($0, $1, 1)',
            [
                0 => $idUzivatele,
                1 => $idRole,
            ],
        ];
    }

    /**
     * @return array{string, array<int, mixed>}
     */
    private static function aktivita(
        int $idAktivity,
        int $typ,
        int $cena,
        bool $nedavaBonus = false,
    ): array {
        return [
            <<<SQL
INSERT INTO akce_seznam(
    id_akce, nazev_akce, rok, cena, typ, zacatek, konec,
    kapacita, kapacita_f, kapacita_m,
    bez_slevy, nedava_bonus, teamova,
    popis, popis_kratky, vybaveni
)
VALUES($0, $1, $2, $3, $4, $5, $6, 10, 0, 0, 0, $7, 0, '', '', '')
SQL,
            [
                0 => $idAktivity,
                1 => "Bonusy {$idAktivity}",
                2 => ROCNIK,
                3 => $cena,
                4 => $typ,
                5 => ROCNIK . '-07-17 10:00:00',
                6 => ROCNIK . '-07-17 13:00:00',
                7 => (int) $nedavaBonus,
            ],
        ];
    }

    /**
     * @return array{string, array<int, mixed>}
     */
    private static function prihlaska(
        int $idAktivity,
        int $idUzivatele,
    ): array {
        return [
            'INSERT INTO akce_prihlaseni(id_akce, id_uzivatele, id_stavu_prihlaseni) VALUES ($0, $1, $2)',
            [
                0 => $idAktivity,
                1 => $idUzivatele,
                2 => StavPrihlaseni::PRIHLASEN,
            ],
        ];
    }
}
