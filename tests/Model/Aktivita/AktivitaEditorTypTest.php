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
 * Neaktivní typ, například „(bez typu – organizační)“, se v editoru aktivity nenabízí a nově přiřadit nejde,
 * aktivita, která ho má, si ho ale ponechá. Nová aktivita nemá předvybraný typ.
 */
class AktivitaEditorTypTest extends AbstractTestDb
{
    private const int ID_AKTIVITY_BEZ_TYPU = 7211;
    private const int ID_AKTIVITY_WORKSHOP = 7212;
    private const int ID_AKTIVITY_LARP = 7213;
    private const int ID_UZIVATELE = 7311;
    private const string VYBER_TYP = 'Vyber typ aktivity.';

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
            // workshop je neaktivní, ať test nezávisí na stavu číselníku, a má nulové pořadí, které editor dřív vynechal
            <<<SQL
UPDATE akce_typy SET aktivni = 0, poradi = 0 WHERE id_typu = {$workshop}
SQL,
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

    public function testBezTypuJeNeaktivni(): void
    {
        self::assertFalse(TypAktivity::zId(TypAktivity::SYSTEMOVA)->isAktivni());
        self::assertTrue(TypAktivity::zId(TypAktivity::LARP)->isAktivni());
    }

    public function testNovaAktivitaNemaPredvybranyTypANabidkaNemaNeaktivniTypy(): void
    {
        $vyberTypu = $this->getVyberTypuVEditoru(null);

        self::assertMatchesRegularExpression('~^<select [^>]*\brequired\b~', $vyberTypu);
        self::assertMatchesRegularExpression('~<option value="" selected disabled>~', $vyberTypu);
        self::assertSame([], $this->getVybraneTypy($vyberTypu));
        self::assertNotContains(TypAktivity::SYSTEMOVA, $this->getNabizeneTypy($vyberTypu));
        self::assertNotContains(TypAktivity::WORKSHOP, $this->getNabizeneTypy($vyberTypu));
        self::assertContains(TypAktivity::LARP, $this->getNabizeneTypy($vyberTypu));
    }

    public function testTypySeZapornymPoradimJsouNaKonci(): void
    {
        $poradiTypu = array_map(
            static fn (int $idTypu): int => (int) TypAktivity::zId($idTypu)->poradi(),
            $this->getNabizeneTypy($this->getVyberTypuVEditoru(null)),
        );
        $kladna = array_values(array_filter($poradiTypu, static fn (int $poradi): bool => $poradi >= 0));
        $zaporna = array_values(array_filter($poradiTypu, static fn (int $poradi): bool => $poradi < 0));

        self::assertNotSame([], $zaporna);
        self::assertSame(array_merge($kladna, $zaporna), $poradiTypu);
        $serazenaKladna = $kladna;
        sort($serazenaKladna);
        self::assertSame($serazenaKladna, $kladna);
        $serazenaZaporna = $zaporna;
        rsort($serazenaZaporna);
        self::assertSame($serazenaZaporna, $zaporna);
    }

    public function testAktivitaSBeznymTypemNemaVNabidceNeaktivniTypy(): void
    {
        $vyberTypu = $this->getVyberTypuVEditoru(self::ID_AKTIVITY_LARP);

        self::assertSame([TypAktivity::LARP], $this->getVybraneTypy($vyberTypu));
        self::assertNotContains(TypAktivity::SYSTEMOVA, $this->getNabizeneTypy($vyberTypu));
        self::assertNotContains(TypAktivity::WORKSHOP, $this->getNabizeneTypy($vyberTypu));
        self::assertStringNotContainsString('(vyber typ)', $vyberTypu);
    }

    public function testAktiviteBezTypuSeTypNabidneAbySeUlozenimNezmenil(): void
    {
        $vyberTypu = $this->getVyberTypuVEditoru(self::ID_AKTIVITY_BEZ_TYPU);

        self::assertSame([TypAktivity::SYSTEMOVA], $this->getVybraneTypy($vyberTypu));
        self::assertStringNotContainsString('(vyber typ)', $vyberTypu);
    }

    public function testAktiviteSNeaktivnimTypemSNulovymPoradimSeTypNabidne(): void
    {
        $vyberTypu = $this->getVyberTypuVEditoru(self::ID_AKTIVITY_WORKSHOP);

        self::assertSame([TypAktivity::WORKSHOP], $this->getVybraneTypy($vyberTypu));
    }

    public function testNovaAktivitaBezTypuVeFormulariJeChyba(): void
    {
        // nevybranou volbu (disabled) prohlížeč neodešle, pole typ ve formuláři vůbec není
        self::assertSame([self::VYBER_TYP], self::zavolejKontroluTypu(null, null));
    }

    /**
     * @dataProvider provideNovePrirazeneTypySChybou
     */
    public function testNoveNelzePriraditNevybranyNeznamyNeboNeaktivniTyp(
        ?int $idAktivity,
        string $typ,
        string $ocekavanaChyba,
    ): void {
        self::assertSame([$ocekavanaChyba], self::zavolejKontroluTypu($typ, $idAktivity));
    }

    /**
     * @return array<string, array{int|null, string, string}>
     */
    public static function provideNovePrirazeneTypySChybou(): array
    {
        $bezTypu = 'Typ aktivity „(bez typu – organizační)“ se už nepoužívá, vyber jiný.';

        return [
            'nová, prázdný typ'        => [null, '', self::VYBER_TYP],
            'nová, neznámý typ'        => [null, '999999', self::VYBER_TYP],
            'nová, bez typu'           => [null, (string) TypAktivity::SYSTEMOVA, $bezTypu],
            'nová, neaktivní workshop' => [null, (string) TypAktivity::WORKSHOP, 'Typ aktivity „workshop“ se už nepoužívá, vyber jiný.'],
            'larp změněný na bez typu' => [self::ID_AKTIVITY_LARP, (string) TypAktivity::SYSTEMOVA, $bezTypu],
            'larp s prázdným typem'    => [self::ID_AKTIVITY_LARP, '', self::VYBER_TYP],
            // DB by uložila zaokrouhleně 5 (neaktivní workshop), kontrola by přitom viděla 4 (RPG)
            'nová, desetinné číslo' => [null, '4.6', self::VYBER_TYP],
            // jako ponechaný larp (2) by prošlo, uložilo by se ale 3
            'larp, desetinné číslo' => [self::ID_AKTIVITY_LARP, '2.6', self::VYBER_TYP],
            'nová, záporné číslo'   => [null, '-1', self::VYBER_TYP],
            'nová, text'            => [null, 'larp', self::VYBER_TYP],
        ];
    }

    /**
     * @dataProvider provideTypyBezChyby
     */
    public function testPlatnyNeboPonechanyTypNeniChyba(?int $idAktivity, ?string $typ): void
    {
        self::assertSame([], self::zavolejKontroluTypu($typ, $idAktivity));
    }

    /**
     * @return array<string, array{int|null, string|null}>
     */
    public static function provideTypyBezChyby(): array
    {
        return [
            'nová, larp'                       => [null, (string) TypAktivity::LARP],
            'bez typu si ho ponechá'           => [self::ID_AKTIVITY_BEZ_TYPU, (string) TypAktivity::SYSTEMOVA],
            'neaktivní workshop si ho ponechá' => [self::ID_AKTIVITY_WORKSHOP, (string) TypAktivity::WORKSHOP],
            'bez typu změněný na larp'         => [self::ID_AKTIVITY_BEZ_TYPU, (string) TypAktivity::LARP],
            'existující bez pole typ'          => [self::ID_AKTIVITY_LARP, null],
        ];
    }

    /**
     * @return list<string> chyby editoru týkající se typu
     */
    private static function zavolejKontroluTypu(?string $typ, ?int $idAktivity): array
    {
        $formular = [
            Sql::ID_AKCE   => $idAktivity ?? 0,
            Sql::URL_AKCE  => 'kontrola-typu-editor-test-' . ($idAktivity ?? 'nova'),
            Sql::PATRI_POD => null,
        ];
        if ($typ !== null) {
            $formular[Sql::TYP] = $typ;
        }
        $chyby = (new \ReflectionMethod(Aktivita::class, 'editorChyby'))->invoke(null, $formular);

        return array_values(array_filter(
            $chyby,
            static fn (string $chyba): bool => $chyba === self::VYBER_TYP || str_starts_with($chyba, 'Typ aktivity '),
        ));
    }

    private function getVyberTypuVEditoru(?int $idAktivity): string
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
     * @return list<int>
     */
    private function getNabizeneTypy(string $vyberTypu): array
    {
        preg_match_all('~<option\s+(?:selected\s+)?value="(\d+)">~', $vyberTypu, $typy);

        return array_map('intval', $typy[1]);
    }

    /**
     * @return list<int>
     */
    private function getVybraneTypy(string $vyberTypu): array
    {
        preg_match_all('~<option\s+selected\s+value="(\d+)">~', $vyberTypu, $typy);

        return array_map('intval', $typy[1]);
    }
}
