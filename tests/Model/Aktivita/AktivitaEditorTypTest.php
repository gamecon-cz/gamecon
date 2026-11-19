<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as Sql;
use Gamecon\Aktivita\TypAktivity;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\XTemplate\XTemplate;

/**
 * „(bez typu – organizační)“ se v editoru aktivity nenabízí, nová aktivita nemá předvybraný typ
 * a typ, který aktivita už má, se nabídne i neaktivní.
 */
class AktivitaEditorTypTest extends AbstractTestDb
{
    private const int ID_AKTIVITY_BEZ_TYPU = 7211;
    private const int ID_AKTIVITY_WORKSHOP = 7212;
    private const int ID_AKTIVITY_LARP = 7213;
    private const int ID_UZIVATELE = 7311;

    protected static bool $disableStrictTransTables = true;

    /**
     * @return list<string>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $rok = (string) ROCNIK;
        $bezTypu = TypAktivity::SYSTEMOVA;
        $workshop = TypAktivity::WORKSHOP;
        $larp = TypAktivity::LARP;

        return [
            <<<'SQL'
INSERT INTO uzivatele_hodnoty(id_uzivatele, login_uzivatele, jmeno_uzivatele, prijmeni_uzivatele, email1_uzivatele)
VALUES
    (7311, 'test-editor-typu', 'Test', 'Editor', 'test-editor-typu@example.invalid')
SQL,
            // workshop je neaktivní typ, ať test nezávisí na stavu číselníku
            "UPDATE akce_typy SET aktivni = 0 WHERE id_typu = {$workshop}",
            <<<SQL
INSERT INTO akce_seznam(id_akce, nazev_akce, url_akce, typ, rok, stav, patri_pod, kapacita, kapacita_f, kapacita_m, teamova)
VALUES
    (7211, 'Aktivita bez typu', 'aktivita-bez-typu-editor-test', {$bezTypu}, {$rok}, 1, NULL, 5, 0, 0, 0),
    (7212, 'Workshop', 'workshop-editor-test', {$workshop}, {$rok}, 1, NULL, 5, 0, 0, 0),
    (7213, 'Larp', 'larp-editor-test', {$larp}, {$rok}, 1, NULL, 5, 0, 0, 0)
SQL,
        ];
    }

    protected function tearDown(): void
    {
        unset($_SESSION[\Uzivatel::UZIVATEL]);

        parent::tearDown();
    }

    public function testNovaAktivitaNemaPredvybranyTypANabidkaNemaBezTypu(): void
    {
        $vyberTypu = $this->vyberTypuVEditoru(null);

        self::assertMatchesRegularExpression('~^<select [^>]*\brequired\b~', $vyberTypu);
        self::assertMatchesRegularExpression('~<option value="" selected disabled>~', $vyberTypu);
        self::assertSame([], $this->vybraneTypy($vyberTypu));
        self::assertNotContains(TypAktivity::SYSTEMOVA, $this->nabizeneTypy($vyberTypu));
        self::assertNotContains(TypAktivity::WORKSHOP, $this->nabizeneTypy($vyberTypu), 'Neaktivní typ se nové aktivitě nenabízí');
        self::assertContains(TypAktivity::LARP, $this->nabizeneTypy($vyberTypu));
    }

    public function testAktivitaSBeznymTypemNemaVNabidceBezTypu(): void
    {
        $vyberTypu = $this->vyberTypuVEditoru(self::ID_AKTIVITY_LARP);

        self::assertSame([TypAktivity::LARP], $this->vybraneTypy($vyberTypu));
        self::assertNotContains(TypAktivity::SYSTEMOVA, $this->nabizeneTypy($vyberTypu));
        self::assertStringNotContainsString('(vyber typ)', $vyberTypu);
    }

    public function testAktiviteBezTypuSeTypNabidneAbySeUlozenimNezmenil(): void
    {
        $vyberTypu = $this->vyberTypuVEditoru(self::ID_AKTIVITY_BEZ_TYPU);

        self::assertSame([TypAktivity::SYSTEMOVA], $this->vybraneTypy($vyberTypu));
        self::assertStringNotContainsString('(vyber typ)', $vyberTypu);
    }

    public function testAktiviteSNeaktivnimTypemSeTypNabidneAbySeUlozenimNezmenil(): void
    {
        $vyberTypu = $this->vyberTypuVEditoru(self::ID_AKTIVITY_WORKSHOP);

        self::assertSame([TypAktivity::WORKSHOP], $this->vybraneTypy($vyberTypu));
    }

    /**
     * @dataProvider provideTypyKtereNovaAktivitaNesmiDostat
     */
    public function testNovaAktivitaBezVybranehoTypuJeChyba(string $typ): void
    {
        self::assertContains('Vyber typ aktivity.', self::zavolejEditorChyby($typ, null));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideTypyKtereNovaAktivitaNesmiDostat(): array
    {
        return [
            'nevybraný typ'            => [''],
            '(bez typu – organizační)' => [(string) TypAktivity::SYSTEMOVA],
        ];
    }

    public function testNovaAktivitaSVybranymTypemNeniChyba(): void
    {
        self::assertNotContains('Vyber typ aktivity.', self::zavolejEditorChyby((string) TypAktivity::LARP, null));
    }

    public function testZmenaTypuNaBezTypuJeChyba(): void
    {
        self::assertContains(
            'Vyber typ aktivity.',
            self::zavolejEditorChyby((string) TypAktivity::SYSTEMOVA, self::ID_AKTIVITY_LARP),
        );
    }

    public function testAktivitaBezTypuSiHoMuzePonechat(): void
    {
        self::assertNotContains(
            'Vyber typ aktivity.',
            self::zavolejEditorChyby((string) TypAktivity::SYSTEMOVA, self::ID_AKTIVITY_BEZ_TYPU),
        );
    }

    public function testBezTypuVeFormulariNeniChyba(): void
    {
        $chyby = (new \ReflectionMethod(Aktivita::class, 'editorChyby'))->invoke(null, [
            Sql::ID_AKCE   => self::ID_AKTIVITY_LARP,
            Sql::URL_AKCE  => 'larp-editor-test',
            Sql::PATRI_POD => null,
        ]);

        self::assertNotContains('Vyber typ aktivity.', $chyby, 'Formulář bez pole typu typ nemění');
    }

    /**
     * @return array<string>
     */
    private static function zavolejEditorChyby(string $typ, ?int $idAktivity): array
    {
        return (new \ReflectionMethod(Aktivita::class, 'editorChyby'))->invoke(null, [
            Sql::ID_AKCE   => $idAktivity ?? 0,
            Sql::URL_AKCE  => 'typ-editor-test-' . ($idAktivity ?? 'nova'),
            Sql::PATRI_POD => null,
            Sql::TYP       => $typ,
        ]);
    }

    private function vyberTypuVEditoru(?int $idAktivity): string
    {
        // Without it XTemplate compiles the template next to its source, into the tracked tree
        $cacheDir = XTemplate::cache() ?: XTPL_CACHE_DIR;
        pripravCache($cacheDir);
        XTemplate::cache($cacheDir);
        $_SESSION[\Uzivatel::UZIVATEL] = [
            'id_uzivatele' => self::ID_UZIVATELE,
        ];
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();
        $aktivita = $idAktivity !== null
            ? Aktivita::zId($idAktivity, false, $systemoveNastaveni)
            : null;

        $html = Aktivita::editor($systemoveNastaveni, $aktivita);

        self::assertSame(1, preg_match('~<select name="[^"]*\[typ\]" id="typAktivity".*?</select>~s', $html, $vyberTypu));

        return $vyberTypu[0];
    }

    /**
     * @return array<int>
     */
    private function nabizeneTypy(string $vyberTypu): array
    {
        preg_match_all('~<option\s+(?:selected\s+)?value="(\d+)">~', $vyberTypu, $typy);

        return array_map('intval', $typy[1]);
    }

    /**
     * @return array<int>
     */
    private function vybraneTypy(string $vyberTypu): array
    {
        preg_match_all('~<option\s+selected\s+value="(\d+)">~', $vyberTypu, $typy);

        return array_map('intval', $typy[1]);
    }
}
