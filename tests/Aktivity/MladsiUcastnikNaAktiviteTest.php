<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\MladsiUcastniciAktivit;
use Gamecon\Aktivita\MladsiUcastnikAktivity;
use Gamecon\Kanaly\EmailovaSablona\KodEmailoveSablonyEnum;
use Gamecon\Kanaly\MailLogger;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;

/**
 * Přihlášený mladší, než je věk aktivity (štítek „Věk: 15+“): e-mail šéfovi sekce při přihlášení a vyznačení
 * ve výpisech adminu a v reportu (MladsiUcastniciAktivit).
 */
class MladsiUcastnikNaAktiviteTest extends AbstractTestDb
{
    use ProbihaRegistraceAktivitTrait;
    use VekAktivityTestTrait;

    protected static bool $disableStrictTransTables = true;

    private SystemoveNastaveni $systemoveNastaveni;

    protected function setUp(): void
    {
        parent::setUp();
        $this->systemoveNastaveni = self::vytvorSystemoveNastaveni();
    }

    public function testKdyzSeMladsiPrihlasiSamSefSekceDostaneEmail(): void
    {
        $emailSefa = $this->unikatniEmailSefa();
        $this->nastavSefaSekceLarpu('šéfka Larpů', $emailSefa);
        $aktivita = $this->vytvorAktivitu('Věk: 18+');
        $ucastnik = $this->vytvorUcastnika(vekVDenAktivity: 15);

        self::assertTrue($aktivita->prihlas($ucastnik, $ucastnik, Aktivita::IGNOROVAT_KONTROLY));

        $mail = $this->posledniMailPro($emailSefa);
        self::assertNotNull($mail, 'Šéf sekce e-mail nedostal');
        self::assertSame('mladsi_ucastnik_na_aktivite', $mail['sablona']);
        self::assertStringContainsString($aktivita->nazev(), $mail['predmet']);
        self::assertStringContainsString('od 18 let', $mail['predmet']);
        self::assertStringContainsString((string) $ucastnik->mail(), $mail['telo_html']);
        self::assertStringContainsString('bude teprve 15', $mail['telo_html']);
        self::assertStringContainsString(
            htmlspecialchars('/aktivity/prihlaseni?typ=' . $aktivita->typId() . '#aktivita-' . $aktivita->id()),
            $mail['telo_html'],
        );
    }

    public function testBezHlavickyLinieJdeEmailNaInfo(): void
    {
        $this->nastavSefaSekceLarpu(null);
        $aktivita = $this->vytvorAktivitu('Věk: 18+');
        $ucastnik = $this->vytvorUcastnika(vekVDenAktivity: 15);
        $pocetPred = $this->pocetMailuSablonyPro(Aktivita::KONTAKT_NA_SEFA_PROGRAMU);

        $aktivita->prihlas($ucastnik, $ucastnik, Aktivita::IGNOROVAT_KONTROLY);

        self::assertSame($pocetPred + 1, $this->pocetMailuSablonyPro(Aktivita::KONTAKT_NA_SEFA_PROGRAMU));
    }

    public function testKdyzMladsihoPrihlasiJinyEmailNeodejde(): void
    {
        $emailSefa = $this->unikatniEmailSefa();
        $this->nastavSefaSekceLarpu('šéfka Larpů', $emailSefa);
        $aktivita = $this->vytvorAktivitu('Věk: 18+');
        $ucastnik = $this->vytvorUcastnika(vekVDenAktivity: 15);

        // admin nebo vypravěč v prezenci: výjimku má domluvenou, stejně jako u upozornění
        $aktivita->prihlas($ucastnik, $this->vytvorUcastnika(vekVDenAktivity: 40), Aktivita::IGNOROVAT_KONTROLY);

        self::assertTrue($aktivita->prihlasen($ucastnik));
        self::assertNull($this->posledniMailPro($emailSefa));
    }

    public function testKdyzSePrihlasiDostatecneStaryEmailNeodejde(): void
    {
        $emailSefa = $this->unikatniEmailSefa();
        $this->nastavSefaSekceLarpu('šéfka Larpů', $emailSefa);
        $aktivita = $this->vytvorAktivitu('Věk: 18+');
        $ucastnik = $this->vytvorUcastnika(vekVDenAktivity: 18);

        $aktivita->prihlas($ucastnik, $ucastnik, Aktivita::IGNOROVAT_KONTROLY);

        self::assertTrue($aktivita->prihlasen($ucastnik));
        self::assertNull($this->posledniMailPro($emailSefa));
    }

    public function testPoVlastnimOdhlaseniAZnovuPrihlaseniSefSekceDalsiEmailNedostane(): void
    {
        $emailSefa = $this->unikatniEmailSefa();
        $this->nastavSefaSekceLarpu('šéfka Larpů', $emailSefa);
        $aktivita = $this->vytvorAktivitu('Věk: 18+');
        $ucastnik = $this->vytvorUcastnika(vekVDenAktivity: 15);

        $aktivita->prihlas($ucastnik, $ucastnik, Aktivita::IGNOROVAT_KONTROLY);
        $aktivita->odhlas($ucastnik, $ucastnik, 'test');
        $aktivita->prihlas($ucastnik, $ucastnik, Aktivita::IGNOROVAT_KONTROLY);

        self::assertTrue($aktivita->prihlasen($ucastnik));
        self::assertSame(1, $this->pocetMailuSablonyPro($emailSefa), 'Šéf sekce o něm už ví');
    }

    public function testKohoSefSekceOdhlasilAPrihlasiSeZnovuOTomDostaneNovyEmail(): void
    {
        $emailSefa = $this->unikatniEmailSefa();
        $this->nastavSefaSekceLarpu('šéfka Larpů', $emailSefa);
        $aktivita = $this->vytvorAktivitu('Věk: 18+');
        $ucastnik = $this->vytvorUcastnika(vekVDenAktivity: 15);

        $aktivita->prihlas($ucastnik, $ucastnik, Aktivita::IGNOROVAT_KONTROLY);
        $aktivita->odhlas($ucastnik, $this->vytvorUcastnika(vekVDenAktivity: 40), 'test');
        $aktivita->prihlas($ucastnik, $ucastnik, Aktivita::IGNOROVAT_KONTROLY);

        self::assertSame(2, $this->pocetMailuSablonyPro($emailSefa));
    }

    public function testKohoPrihlasilJinyAOnSeOdhlasilAZnovuPrihlasilSefSekceEmailNedostane(): void
    {
        $emailSefa = $this->unikatniEmailSefa();
        $this->nastavSefaSekceLarpu('šéfka Larpů', $emailSefa);
        $aktivita = $this->vytvorAktivitu('Věk: 18+');
        $ucastnik = $this->vytvorUcastnika(vekVDenAktivity: 15);

        // kdo ho přihlásil z adminu, měl výjimku domluvenou
        $aktivita->prihlas($ucastnik, $this->vytvorUcastnika(vekVDenAktivity: 40), Aktivita::IGNOROVAT_KONTROLY);
        $aktivita->odhlas($ucastnik, $ucastnik, 'test');
        $aktivita->prihlas($ucastnik, $ucastnik, Aktivita::IGNOROVAT_KONTROLY);

        self::assertSame(0, $this->pocetMailuSablonyPro($emailSefa));
    }

    public function testNaTurnajiJeEmailJenOPrvnimKole(): void
    {
        $emailSefa = $this->unikatniEmailSefa();
        $this->nastavSefaSekceLarpu('šéfka Larpů', $emailSefa);
        [[$prvniKolo], [$druheKolo]] = $this->vytvorTurnaj([['Věk: 15+'], ['Věk: 18+']]);
        $ucastnik = $this->vytvorUcastnika(vekVDenAktivity: 12);

        $prvniKolo->prihlas($ucastnik, $ucastnik, Aktivita::IGNOROVAT_KONTROLY);

        self::assertTrue($druheKolo->prihlasen($ucastnik), 'Přihlášení na turnaj přihlásí i na další kolo');
        self::assertSame(1, $this->pocetMailuSablonyPro($emailSefa), 'Za celé přihlášení jeden e-mail');
        $mail = $this->posledniMailPro($emailSefa);
        self::assertStringContainsString($prvniKolo->nazev(), $mail['predmet']);
        self::assertStringContainsString('od 15 let', $mail['predmet']);
        self::assertStringContainsString('#aktivita-' . $prvniKolo->id(), $mail['telo_html']);
    }

    public function testNaTurnajiBezVekuPrvnihoKolaEmailNeodejde(): void
    {
        $emailSefa = $this->unikatniEmailSefa();
        $this->nastavSefaSekceLarpu('šéfka Larpů', $emailSefa);
        [[$prvniKolo], [$druheKolo]] = $this->vytvorTurnaj([[null], ['Věk: 15+']]);
        $ucastnik = $this->vytvorUcastnika(vekVDenAktivity: 12);

        $prvniKolo->prihlas($ucastnik, $ucastnik, Aktivita::IGNOROVAT_KONTROLY);

        self::assertTrue($druheKolo->prihlasen($ucastnik), 'Přihlášení na turnaj přihlásí i na další kolo');
        // upozornění bylo jen o prvním kole, ve výpisech v adminu je vyznačený na druhém
        self::assertSame(0, $this->pocetMailuSablonyPro($emailSefa));
    }

    public function testKdyzSeEmailNepodariOdeslatPrihlaseniPlati(): void
    {
        $emailSefa = $this->unikatniEmailSefa();
        $this->nastavSefaSekceLarpu('šéfka Larpů', $emailSefa);
        $aktivita = $this->vytvorAktivitu('Věk: 18+');
        $ucastnik = $this->vytvorUcastnika(vekVDenAktivity: 15);
        // bez šablony se e-mail neodešle (EmailovaSablonaNeniVyplnena)
        dbQuery(<<<'SQL'
            DELETE FROM emailova_sablona WHERE kod = $0
            SQL, [
            0 => KodEmailoveSablonyEnum::MLADSI_UCASTNIK_NA_AKTIVITE->value,
        ]);
        $pocetChybPred = $this->pocetZapsanychChyb();

        // varování (trigger_error) by tu PHPUnit ohlásil, chyba se má jen zapsat
        self::assertTrue($aktivita->prihlas($ucastnik, $ucastnik, Aktivita::IGNOROVAT_KONTROLY));

        self::assertTrue($aktivita->prihlasen($ucastnik));
        self::assertNull($this->posledniMailPro($emailSefa));
        self::assertSame($pocetChybPred + 1, $this->pocetZapsanychChyb(), 'Chyba e-mailu se zapíše do logu chyb');
    }

    public function testNajdeJenPrihlaseneMladsiNezVekAktivityAtKohokoliPrihlasil(): void
    {
        $proStarsi = $this->vytvorAktivitu('Věk: 15+');
        $bezVeku = $this->vytvorAktivitu();
        $mladsi = $this->vytvorUcastnika(vekVDenAktivity: 13);
        $nejmladsi = $this->vytvorUcastnika(vekVDenAktivity: 12);
        $dostatecneStary = $this->vytvorUcastnika(vekVDenAktivity: 15);
        // rychloregistrace: místo data narození den registrace, nevíme
        $bezDataNarozeni = $this->vytvorUcastnika(datumNarozeni: ROCNIK . '-01-01', registrovan: ROCNIK . '-01-01 10:00:00');
        foreach ([$mladsi, $nejmladsi, $dostatecneStary, $bezDataNarozeni] as $ucastnik) {
            $this->prihlas($proStarsi, $ucastnik);
            $this->prihlas($bezVeku, $ucastnik);
        }

        $nalezeni = MladsiUcastniciAktivit::najdi(Aktivita::zIds([$proStarsi->id(), $bezVeku->id()], $this->systemoveNastaveni));

        self::assertSame([(int) $proStarsi->id()], array_keys($nalezeni));
        self::assertSame(
            [
                [$nejmladsi->id(), 12, 15],
                [$mladsi->id(), 13, 15],
            ],
            array_map(
                static fn (MladsiUcastnikAktivity $mladsiUcastnik) => [
                    $mladsiUcastnik->ucastnik->id(),
                    $mladsiUcastnik->vek,
                    $mladsiUcastnik->minimalniVek,
                ],
                $nalezeni[(int) $proStarsi->id()],
            ),
            'Od nejmladšího',
        );
        self::assertSame(
            "Aktivita je od 15 let, v den aktivity bude míň: {$nejmladsi->jmenoNick()} (12), {$mladsi->jmenoNick()} (13)",
            MladsiUcastniciAktivit::getPopis($nalezeni[(int) $proStarsi->id()]),
        );
    }

    public function testBezAktivitSeStitkemVekuNicNehleda(): void
    {
        self::assertSame([], MladsiUcastniciAktivit::najdi([$this->vytvorAktivitu()]));
        self::assertSame([], MladsiUcastniciAktivit::najdi([]));
    }

    private function unikatniEmailSefa(): string
    {
        return 'sef.sekce.' . uniqid('', true) . '@example.test';
    }

    /**
     * @return array<string, mixed>|null
     */
    private function posledniMailPro(string $email): ?array
    {
        $logger = new MailLogger(LOGY . '/maily.sqlite');
        $nalezene = $logger->najdi($email, limit: 1);

        return $nalezene === []
            ? null
            : $logger->detail((int) $nalezene[0]['id']);
    }

    /**
     * Chyby zapsané Vyjimkovacem (LOGY/chyby.sqlite), jak je vidí Dev → Chyby.
     */
    private function pocetZapsanychChyb(): int
    {
        $soubor = LOGY . '/chyby.sqlite';
        if (! is_file($soubor)) {
            return 0;
        }
        $db = new \PDO('sqlite:' . $soubor);
        $tabulka = $db->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'chyby'")->fetchColumn();

        return $tabulka
            ? (int) $db->query('SELECT COUNT(*) FROM chyby')->fetchColumn()
            : 0;
    }

    private function pocetMailuSablonyPro(string $email): int
    {
        return count(array_filter(
            (new MailLogger(LOGY . '/maily.sqlite'))->najdi($email, limit: 10_000),
            static fn (array $mail) => $mail['sablona'] === 'mladsi_ucastnik_na_aktivite',
        ));
    }
}
