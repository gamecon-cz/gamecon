<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Aktivita;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as Sql;
use Gamecon\Tests\Db\AbstractTestDb;
use Gamecon\XTemplate\XTemplate;

/**
 * Místnosti a hlavní místnost z editoru aktivity: hlavní místnost je nepovinná (NULL = „automaticky“), zvolená je
 * vždy jednou z vybraných místností a odebraná hlavní místnost se mezi místnosti nevrací (Trello 1438 a 1471).
 */
class AktivitaEditorMistnostiTest extends AbstractTestDb
{
    protected static bool $disableStrictTransTables = true;

    /**
     * @return array<string>
     */
    protected static function getSetUpBeforeClassInitQueries(): array
    {
        $rok = (string) ROCNIK;

        return [
            // ID roste opačně než pořadí, aby bylo vidět, podle čeho se hlavní místnost vybírá
            <<<'SQL'
INSERT INTO lokace(id_lokace, nazev, dvere, poznamka, poradi, rok)
VALUES
    (3101, 'Editor místnost C', '', 'Editor skupina', 3003, 0),
    (3102, 'Editor místnost B', '', 'Editor skupina', 3002, 0),
    (3103, 'Editor místnost A', '', 'Editor skupina', 3001, 0)
SQL,
            // 7201 má zvolenou hlavní místnost, 7203 automatickou, 7204 starou zvolenou, která mezi místnostmi není
            <<<SQL
INSERT INTO akce_seznam(
    id_akce, nazev_akce, url_akce, typ, rok, stav, zacatek, konec, cena, teamova, kapacita, kapacita_f, kapacita_m, id_hlavni_lokace
)
VALUES
    (7201, 'Aktivita ve dvou místnostech', 'aktivita-ve-dvou-mistnostech-editor-test', 1, {$rok}, 2, '{$rok}-07-16 10:00:00', '{$rok}-07-16 12:00:00', 100, 0, 5, 0, 0, 3101),
    (7202, 'Aktivita bez místnosti', 'aktivita-bez-mistnosti-editor-test', 1, {$rok}, 2, '{$rok}-07-16 10:00:00', '{$rok}-07-16 12:00:00', 100, 0, 5, 0, 0, NULL),
    (7203, 'Aktivita s automatickou hlavní', 'aktivita-s-automatickou-hlavni-editor-test', 1, {$rok}, 2, '{$rok}-07-16 10:00:00', '{$rok}-07-16 12:00:00', 100, 0, 5, 0, 0, NULL),
    (7204, 'Aktivita se starou hlavní', 'aktivita-se-starou-hlavni-editor-test', 1, {$rok}, 2, '{$rok}-07-16 10:00:00', '{$rok}-07-16 12:00:00', 100, 0, 5, 0, 0, 3101)
SQL,
            <<<'SQL'
INSERT INTO akce_lokace(id_akce, id_lokace)
VALUES
    (7201, 3101),
    (7201, 3102),
    (7203, 3101),
    (7203, 3102),
    (7204, 3102)
SQL,
        ];
    }

    protected function tearDown(): void
    {
        $_POST = [];
        $_FILES = [];
        \Chyba::vyzvedniHtml();

        parent::tearDown();
    }

    public function testBezHlavniMistnostiSeAktivitaUloziSAutomatickouHlavni(): void
    {
        // Trello 1438, případ 1: dvě místnosti, hlavní nevyplněná, nápověda říká, že je nepovinná
        $this->posliEditor(7202, [3101, 3103], '');

        $aktivita = Aktivita::editorZpracuj(null);

        self::assertNotNull($aktivita, 'Aktivita bez zvolené hlavní místnosti se má uložit.');
        $hlasky = \Chyba::vyzvedniHtml();
        self::assertStringContainsString('Aktivita byla uložena', $hlasky);
        self::assertStringNotContainsString('Hlavní místnost', $hlasky);
        self::assertSame(
            'Upravený název',
            dbOneCol(<<<'SQL'
                SELECT nazev_akce FROM akce_seznam WHERE id_akce = 7202
                SQL),
            'Zbytek úprav se má uložit taky.',
        );
        self::assertSame([3101, 3103], self::idckaLokaci(7202));
        self::assertNull(self::idZvoleneHlavniLokace(7202), 'Nezvolená hlavní místnost zůstane automatická.');
        self::assertSame(
            3103,
            Aktivita::zId(7202)->idHlavniLokace(),
            'Hlavní má být první z vybraných místností podle pořadí, ne podle ID.',
        );
    }

    public function testOdebranaHlavniMistnostSeNevraciMeziMistnostiAHlavniJeAutomaticka(): void
    {
        // Trello 1471 a 1438, případ 2: hlavní místnost 3101 se z místností odebere, ale editor ji pořád posílá jako hlavní
        $this->posliEditor(7201, [3102, 3103], '3101');

        self::assertNotNull(Aktivita::editorZpracuj(null));
        self::assertSame([3102, 3103], self::idckaLokaci(7201), 'Odebraná hlavní místnost se nesmí vrátit mezi místnosti.');
        self::assertNull(self::idZvoleneHlavniLokace(7201));
        self::assertSame(3103, Aktivita::zId(7201)->idHlavniLokace());
    }

    public function testZvolenaHlavniMistnostZVybranychSeUlozi(): void
    {
        $this->posliEditor(7201, [3101, 3102, 3103], '3102');

        self::assertNotNull(Aktivita::editorZpracuj(null));
        self::assertSame(3102, self::idZvoleneHlavniLokace(7201));
    }

    public function testAutomatickaHlavniMistnostSePosuneSPridanouMistnosti(): void
    {
        self::assertSame(3102, Aktivita::zId(7203)->idHlavniLokace());

        $this->posliEditor(7203, [3101, 3102, 3103], '');
        self::assertNotNull(Aktivita::editorZpracuj(null));

        self::assertNull(self::idZvoleneHlavniLokace(7203));
        self::assertSame(3103, Aktivita::zId(7203)->idHlavniLokace(), 'Přidaná místnost s nižším pořadím je nová hlavní.');
    }

    public function testBezMistnostiNeniHlavniMistnost(): void
    {
        $this->posliEditor(7201, [], '3101');

        self::assertNotNull(Aktivita::editorZpracuj(null));
        self::assertSame([], self::idckaLokaci(7201));
        self::assertNull(self::idZvoleneHlavniLokace(7201));
    }

    public function testEditorPosleZvolenouHlavniMistnostAMistnostiVPoradi(): void
    {
        $html = self::vykresliMistnostiEditoru(Aktivita::zId(7201));

        self::assertSame('3101', self::zvolenaHlavniMistnost($html));
        self::assertSame(
            [
                3103 => 'Editor místnost A',
                3102 => 'Editor místnost B',
                3101 => 'Editor místnost C',
            ],
            array_intersect_key(self::mistnostiProNabidkuHlavni($html), [
                3101 => true,
                3102 => true,
                3103 => true,
            ]),
            'Nabídka hlavní místnosti dostane místnosti v pořadí místností, první vybraná je ta automatická.',
        );
        self::assertSame(
            1,
            substr_count($html, '<optgroup label="Editor skupina">'),
            'Místnosti se stejnou poznámkou mají být v jedné skupině, ne každá ve své.',
        );
    }

    public function testEditorUkazeAutomatickouHlavniMistnost(): void
    {
        self::assertSame(
            '',
            self::zvolenaHlavniMistnost(self::vykresliMistnostiEditoru(Aktivita::zId(7203))),
            'Bez zvolené hlavní místnosti je vybraná volba „automaticky“.',
        );
        self::assertSame(
            '',
            self::zvolenaHlavniMistnost(self::vykresliMistnostiEditoru(Aktivita::zId(7204))),
            'Zvolená hlavní místnost, která mezi místnostmi není, se ukáže jako „automaticky“.',
        );
        self::assertSame('', self::zvolenaHlavniMistnost(self::vykresliMistnostiEditoru(Aktivita::zId(7202))));
    }

    /**
     * @param array<int> $lokaceIds
     */
    private function posliEditor(
        int $idAktivity,
        array $lokaceIds,
        string $hlavniLokaceId,
    ): void {
        $_POST[Aktivita::POST_KLIC] = [
            Sql::ID_AKCE    => $idAktivity,
            Sql::NAZEV_AKCE => 'Upravený název',
            Sql::URL_AKCE   => dbOneCol(<<<'SQL'
                SELECT url_akce FROM akce_seznam WHERE id_akce = $0
                SQL, [
                0 => $idAktivity,
            ]),
            Sql::PATRI_POD  => '',
            Sql::POPIS      => '',
            'den'           => ROCNIK . '-07-16',
            Sql::ZACATEK    => '10',
            Sql::KONEC      => '12',
            Sql::CENA       => 100,
            Sql::TEAMOVA    => 0,
            Sql::KAPACITA   => 5,
            Sql::KAPACITA_F => 0,
            Sql::KAPACITA_M => 0,
        ];
        $_POST[Aktivita::LOKACE_KLIC] = array_map('strval', $lokaceIds);
        $_POST[Aktivita::HLAVNI_LOKACE_KLIC] = $hlavniLokaceId;
        $_POST[Aktivita::POTVRDIT_ZMENU_UDAJU_S_PRIHLASENYMI_KLIC] = '0';
    }

    private static function idZvoleneHlavniLokace(int $idAktivity): ?int
    {
        $idHlavniLokace = dbOneCol(<<<'SQL'
            SELECT id_hlavni_lokace FROM akce_seznam WHERE id_akce = $0
            SQL, [
            0 => $idAktivity,
        ]);

        return $idHlavniLokace === null
            ? null
            : (int) $idHlavniLokace;
    }

    /**
     * @return array<int>
     */
    private static function idckaLokaci(int $idAktivity): array
    {
        return array_map('intval', dbFetchColumn(<<<'SQL'
            SELECT id_lokace FROM akce_lokace WHERE id_akce = $0 ORDER BY id_lokace
            SQL, [
            0 => $idAktivity,
        ]));
    }

    private static function vykresliMistnostiEditoru(Aktivita $aktivita): string
    {
        // Přesně ta cesta, se kterou šablonu načítá Aktivita: zkompilovaná šablona (.xtpc) je jedna pro všechny cesty
        // k šabloně, ale jméno její třídy se počítá z cesty, takže jiný zápis cesty by ji nenašel.
        $xtpl = new XTemplate(dirname((new \ReflectionClass(Aktivita::class))->getFileName()) . '/templates/editor-aktivity.xtpl');
        $parseUpravyTabulkaLokace = new \ReflectionMethod(Aktivita::class, 'parseUpravyTabulkaLokace');
        $parseUpravyTabulkaLokace->invoke(null, $aktivita, $xtpl);
        $xtpl->parse('upravy.tabulka');
        $xtpl->parse('upravy');

        return $xtpl->text('upravy');
    }

    /**
     * @return string výslovně zvolená hlavní místnost, kterou editor předá JS, prázdná pro „automaticky“
     */
    private static function zvolenaHlavniMistnost(string $html): string
    {
        self::assertSame(1, preg_match('~<select id="hlavniLokace"[^>]* data-zvolena="([^"]*)"></select>~', $html, $shoda));

        return $shoda[1];
    }

    /**
     * @return array<int, string> ID místnosti => název v pořadí, v jakém je dostane nabídka hlavní místnosti
     */
    private static function mistnostiProNabidkuHlavni(string $html): array
    {
        self::assertSame(1, preg_match('~const lokaceVPoradi = (\[.*?\]);\n~', $html, $shoda));
        $mistnosti = json_decode($shoda[1], true, flags: JSON_THROW_ON_ERROR);

        return array_column($mistnosti, 'nazev', 'id');
    }
}
