<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniSpecSqlStruktura as PrihlaseniSpecSql;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\Report\MladsiNezVekAktivityReport;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Přihlášení mladší, než je věk aktivity (štítek „Věk: 15+“), ve výpisech adminu (Přehled aktivit, Seznam přihlášených)
 * a v reportu pro šéfy sekcí. Skripty adminu se načtou přes require ve statické closure, která jim dá jen proměnné
 * z kontextu adminu a nepřepíše proměnné testu.
 */
class MladsiUcastniciVAdminuTest extends AbstractTestDb
{
    use ProbihaRegistraceAktivitTrait;
    use VekAktivityTestTrait;

    private const string MODULY_AKTIVIT = __DIR__ . '/../../admin/scripts/modules/aktivity';

    protected static bool $disableStrictTransTables = true;

    private SystemoveNastaveni $systemoveNastaveni;

    protected function setUp(): void
    {
        parent::setUp();
        $this->systemoveNastaveni = self::vytvorSystemoveNastaveni();
    }

    public function testSeznamPrihlasenychVyznaciMladsihoUAktivityIVeVeku(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 15+');
        $mladsi = $this->vytvorUcastnika(vekVDenAktivity: 13);
        $starsi = $this->vytvorUcastnika(vekVDenAktivity: 30);
        $sledujici = $this->vytvorUcastnika(vekVDenAktivity: 12);
        // rychloregistrace: místo data narození den registrace, věk nevíme
        $rychloregistrovany = $this->vytvorUcastnika(datumNarozeni: ROCNIK . '-01-01', registrovan: ROCNIK . '-01-01 10:00:00');
        $this->prihlas($aktivita, $mladsi);
        $this->prihlas($aktivita, $starsi);
        $this->prihlas($aktivita, $rychloregistrovany);
        dbInsert(PrihlaseniSpecSql::AKCE_PRIHLASENI_SPEC_TABULKA, [
            PrihlaseniSpecSql::ID_AKCE             => $aktivita->id(),
            PrihlaseniSpecSql::ID_UZIVATELE        => $sledujici->id(),
            PrihlaseniSpecSql::ID_STAVU_PRIHLASENI => StavPrihlaseni::SLEDUJICI,
        ]);

        $vypisAktivity = $this->vypisAktivityVSeznamuPrihlasenych($aktivita);

        // na tenhle nadpis vede odkaz z e-mailu šéfovi sekce
        self::assertStringContainsString(
            '<i class="fa-solid fa-baby" aria-hidden="true"></i> mladší než 15 let: 1</span></h2>',
            $vypisAktivity,
            'Sledující ani rychloregistrovaný se nepočítají',
        );
        self::assertStringContainsString(
            '<span class="hinted mladsi-nez-vek-aktivity"><i class="fa-solid fa-baby" aria-hidden="true"></i>&nbsp;13'
            . '<span class="hint">Aktivita je od 15 let</span></span>',
            $this->radekUcastnika($vypisAktivity, $mladsi),
        );
        foreach ([$starsi, $sledujici, $rychloregistrovany] as $nevyznaceny) {
            self::assertStringNotContainsString('fa-baby', $this->radekUcastnika($vypisAktivity, $nevyznaceny));
        }
    }

    public function testPrehledAktivitVyznaciMladsiOdkazemDoSeznamuPrihlasenych(): void
    {
        $aktivita = $this->vytvorAktivitu('Věk: 15+');
        $mladsi = $this->vytvorUcastnika(vekVDenAktivity: 13);
        $nejmladsi = $this->vytvorUcastnika(vekVDenAktivity: 12);
        $this->prihlas($aktivita, $mladsi);
        $this->prihlas($aktivita, $nejmladsi);
        $this->prihlas($aktivita, $this->vytvorUcastnika(vekVDenAktivity: 30));
        $bezMladsich = $this->vytvorAktivitu('Věk: 15+');
        $this->prihlas($bezMladsich, $this->vytvorUcastnika(vekVDenAktivity: 30));

        $prehled = $this->spustPrehledAktivit();

        self::assertStringContainsString(
            '<a href="aktivity/prihlaseni?typ=' . TypAktivity::LARP . '#aktivita-' . $aktivita->id() . '"'
            . ' class="hinted mladsi-nez-vek-aktivity"><i class="fa-solid fa-baby" aria-hidden="true"></i>&nbsp;2'
            . '<span class="hint">'
            . htmlspecialchars(
                "Aktivita je od 15 let, v den aktivity bude míň: {$nejmladsi->jmenoNick()} (12), {$mladsi->jmenoNick()} (13)",
                ENT_QUOTES,
            )
            . '</span></a>',
            $prehled,
        );
        self::assertStringNotContainsString('#aktivita-' . $bezMladsich->id() . '"', $prehled);
    }

    public function testReportVypiseMladsiOdNejmladsihoSeSefemSekceAKdoJePrihlasil(): void
    {
        $this->nastavSefaSekceLarpu('šéfka Larpů', 'larpy.report@example.test');
        $aktivita = $this->vytvorAktivitu('Věk: 15+');
        $prihlasilSeSam = $this->vytvorUcastnika(vekVDenAktivity: 13);
        $prihlasilJiny = $this->vytvorUcastnika(vekVDenAktivity: 12);
        $admin = $this->vytvorUcastnika(vekVDenAktivity: 40);
        $aktivita->prihlas($prihlasilSeSam, $prihlasilSeSam, Aktivita::IGNOROVAT_KONTROLY);
        $aktivita->prihlas($prihlasilJiny, $admin, Aktivita::IGNOROVAT_KONTROLY);
        $this->prihlas($aktivita, $this->vytvorUcastnika(vekVDenAktivity: 30));

        $report = new MladsiNezVekAktivityReport($this->systemoveNastaveni);
        $radky = array_values(array_filter(
            array_map(
                static fn (array $radek) => array_combine($report->getHlavicky(), $radek),
                $report->getRadky(),
            ),
            static fn (array $radek) => $radek['ID aktivity'] === $aktivita->id(),
        ));

        self::assertSame([$prihlasilJiny->id(), $prihlasilSeSam->id()], array_column($radky, 'ID účastníka'), 'Od nejmladšího');
        self::assertSame([12, 13], array_column($radky, 'Věk v den aktivity'));
        self::assertSame([(string) $admin->jmenoNick(), 'sám'], array_column($radky, 'Přihlásil'));
        self::assertSame(['larpy.report@example.test', 'larpy.report@example.test'], array_column($radky, 'Šéf sekce'));
        self::assertSame([15, 15], array_column($radky, 'Od kolika let'));
        self::assertNotSame('', $radky[0]['Přihlášen']);
    }

    /**
     * Seznam přihlášených (admin Aktivity → Seznam přihlášených) linie larpů, jen část od nadpisu aktivity
     * po nadpis další.
     */
    private function vypisAktivityVSeznamuPrihlasenych(Aktivita $aktivita): string
    {
        $spustSeznam = static function (): void {
            require self::MODULY_AKTIVIT . '/prihlaseni.php';
        };
        $puvodniGet = $_GET;
        $_GET = [
            'typ' => (string) TypAktivity::LARP,
        ];
        ob_start();
        try {
            $spustSeznam();
        } finally {
            $vystup = (string) ob_get_clean();
            $_GET = $puvodniGet;
        }

        $zacatek = strpos($vystup, '<h2 id="aktivita-' . $aktivita->id() . '">');
        self::assertNotFalse($zacatek, "Seznam přihlášených aktivitu {$aktivita->id()} nevypsal");
        $konec = strpos($vystup, '<h2', $zacatek + 1);

        return $konec === false
            ? substr($vystup, $zacatek)
            : substr($vystup, $zacatek, $konec - $zacatek);
    }

    private function radekUcastnika(string $vypisAktivity, \Uzivatel $ucastnik): string
    {
        self::assertSame(
            1,
            preg_match('~<tr[^>]*>\s*<td>' . preg_quote($ucastnik->login(), '~') . '(?: \(sledující\))?</td>.*?</tr>~s', $vypisAktivity, $shoda),
            "Účastník {$ucastnik->login()} ve výpisu aktivity není",
        );

        return $shoda[0];
    }

    /**
     * Přehled aktivit (admin Aktivity → Přehled aktivit) bez filtru, tedy letošní aktivity všech linií.
     */
    private function spustPrehledAktivit(): string
    {
        $spustPrehled = static function (\Uzivatel $u, SystemoveNastaveni $systemoveNastaveni): void {
            require self::MODULY_AKTIVIT . '/aktivity.php';
        };
        $puvodniRequestUri = $_SERVER['REQUEST_URI'] ?? null;
        $_SERVER['REQUEST_URI'] = '/admin/aktivity';
        ob_start();
        try {
            $spustPrehled($this->vytvorUcastnika(vekVDenAktivity: 40), $this->systemoveNastaveni);
        } finally {
            $vystup = (string) ob_get_clean();
            // podle REQUEST_URI pozná KontextZobrazeni, že neběžíme na webu
            if ($puvodniRequestUri === null) {
                unset($_SERVER['REQUEST_URI']);
            } else {
                $_SERVER['REQUEST_URI'] = $puvodniRequestUri;
            }
        }

        return $vystup;
    }
}
