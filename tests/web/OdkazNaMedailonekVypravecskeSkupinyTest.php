<?php

declare(strict_types=1);

namespace Gamecon\Tests\web;

use Gamecon\Aktivita\StavAktivity;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Role\Role;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Jméno vypravěče u aktivity odkazuje na jeho medailonek, i u vypravěčské skupiny (Albi, Deskofobie, …),
 * kterou admin zakládá jen z názvu: má přezdívku, jméno ne. Jméno si vypravěč píše sám, proto se escapuje.
 * Medailonek /<id> ale patří jen vypravěči, ne každému registrovanému.
 */
class OdkazNaMedailonekVypravecskeSkupinyTest extends AbstractTestWeb
{
    use ZadostiDoAdmina;

    /**
     * s HTML, ampersandem, uvozovkami (konec atributu og:title) a čárkou, kterou náhled karty dřív bral jako oddělovač vypravěčů
     */
    private const string NAZEV_SKUPINY = 'Hry & <i>spol</i>, "s.r.o."';
    private const string NAZEV_SKUPINY_HTML = 'Hry &amp; &lt;i&gt;spol&lt;/i&gt;, &quot;s.r.o.&quot;';
    private const string NAZEV_PRVNI_SKUPINY = 'Testovací skupina prvního termínu';
    private const string NAZEV_DRUHE_SKUPINY = 'Testovací skupina druhého termínu';
    private const int ID_AKTIVITY = 9851;
    // karta, jejíž dva termíny vedou různé skupiny (jako první kolo DrD)
    private const int ID_PRVNIHO_TERMINU = 9852;
    private const int ID_DRUHEHO_TERMINU = 9853;
    private const int ID_INSTANCE = 9851;
    // karta, jejíž dva termíny vedou obě skupiny společně
    private const int ID_PRVNIHO_SPOLECNEHO_TERMINU = 9854;
    private const int ID_DRUHEHO_SPOLECNEHO_TERMINU = 9855;
    private const int ID_SPOLECNE_INSTANCE = 9852;

    private static int $idSkupiny;
    private static int $idPrvniSkupiny;
    private static int $idDruheSkupiny;
    private static int $idUzivateleBezAktivit;
    private static int $idUzivateleSMedailonkem;
    private static int $idVypraveceBezAktivit;

    /**
     * @var list<string>
     */
    private array $kUklidu = [];

    protected static function keepSingleTestMethodDbChangesInTransaction(): bool
    {
        // admin zakládá skupinu v požadavku na Apache, mimo transakci testu
        return false;
    }

    protected static function getBeforeClassInitCallbacks(): array
    {
        return [
            static function (): void {
                // stejně jako admin → Aktivity → Nová vypravěčská skupina
                self::$idSkupiny = self::zalozUzivatele(self::NAZEV_SKUPINY);
                self::$idPrvniSkupiny = self::zalozUzivatele(self::NAZEV_PRVNI_SKUPINY);
                self::$idDruheSkupiny = self::zalozUzivatele(self::NAZEV_DRUHE_SKUPINY);

                self::zalozAktivitu(self::ID_AKTIVITY, TypAktivity::LARP, null, null, self::$idSkupiny);

                self::zalozKartuSeDvemaTerminy(
                    self::ID_PRVNIHO_TERMINU,
                    self::ID_DRUHEHO_TERMINU,
                    self::ID_INSTANCE,
                    [self::$idPrvniSkupiny],
                    [self::$idDruheSkupiny],
                );
                // u druhého termínu v opačném pořadí, na pořadí vypravěčů nezáleží
                self::zalozKartuSeDvemaTerminy(
                    self::ID_PRVNIHO_SPOLECNEHO_TERMINU,
                    self::ID_DRUHEHO_SPOLECNEHO_TERMINU,
                    self::ID_SPOLECNE_INSTANCE,
                    [self::$idPrvniSkupiny, self::$idDruheSkupiny],
                    [self::$idDruheSkupiny, self::$idPrvniSkupiny],
                );

                self::$idUzivateleBezAktivit = self::zalozUzivatele('Testovací uživatel bez aktivit');

                self::$idUzivateleSMedailonkem = self::zalozUzivatele('Testovací uživatel s medailonkem');
                dbQuery(<<<'SQL'
INSERT INTO medailonky(id_uzivatele, o_sobe, drd) VALUES ($0, '', '')
SQL, [
                    0 => self::$idUzivateleSMedailonkem,
                ]);

                // přehled /vypraveci ho vypíše, i když zatím nic nevede
                self::$idVypraveceBezAktivit = self::zalozUzivatele('Testovací vypravěč bez aktivit');
                dbQuery(<<<'SQL'
INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil) VALUES ($0, $1, $0)
SQL, [
                    0 => self::$idVypraveceBezAktivit,
                    1 => Role::LETOSNI_VYPRAVEC(ROCNIK),
                ]);
            },
        ];
    }

    private static function zalozUzivatele(string $login): int
    {
        return (int) \Uzivatel::zPole([
            'login_uzivatele' => $login,
        ], \Uzivatel::FAKE)->id();
    }

    /**
     * @param list<int> $idVypravecuPrvnihoTerminu
     * @param list<int> $idVypravecuDruhehoTerminu
     */
    private static function zalozKartuSeDvemaTerminy(
        int $idPrvnihoTerminu,
        int $idDruhehoTerminu,
        int $idInstance,
        array $idVypravecuPrvnihoTerminu,
        array $idVypravecuDruhehoTerminu,
    ): void {
        // instance a hlavní aktivita na sebe odkazují navzájem, proto hlavní aktivita napřed bez instance
        self::zalozAktivitu(
            $idPrvnihoTerminu,
            TypAktivity::RPG,
            null,
            ROCNIK . '-07-17 10:00:00',
            ...$idVypravecuPrvnihoTerminu,
        );
        dbQuery(<<<'SQL'
INSERT INTO akce_instance(id_instance, id_hlavni_akce) VALUES ($0, $1)
SQL, [
            0 => $idInstance,
            1 => $idPrvnihoTerminu,
        ]);
        dbQuery(<<<'SQL'
UPDATE akce_seznam SET patri_pod = $0 WHERE id_akce = $1
SQL, [
            0 => $idInstance,
            1 => $idPrvnihoTerminu,
        ]);
        self::zalozAktivitu(
            $idDruhehoTerminu,
            TypAktivity::RPG,
            $idInstance,
            ROCNIK . '-07-18 10:00:00',
            ...$idVypravecuDruhehoTerminu,
        );
    }

    private static function zalozAktivitu(int $id, int $typ, ?int $patriPod, ?string $zacatek, int ...$idVypravecu): void
    {
        dbQuery(<<<'SQL'
INSERT INTO akce_seznam (id_akce, nazev_akce, kapacita, kapacita_f, kapacita_m, cena, bez_slevy, nedava_bonus, typ, rok, stav, teamova, popis, popis_kratky, vybaveni, patri_pod, zacatek, konec)
VALUES ($0, $1, 10, 0, 0, 100, 0, 0, $2, $3, $4, 0, '', '', '', $5, $6, $6 + INTERVAL 2 HOUR)
SQL, [
            0 => $id,
            1 => 'Aktivita vypravěčské skupiny ' . $id,
            2 => $typ,
            3 => ROCNIK,
            4 => StavAktivity::PUBLIKOVANA,
            5 => $patriPod,
            6 => $zacatek,
        ]);
        foreach ($idVypravecu as $idVypravece) {
            dbQuery(<<<'SQL'
INSERT INTO akce_organizatori (id_akce, id_uzivatele) VALUES ($0, $1)
SQL, [
                0 => $id,
                1 => $idVypravece,
            ]);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        // aby se DNS vyřešilo ještě před curl, které by jinak mohlo padnout na ještě nepřipraveném Apache
        get_headers(URL_ADMIN);
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->kUklidu);
        parent::tearDown();
    }

    /**
     * @test
     */
    public function jmenoSkupinyUAktivityOdkazujeNaJejiMedailonekEscapovane(): void
    {
        $odpoved = $this->stahniVerejnouStranku('web/larpy');

        self::assertSame(200, $odpoved['status']);
        // v hlavičce karty a v náhledu, čárka v názvu jméno v náhledu nerozdělí
        self::assertSame(
            2,
            substr_count($odpoved['telo'], '<a href="' . self::$idSkupiny . '">' . self::NAZEV_SKUPINY_HTML . '</a>'),
            $odpoved['telo'],
        );
        self::assertStringNotContainsString('<i>spol</i>', $odpoved['telo']);
    }

    /**
     * @test
     */
    public function kartaSRuznymiVypravaciTerminuJeMaUKazdehoTerminuNeVHlavicce(): void
    {
        $telo = $this->stahniVerejnouStranku('web/rpg')['telo'];

        $odkazPrvniSkupiny = $this->odkazNaMedailonek(self::$idPrvniSkupiny, self::NAZEV_PRVNI_SKUPINY);
        $odkazDruheSkupiny = $this->odkazNaMedailonek(self::$idDruheSkupiny, self::NAZEV_DRUHE_SKUPINY);

        // u prvního kola DrD s desítkami PJ by byl seznam v hlavičce dlouhý a opakoval by termíny
        self::assertStringNotContainsString(
            'Vypravěč:',
            $this->hlavickaKarty($telo, self::ID_DRUHEHO_TERMINU),
        );
        $nahled = $this->nahledKarty($telo, self::ID_DRUHEHO_TERMINU);
        self::assertStringNotContainsString('<h3>Vypravěč:</h3>', $nahled);
        foreach ([$odkazPrvniSkupiny, $odkazDruheSkupiny] as $odkazTerminu) {
            self::assertStringContainsString(
                '<div class="aktivity_terminVypravec"><b>Vypravěč:</b> ' . $odkazTerminu . '</div>',
                $telo,
            );
            self::assertStringContainsString(
                '<div class="aktivity_nahledTerminVypravec">' . $odkazTerminu . '</div>',
                $nahled,
            );
        }
    }

    /**
     * @test
     */
    public function kartaSeStejnymiVypravaciVsechTerminuJeVypiseVHlavicceJednou(): void
    {
        $telo = $this->stahniVerejnouStranku('web/rpg')['telo'];

        $odkazPrvniSkupiny = $this->odkazNaMedailonek(self::$idPrvniSkupiny, self::NAZEV_PRVNI_SKUPINY);
        $odkazDruheSkupiny = $this->odkazNaMedailonek(self::$idDruheSkupiny, self::NAZEV_DRUHE_SKUPINY);

        self::assertStringContainsString(
            '<div class="aktivity_vypravec"><b>Vypravěč:</b> ' . $odkazPrvniSkupiny . ', ' . $odkazDruheSkupiny . '</div>',
            $this->hlavickaKarty($telo, self::ID_DRUHEHO_SPOLECNEHO_TERMINU),
        );
        $nahled = $this->nahledKarty($telo, self::ID_DRUHEHO_SPOLECNEHO_TERMINU);
        self::assertStringContainsString('<h3>Vypravěč:</h3>', $nahled);
        // každý na svém řádku, i když má název skupiny čárku
        self::assertStringContainsString($odkazPrvniSkupiny . '<br>' . $odkazDruheSkupiny, $nahled);
        self::assertStringNotContainsString('aktivity_nahledTerminVypravec', $nahled);
        // karta termínu je pořád ukáže u každého termínu (DrD a skupiny termínů)
        self::assertSame(
            2,
            substr_count(
                $telo,
                '<div class="aktivity_terminVypravec"><b>Vypravěč:</b> ' . $odkazPrvniSkupiny . ', ' . $odkazDruheSkupiny . '</div>',
            ),
        );
    }

    /**
     * @test
     */
    public function medailonekSkupinyMaJmenoEscapovaneIVTitulkuStranky(): void
    {
        $odpoved = $this->stahniVerejnouStranku('web/' . self::$idSkupiny);

        self::assertSame(200, $odpoved['status']);
        self::assertStringNotContainsString('<i>spol</i>', $odpoved['telo']);
        $jmeno = preg_quote(self::NAZEV_SKUPINY_HTML, '~');
        self::assertMatchesRegularExpression("~<title>[^<]*{$jmeno}</title>~", $odpoved['telo']);
        self::assertMatchesRegularExpression("~<meta property=\"og:title\" content=\"[^\"]*{$jmeno}\">~", $odpoved['telo']);
        self::assertStringContainsString('<h1>' . self::NAZEV_SKUPINY_HTML . '</h1>', $odpoved['telo']);
    }

    /**
     * @test
     */
    public function medailonekKohoNicNevedlNemaRoliAniMedailonekNeexistuje(): void
    {
        self::assertSame(404, $this->stahniVerejnouStranku('web/' . self::$idUzivateleBezAktivit)['status']);
    }

    /**
     * @test
     */
    public function medailonekMaIKdoNicNevedlAleMaMedailonekNeboLetosRoliVypravece(): void
    {
        self::assertSame(200, $this->stahniVerejnouStranku('web/' . self::$idUzivateleSMedailonkem)['status']);
        self::assertSame(200, $this->stahniVerejnouStranku('web/' . self::$idVypraveceBezAktivit)['status']);
    }

    /**
     * @test
     */
    public function poZalozeniSkupinyAdminOdkazeDoJejihoMedailonku(): void
    {
        $cookieFile = $this->prihlas(\Uzivatel::SYSTEM_LOGIN);

        $odpoved = $this->odesliSTelem($cookieFile, 'aktivity/novy-org', [
            'vypravec' => 'Nová <b>skupina</b>',
        ]);

        self::assertSame(200, $odpoved['kod']);
        self::assertSame(
            1,
            preg_match('~Uživatel Nová &lt;b&gt;skupina&lt;/b&gt; \((\d+)\) vytvořen~', $odpoved['telo'], $shoda),
            $odpoved['telo'],
        );
        self::assertStringContainsString('href="aktivity/medailonky?id=' . $shoda[1] . '"', $odpoved['telo']);
    }

    private function odkazNaMedailonek(int $idVypravece, string $jmenoHtml): string
    {
        return '<a href="' . $idVypravece . '">' . $jmenoHtml . '</a>';
    }

    /**
     * Od obrázku karty po její termíny; obrázek odkazuje na náhled poslední aktivity karty.
     */
    private function hlavickaKarty(string $telo, int $idPosledniAktivityKarty): string
    {
        return $this->vyrizni(
            $telo,
            'for="aktivity_nahledId' . $idPosledniAktivityKarty . '"><img',
            '<div class="aktivity_terminy">',
        );
    }

    private function nahledKarty(string $telo, int $idPosledniAktivityKarty): string
    {
        // poslední náhled na stránce nemá za sebou další
        return $this->vyrizni(
            $telo,
            'id="aktivity_nahledId' . $idPosledniAktivityKarty . '">',
            'class="aktivity_nahledCheckbox"',
        );
    }

    private function vyrizni(string $telo, string $od, string $do): string
    {
        $zacatek = strpos($telo, $od);
        self::assertNotFalse($zacatek, "Na stránce chybí {$od}\n{$telo}");
        $konec = strpos($telo, $do, $zacatek + strlen($od));

        return $konec === false
            ? substr($telo, $zacatek)
            : substr($telo, $zacatek, $konec - $zacatek);
    }
}
