<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

use Gamecon\Admin\Modules\Aktivity\Import\Activities\ActivitiesImportSqlColumn;
use Gamecon\Admin\Modules\Aktivity\Import\Activities\ImportSqlMappedValuesChecker;
use Gamecon\Admin\Modules\Aktivity\Import\Activities\ImportValuesDescriber;
use Gamecon\Aktivita\OnlinePrezence\OnlinePrezenceHtml;
use Gamecon\Aktivita\SqlStruktura\AkceLokaceSqlStruktura;
use Gamecon\Aktivita\SqlStruktura\AkceOrganizatoriSqlStruktura;
use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniLogSqlStruktura;
use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniSpecSqlStruktura;
use Gamecon\Aktivita\SqlStruktura\AkcePrihlaseniSqlStruktura;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as Sql;
use Gamecon\Aktivita\SqlStruktura\AkceSjednoceneTagySqlStruktura;
use Gamecon\Aktivita\SqlStruktura\AkceTypySqlStruktura;
use Gamecon\Aktivita\SqlStruktura\KategorieSjednocenychTaguSqlStruktura;
use Gamecon\Aktivita\SqlStruktura\SjednoceneTagySqlStruktura;
use Gamecon\Cache\DataSourcesCollector;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Exceptions\ChybaKolizeAktivit;
use Gamecon\Kanaly\GcMail;
use Gamecon\Pravo;
use Gamecon\PrednacitaniTrait;
use Gamecon\SystemoveNastaveni\SqlStruktura\SystemoveNastaveniSqlStruktura;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Finance;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura;
use Gamecon\Vyjimkovac\Vyjimkovac;
use Gamecon\Web\Urls;
use Gamecon\XTemplate\XTemplate;
use Granam\RemoveDiacritics\RemoveDiacritics;
use Symfony\Component\Filesystem\Filesystem;
use Uzivatel;

/**
 * Třída aktivity
 */
class Aktivita
{

    use PrednacitaniTrait;

    private static       $objekty                      = [];
    private static       $prihlaseniNaAktivityRawCache = [];
    private static array $seznamUcastnikuCache         = [];

    private                    $a;              // databázový řádek s aktivitou
    private array              $kolekce = [];   // nadřízená kolekce, v rámci které byla aktivita načtena
    private                    $lokace;
    private                    $stav;
    private bool               $nova;           // jestli jde o nově uloženou aktivitu nebo načtenou z DB
    private bool               $prednacitat;    // zda bychom měli načíst data i pro další aktivity
    private SystemoveNastaveni $systemoveNastaveni;
    /** @var array<Uzivatel>|null */
    private array | null $organizatori;
    private              $uzavrenaOd;
    private              $typ;
    /** @var void|Uzivatel[][] */
    private $seznamUcastniku;
    /** @var null|AktivitaPrezence */
    private $prezence;
    /** @var null|Filesystem */
    private $filesystem;

    const AJAXKLIC              = 'aEditFormTest'; // název post proměnné; ve které jdou data; pokud chceme ajaxově testovat jejich platnost a čekáme json odpověď
    const OBRAZEK_KLIC          = 'aEditObrazek';    // název proměnné; v které bude případně obrázek
    const ODMENA_ZA_HODINU_KLIC = 'odmena_za_hodinu';
    const TAGYKLIC              = 'aEditTag';       // název proměnné; v které jdou tagy
    const POSTKLIC              = 'aEditForm'; // název proměnné (ve výsledku pole); v které bude editační formulář aktivity předávat data
    const TEAMKLIC              = 'aTeamForm'; // název post proměnné s formulářem pro výběr teamu
    const TEAMKLIC_KOLA         = 'aTeamFormKolo';  // název post proměnné s výběrem kol pro team
    const PN_PLUSMINUSP         = 'cAktivitaPlusminusp'; // název post proměnné pro úpravy typu plus
    const PN_PLUSMINUSM         = 'cAktivitaPlusminusm'; // název post proměnné pro úpravy typu mínus
    const HAJENI_TEAMU_HODIN    = 72; // počet hodin po kterýc aktivita automatick vykopává nesestavený tým
    const LIMIT_POPIS_KRATKY    = 180; // max počet znaků v krátkém popisku
    // ignore a parametry kolem přihlašovátka
    const PLUSMINUS                          = 0b0000000000001; // plus/mínus zkratky pro měnění míst v team. aktivitě
    const PLUSMINUS_KAZDY                    = 0b0000000000010; // plus/mínus zkratky pro každého
    const STAV                               = 0b0000000000100; // ignorování stavu
    const ZAMEK                              = 0b0000000001000; // ignorování zamčení pro tým
    const BEZ_POKUT                          = 0b0000000010000; // odhlášení bez pokut
    const ZPETNE                             = 0b0000000100000; // možnost zpětně měnit přihlášení
    const INTERNI                            = 0b0000001000000; // přihlašovat i skryté technické a brigádnické aktivity
    const NEPOSILAT_MAILY_SLEDUJICIM         = 0b0000010000000; // odhlášení bez mailů náhradníkům
    const DOPREDNE                           = 0b0000100000000; // možnost přihlásit před otevřením registrací na aktivity
    const IGNOROVAT_LIMIT                    = 0b0001000000000;
    const IGNOROVAT_PRIHLASENI_NA_SOUROZENCE = 0b0010000000000;
    const NEOTEVRENE                         = 0b0100000000000; // přihlašování na neaktivované, pro běžné přihlašování dosud neotevřené aktivity
    const UKAZAT_DETAILY_CHYBY               = 0b1000000000000;
    // parametry kolem továrních metod
    const JEN_VOLNE  = 0b00000001; // jen volné aktivity
    const VEREJNE    = 0b00000010; // jen veřejně viditelné aktivity
    const ZAMCENE    = 0b00000100;
    const NEUZAVRENE = 0b00001000;

    public static function dejPrazdnou(): self
    {
        return new static([], true);
    }

    /**
     * @param Aktivita[] $aktivity
     * @return ZmenaStavuAktivity|null
     */
    public static function posledniZmenaStavuAktivit(array $aktivity): ?ZmenaStavuAktivity
    {
        if (count($aktivity) === 0) {
            return null;
        }
        $posledniZnameStavyAktivit = [];
        foreach ($aktivity as $aktivita) {
            $posledniZnameStavyAktivit[$aktivita->id()] = ['idPoslednihoLogu' => 0];
        }

        return static::dejPosledniZmenyStavuAktivit($posledniZnameStavyAktivit)->posledniZmenaStavuAktivity();
    }

    public static function dejPosledniZmenyStavuAktivit(array $posledniZnameStavyAktivit): PosledniZmenyStavuAktivit
    {
        $posledniZmenyStavuAktivit = new PosledniZmenyStavuAktivit();
        foreach (self::dejDataPoslednichZmen($posledniZnameStavyAktivit) as $zmena) {
            $zmenaStavuAktivity = ZmenaStavuAktivity::vytvorZDatDatabaze(
                (int)$zmena['id_akce'],
                (int)$zmena['akce_stavy_log_id'],
                new \DateTimeImmutable($zmena['kdy']),
                (int)$zmena['id_stav'],
            );
            $posledniZmenyStavuAktivit->addPosledniZmenaStavuAktivity($zmenaStavuAktivity);
        }

        return $posledniZmenyStavuAktivit;
    }

    /**
     * @param string[][] $posledniZnameStavyAktivit Například {"4387":[{"idPoslednihoLogu": 12345}]}
     * Formát viz online-prezence-posledni-zname-zmeny-prihlaseni.js
     * @return array
     * @throws \DbException
     */
    private static function dejDataPoslednichZmen(array $posledniZnameStavyAktivit): array
    {
        if (!$posledniZnameStavyAktivit) {
            return [];
        }

        $whereOrArray      = [];
        $sqlQueryParametry = [];
        foreach ($posledniZnameStavyAktivit as $idAktivity => ['idPoslednihoLogu' => $idPoslednihoZnamehoLogu]) {
            $idAktivity              = (int)$idAktivity;
            $idPoslednihoZnamehoLogu = (int)$idPoslednihoZnamehoLogu;
            $whereOrArray[]          = "(id_akce = $idAktivity AND akce_stavy_log_id > $idPoslednihoZnamehoLogu)";
        }
        $where = implode(' OR ', $whereOrArray);

        return dbFetchAll(<<<SQL
SELECT akce_stavy_log.id_akce,
       akce_stavy_log.id_stav,
       akce_stavy_log.kdy,
       akce_stavy_log.akce_stavy_log_id
FROM (
    SELECT akce_stavy_log.id_akce, MAX(akce_stavy_log.akce_stavy_log_id) AS id_posledniho_logu
    FROM akce_stavy_log
    WHERE {$where}
    GROUP BY id_akce
) AS nejnovejsi
INNER JOIN akce_stavy_log
    ON nejnovejsi.id_akce = akce_stavy_log.id_akce
        AND nejnovejsi.id_posledniho_logu = akce_stavy_log.akce_stavy_log_id
GROUP BY akce_stavy_log.id_akce
SQL
            , $sqlQueryParametry,
        );
    }

    /**
     * Vytvoří aktivitu dle výstupu z databáze. Pokud výstup (např. položkou
     * "přihlášen") je vztažen vůči uživateli, je potřeba ho zadat teď jako $u,
     * později to nebude možné.
     * @param array $dbRow
     * @throws \Exception
     */
    private function __construct(
        array              $dbRow,
        bool               $povolitPrazdnou = false,
        SystemoveNastaveni $systemoveNastaveni = null,
        bool               $prednacitat = false,
    ) {
        if (!$dbRow && !$povolitPrazdnou) {
            throw new \RuntimeException('Nelze vytvořiit aktivitu. Prázdný parametr jejího konstruktoru.');
        }
        $this->a                  = $dbRow;
        $this->nova               = false;
        $this->prednacitat        = $prednacitat;
        $this->systemoveNastaveni = $systemoveNastaveni ?? SystemoveNastaveni::zGlobals();

        $this->typ    = $dbRow[Sql::TYP] ?? null;
        $this->lokace = $dbRow[Sql::LOKACE] ?? null;
        $this->stav   = $dbRow[Sql::STAV] ?? null;
    }

    public function posledniZmenaStavuAktivity(): ?ZmenaStavuAktivity
    {
        return static::dejPosledniZmenyStavuAktivit([$this->id() => ['idPoslednihoLogu' => 0]])->posledniZmenaStavuAktivity();
    }

    /**
     * Aktivuje (zpřístupní pro přihlašování) aktivitu
     */
    public function aktivuj()
    {
        if (!$this->zacatek()) {
            throw new \Chyba('Aktivita nemá nastavený čas');
        }
        dbQuery('UPDATE akce_seznam SET stav = $1 WHERE id_akce = $2', [StavAktivity::AKTIVOVANA, $this->id()]);
        $this->refresh();
    }

    /**
     * POZOR: toto by se mělo dít jen výjimečně a měl by na to mít právo jen někdo.
     * Vrátí aktivovanou (přihlašovatelnou) aktivitu zpět na publikovanou (veřejnou, ale nepřihlašovatelnou)
     */
    public function deaktivuj()
    {
        if ($this->stav()->jeAktivovana()) {
            $this->priprav();
        }
        $this->refresh();
    }

    /**
     * POZOR: toto by se mělo dít jen výjimečně a měl by na to mít právo jen někdo.
     * Vrátí publikovanou (veřejnou, ale nepřihlašovatelnou) aktivitu zpět na novou (skrytou)
     */
    public function odpublikuj()
    {
        if ($this->stav()->jePublikovana()) {
            $this->zmenStav(StavAktivity::NOVA);
        }
        $this->refresh();
    }

    /**
     * Jestli je na aktivitu zakázáno uplatnit jakékoli procentuální slevy
     */
    public function bezSlevy(): bool
    {
        return (bool)$this->a[Sql::BEZ_SLEVY];
    }

    public function probehlaKorekce(): bool
    {
        return (bool)$this->a[Sql::PROBEHLA_KOREKCE];
    }

    public function nastavKorekci(bool $stav): void
    {
        dbQuery(<<<SQL
            UPDATE akce_seznam
            SET probehla_korekce = $0
            WHERE id_akce = {$this->id()}
            OR (patri_pod IS NOT NULL
                AND patri_pod = (SELECT a.patri_pod
                    FROM akce_seznam a
                    WHERE a.id_akce = {$this->id()}
                )
            )
            SQL,
            [$stav]);
        $this->a[Sql::PROBEHLA_KOREKCE] = $stav;
    }

    public function slevaNasobic(
        \Uzivatel             $u = null,
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): float {
        Finance::slevaAktivityDSC($dataSourcesCollector);

        return (!$this->a[Sql::BEZ_SLEVY] && $u && $u->gcPrihlasen($dataSourcesCollector))
            ? $u->finance()->slevaAktivity($dataSourcesCollector)
            : 1.;
    }

    /**
     * Cena aktivity čitelná člověkem, poplatná aktuálnímu okamžiku. V případě
     * uvedení uživatele vrací pro něj specifickou cenu.
     */
    public function cenaTextem(Uzivatel $u = null): ?string
    {
        if (TypAktivity::jeInterniDleId((int)$this->a[Sql::TYP])) {
            return null;
        }
        if ($this->cenaZaklad() <= 0) {
            return 'zdarma';
        }
        if ($this->a[Sql::BEZ_SLEVY]) {
            return round($this->cenaZaklad()) . '&thinsp;Kč';
        }
        if ($u && $u->gcPrihlasen()) {
            return round($this->cenaZaklad() * $u->finance()->slevaAktivity()) . '&thinsp;Kč';
        }

        return round($this->cenaZaklad()) . '&thinsp;Kč';
    }

    /** Základní cena aktivity */
    public function cenaZaklad(): float
    {
        return (float)$this->a[Sql::CENA];
    }

    /**
     * @return array Vrací pole dalších kol této aktivity. Každé další kolo je
     *  samo polem, v kterém jsou jednotlivé aktivity (varianty) z kterých se dá
     *  v daném kole vybírat.
     */
    public function dalsiKola()
    {
        $dalsiKola = [];
        $dalsiKolo = [$this];
        while ($dalsiKolo = current($dalsiKolo)->deti()) {
            $dalsiKola[] = $dalsiKolo;
        }

        return $dalsiKola;
    }

    /** Délka aktivity v hodinách (float) */
    public function delka(): float
    {
        if (($zacatek = $this->zacatek()) && ($konec = $this->konec())) {
            return (($konec->getTimestamp() - $zacatek->getTimestamp()) / 3600 + 24) % 24;
        }

        return 0.0;
    }

    public function rok(): int
    {
        return (int)$this->a['rok'];
    }

    /**
     * @return string datum ve stylu Pátek 14-18 (skutečný čas)
     */
    public function denCasSkutecny(): string
    {
        if ($this->denSkutecny() && $this->konec()) {
            return $this->denSkutecny()->format('l G') . '–' . $this->konec()->format('G');
        }

        return '';
    }

    /**
     * Skutečný den - nepřesouvá se do předchozího dne pokud je po půlnoci
     */
    public function denSkutecny(): DateTimeCz | null
    {
        return self::denAktivity($this, false);
    }

    /**
     * Oficiální den, do kterého aktivita spadá (může být po půlnoci, ale spadá do předchozího dne)
     */
    public function denProgramu(): DateTimeCz | null
    {
        return self::denAktivity($this, true);
    }

    private static function denAktivity(
        ?Aktivita $aktivita,
        bool      $programovy,
    ): DateTimeCz | null {
        if ($aktivita && $aktivita->zacatek()) {
            return (!$programovy || ($aktivita->zacatek()->format('H') >= PROGRAM_ZACATEK))
                ? $aktivita->zacatek()
                : (clone $aktivita->zacatek())->minusDen();
        }

        return null;
    }

    /** Vrátí potomky této aktivity (=navázané aktivity, další kola, ...) */
    public function deti(): array
    {
        if ($this->a[Sql::DITE]) {
            return self::zIds(
                ids: $this->a[Sql::DITE],
                systemoveNastaveni: $this->systemoveNastaveni,
            );
        }

        return [];
    }

    public function maDite(int $idDitete): bool
    {
        return in_array($idDitete, $this->detiIds(), true);
    }

    /**
     * @return int[]
     */
    public function detiIds(): array
    {
        if (!$this->a[Sql::DITE]) {
            return [];
        }

        return $this->parseIds($this->a[Sql::DITE] ?? '');
    }

    public function detiDbString(): ?string
    {
        if (!$this->a[Sql::DITE]) {
            return null;
        }

        return $this->a[Sql::DITE];
    }

    /** Počet hodin do začátku aktivity (float) */
    public function zbyvaHodinDoZacatku(): float
    {
        $zacatek = $this->zacatek();
        if (!$zacatek) {
            return (float)PHP_INT_MAX;
        }

        return ($zacatek->getTimestamp() - $this->systemoveNastaveni->ted()->getTimestamp()) / 3600;
    }

    /**
     * Vrátí HTML kód editoru aktivit určený pro vytváření a editaci aktivity.
     * Podle nastavení $a buď aktivitu edituje nebo vytváří.
     * @todo Zkusit refaktorovat editor na samostatnou třídu, pokud to půjde bez
     * vytvoření závislostí na vnitřní proměnné aktivity.
     */
    public static function editor(
        SystemoveNastaveni $systemoveNastaveni,
        Aktivita           $a = null,
    ) {
        return self::editorParam(
            editorTagu: new EditorTagu($systemoveNastaveni->db()),
            aktivita: $a,
            systemoveNastaveni: $systemoveNastaveni,
        );
    }

    /**
     * Vrátí pole obsahující chyby znemožňující úpravu aktivity. Hodnoty jsou
     * chybové hlášky. Význam indexů ndef (todo možno rozšířit).
     * @param array $a Pole odpovídající strukturou vkládanému (upravovanému) řádku DB,
     * podle toho nemá (má) id aktivity
     */
    protected static function editorChyby(array $a)
    {
        $chyby = []; // kontrola dostupnosti organizátorů v daný čas
        if (!empty($a['den']) && !empty($a[Sql::ZACATEK]) && !empty($a[Sql::KONEC])) {

            $zacatek           = (Program::denAktivityDleZacatku($a))->add(new \DateInterval('PT' . $a[Sql::ZACATEK] . 'H'));
            $konec             = (Program::denAktivityDleKonce($a))->add(new \DateInterval('PT' . $a[Sql::KONEC] . 'H'));
            $ignorovatAktivitu = isset($a[Sql::ID_AKCE])
                ? self::zId($a[Sql::ID_AKCE])
                : null;
            foreach ($a['organizatori'] ?? [] as $orgId) {
                $org = Uzivatel::zId($orgId);
                if (!$org->maVolno($zacatek, $konec, $ignorovatAktivitu)) {
                    $chyby[] = 'Organizátor ' . $org->jmenoNick() . ' má v danou dobu jinou aktivitu.'; // TODO doplnit název kolizní aktivity
                }
            }
        }

        // kontrola duplicit url
        if (self::urlJeObsazena($a)) {
            $chyby[] = sprintf(
                "Url '%s' je už letos použitá pro jinou aktivitu. Vyberte jinou, nebo použijte tlačítko „inst“ v seznamu aktivit pro duplikaci.",
                $a[Sql::URL_AKCE],
            );
        }

        return $chyby;
    }

    protected static function urlJeObsazena(array $a): bool
    {
        return (bool)dbOneLine('SELECT 1 FROM akce_seznam
      WHERE url_akce = $0 AND ( patri_pod IS NULL OR patri_pod != $1 ) AND id_akce != $2 AND rok = $3',
            [0 => $a[Sql::URL_AKCE], 1 => $a[Sql::PATRI_POD], 2 => $a[Sql::ID_AKCE], 3 => ROCNIK],
        );
    }

    /**
     * Vrátí v chyby v JSON formátu (pro ajax) nebo FALSE pokud žádné nejsou
     */
    public static function editorChybyJson()
    {
        $a = $_POST[self::POSTKLIC];

        return json_encode(['chyby' => self::editorChyby($a)]);
    }

    /**
     * Vrátí html kód editoru, je možné parametrizovat, co se pomocí něj dá měnit
     */
    protected static function editorParam(
        EditorTagu         $editorTagu,
        Aktivita           $aktivita = null,
                           $omezeni = [],
        SystemoveNastaveni $systemoveNastaveni = null,
    ) {
        // inicializace šablony
        $xtpl = new XTemplate(__DIR__ . '/templates/editor-aktivity.xtpl');
        $xtpl->assign('fields', self::POSTKLIC); // název proměnné (pole) v kterém se mají posílat věci z formuláře
        $xtpl->assign('ajaxKlic', self::AJAXKLIC);
        $xtpl->assign('obrKlic', self::OBRAZEK_KLIC);
        $xtpl->assign('obrKlicUrl', self::OBRAZEK_KLIC . 'Url');
        $xtpl->assign('aEditTag', self::TAGYKLIC);
        $xtpl->assign('limitPopisKratky', self::LIMIT_POPIS_KRATKY);
        $xtpl->assign('typBrigadnicka', TypAktivity::BRIGADNICKA);

        if ($aktivita) {
            $aktivitaData = $aktivita->a; // databázový řádek
            $xtpl->assign($aktivitaData);
            $xtpl->assign(Sql::POPIS, dbText($aktivitaData[Sql::POPIS]));
            $xtpl->assign('urlObrazku', $aktivita->obrazek());
            $xtpl->assign(Sql::VYBAVENI, $aktivita->vybaveni());
        }

        $vybraneTagy = $aktivita
            ? $aktivita->tagy()
            : [];
        self::nactiTagy($vybraneTagy, $editorTagu, $xtpl); // načtení lokací
        if (!$omezeni || !empty($omezeni[Sql::LOKACE])) {
            self::parseUpravyTabulkaLokace($aktivita, $xtpl);
        }

        if (!$omezeni || !empty($omezeni['deti'])) {
            self::parseUpravyTabulkaDeti(aktivita: $aktivita, xtpl: $xtpl, systemoveNastaveni: $systemoveNastaveni);
        }

        if (!$omezeni || !empty($omezeni['rodice'])) {
            self::parseUpravyTabulkaRodice(aktivita: $aktivita, xtpl: $xtpl, systemoveNastaveni: $systemoveNastaveni);
        }

        // editace dnů + časů
        if (!$omezeni || !empty($omezeni[Sql::ZACATEK])) {
            // načtení dnů
            self::parseUpravyTabulkaDen($aktivita, $xtpl); // načtení časů
            self::parseUpravyTabulkaZacatekAKonec($aktivita, $xtpl);
        }

        // načtení organizátorů
        if (!$omezeni || !empty($omezeni['organizator'])) {
            self::parseUpravyTabulkaVypraveci($aktivita, $xtpl);
        }

        // načtení typů
        if (!$omezeni || !empty($omezeni[Sql::TYP])) {
            self::parseUpravyTabulkaTypy($aktivita, $xtpl);
        }

        // výstup
        if (!$omezeni) {
            $xtpl->parse('upravy.tabulka');
        }

        if (Uzivatel::zSession()->maPravo(Pravo::PROVADI_KOREKCE)) {
            $xtpl->parse('upravy.checkboxKorekce');
        }
        $xtpl->parse('upravy');

        return $xtpl->text('upravy');
    }

    private static function parseUpravyTabulkaLokace(
        ?Aktivita $aktivita,
        XTemplate $xtpl,
    ): void {
        $aktivitaData = $aktivita?->a; // databázový řádek
        $xtpl->assign(['id_lokace' => null, 'nazev' => '(žádná)', 'selected' => '']);
        $xtpl->parse('upravy.tabulka.lokace');
        $q = dbQuery('SELECT id_lokace, nazev FROM akce_lokace ORDER BY poradi');
        while ($lokaceData = mysqli_fetch_assoc($q)) {
            $xtpl->assign('selected', $aktivita && $aktivitaData[Sql::LOKACE] == $lokaceData['id_lokace']
                ? 'selected'
                : '');
            $xtpl->assign($lokaceData);
            $xtpl->parse('upravy.tabulka.lokace');
        }
    }

    private static function parseUpravyTabulkaDeti(
        ?Aktivita           $aktivita,
        XTemplate           $xtpl,
        ?SystemoveNastaveni $systemoveNastaveni,
    ): void {
        $q       = dbQuery(
            "SELECT id_akce FROM akce_seznam WHERE id_akce != $1 AND rok = $2 ORDER BY nazev_akce",
            [1 => $aktivita?->id(), 2 => ROCNIK],
        );
        $detiIds = $aktivita
            ? $aktivita->detiIds()
            : [];
        while ($mozneDiteData = mysqli_fetch_assoc($q)) {
            $mozneDiteId = $mozneDiteData[Sql::ID_AKCE];
            $xtpl->assign(
                'selected',
                in_array($mozneDiteId, $detiIds, false)
                    ? 'selected'
                    : '',
            );
            $mozneDite = Aktivita::zId(
                id: $mozneDiteId,
                zCache: true,
                systemoveNastaveni: $systemoveNastaveni,
            );
            $xtpl->assign('id_ditete', $mozneDiteId);
            $xtpl->assign('nazev_ditete', self::dejRozsirenyNazevAktivity($mozneDite));
            $xtpl->parse('upravy.tabulka.dite');
        }
    }

    private static function dejRozsirenyNazevAktivity(Aktivita $aktivita): string
    {
        return sprintf(
            '%d - %s - %s %s-%s',
            $aktivita->id(),
            $aktivita->nazev(),
            $aktivita->zacatek()
                ? $aktivita->zacatek()->format('l')
                : '',
            $aktivita->zacatek()
                ? $aktivita->zacatek()->format('G')
                : '',
            $aktivita->konec()
                ? $aktivita->konec()->format('G')
                : '',
        );
    }

    private static function parseUpravyTabulkaRodice(
        ?Aktivita           $aktivita,
        XTemplate           $xtpl,
        ?SystemoveNastaveni $systemoveNastaveni,
    ): void {
        $q = dbQuery(
            "SELECT id_akce FROM akce_seznam WHERE id_akce != $1 AND rok = $2 ORDER BY nazev_akce",
            [1 => $aktivita?->id(), 2 => ROCNIK],
        );
        while ($moznyRodicData = mysqli_fetch_assoc($q)) {
            $moznyRodicId = $moznyRodicData[Sql::ID_AKCE];
            $moznyRodic   = Aktivita::zId(id: $moznyRodicId, zCache: true, systemoveNastaveni: $systemoveNastaveni);
            $xtpl->assign(
                'selected',
                $aktivita && $moznyRodic->maDite($aktivita->id())
                    ? 'selected'
                    : '',
            );
            $xtpl->assign('id_rodice', $moznyRodicId);
            $xtpl->assign('nazev_rodice', self::dejRozsirenyNazevAktivity($moznyRodic));
            $xtpl->parse('upravy.tabulka.rodic');
        }
    }

    private static function parseUpravyTabulkaDen(
        ?Aktivita $aktivita,
        XTemplate $xtpl,
    ) {
        $denAktivity = self::denAktivity($aktivita, true);
        $xtpl->assign([
            'selected' => $aktivita && !$aktivita->zacatek()
                ? 'selected'
                : '',
            'den'      => 0,
            'denSlovy' => '?',
        ]);
        $xtpl->parse('upravy.tabulka.den');
        for ($den = new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()) {
            $xtpl->assign([
                'selected' => $aktivita && $den->stejnyDen($denAktivity)
                    ? 'selected'
                    : '',
                'den'      => $den->format('Y-m-d'),
                'denSlovy' => $den->format('l'),
            ]);
            $xtpl->parse('upravy.tabulka.den');
        }
    }

    private static function parseUpravyTabulkaZacatekAKonec(
        ?Aktivita $aktivita,
        XTemplate $xtpl,
    ) {
        $aZacatek      = $aktivita && $aktivita->zacatek()
            ? (int)$aktivita->zacatek()->format('G')
            : null;
        $aKonec        = $aktivita && $aktivita->konec()
            ? (int)$aktivita->konec()->sub(new \DateInterval('PT1H'))->format('G')
            : null; // kontrola přehoupnutí přes půlnoc
        $hodinyZacatku = Program::seznamHodinZacatku();

        array_unshift($hodinyZacatku, null);
        foreach ($hodinyZacatku as $hodinaZacatku) {
            $xtpl->assign('selected', $aZacatek === $hodinaZacatku
                ? 'selected'
                : '');
            if ($hodinaZacatku === 0) {
                $xtpl->assign(Sql::ZACATEK, "24");
                $xtpl->assign('zacatekSlovy', '24:00');
            } else {
                $xtpl->assign(Sql::ZACATEK, $hodinaZacatku);
                $xtpl->assign('zacatekSlovy', $hodinaZacatku !== null
                    ? ($hodinaZacatku . ':00')
                    : '?');
            }
            $xtpl->parse('upravy.tabulka.zacatek');

            $xtpl->assign('selected', $aKonec === $hodinaZacatku
                ? 'selected'
                : '');
            $xtpl->assign(Sql::KONEC, ($hodinaZacatku !== null
                ? $hodinaZacatku + 1
                : null));
            $xtpl->assign('konecSlovy', $hodinaZacatku !== null
                ? (($hodinaZacatku + 1) . ':00')
                : '?');
            $xtpl->parse('upravy.tabulka.konec');
        }
    }

    private static function parseUpravyTabulkaVypraveci(
        ?Aktivita $aktivita,
        XTemplate $xtpl,
    ): void {
        $q = dbQuery(<<<SQL
                SELECT u.id_uzivatele, u.login_uzivatele, u.jmeno_uzivatele, u.prijmeni_uzivatele
                FROM uzivatele_hodnoty u
                JOIN platne_role_uzivatelu
                    ON u.id_uzivatele = platne_role_uzivatelu.id_uzivatele
                JOIN prava_role
                    ON platne_role_uzivatelu.id_role = prava_role.id_role
                WHERE prava_role.id_prava = $0
                GROUP BY u.login_uzivatele
                ORDER BY u.login_uzivatele
            SQL,
            [
                0 => Pravo::PORADANI_AKTIVIT,
            ],
        );

        $vsichniOrg = [];
        while ($uzivatelData = mysqli_fetch_assoc($q)) {
            $vsichniOrg[$uzivatelData['id_uzivatele']] = Uzivatel::jmenoNickZjisti($uzivatelData);
        }
        $aktOrg   = $aktivita
            ? array_map(static function (
                Uzivatel $e,
            ) {
                return (int)$e->id();
            }, $aktivita->organizatori())
            : [];
        $aktOrg[] = 0; // poslední pole má selected 0 (žádný org)
        foreach ($vsichniOrg as $id => $org) {
            if (in_array($id, $aktOrg, false)) {
                $xtpl->assign('vypravecSelected', 'selected');
            } else {
                $xtpl->assign('vypravecSelected', '');
            }
            $xtpl->assign('vypravecId', $id);
            $xtpl->assign('vypravecJmeno', $org);
            $xtpl->parse('upravy.tabulka.vypraveci.vypravec');
        }
        $xtpl->parse('upravy.tabulka.vypraveci');
    }

    private static function parseUpravyTabulkaTypy(
        ?Aktivita $aktivita,
        XTemplate $xtpl,
    ) {
        $aktivitaData = $aktivita
            ? $aktivita->a
            : null; // databázový řádek
        // typ s id 0 je (bez typu – organizační) a ten chceme první
        $sKladnymPoradim = dbFetchAll('SELECT id_typu, typ_1p FROM akce_typy WHERE aktivni = 1 AND (poradi > 0 OR id_typu = 0) ORDER BY poradi');
        // typy se záporným pořadím jsou technické, brigádnické a tak
        $seZapornymPoradim = dbFetchAll('SELECT id_typu, typ_1p FROM akce_typy WHERE aktivni = 1 AND poradi < 0 AND id_typu != 0 ORDER BY poradi DESC');
        foreach ([...$sKladnymPoradim, ...$seZapornymPoradim] as $akceTypData) {
            $xtpl->assign('selected', $aktivita && $akceTypData['id_typu'] == $aktivitaData[Sql::TYP]
                ? 'selected'
                : '');
            $xtpl->assign($akceTypData);
            $xtpl->parse('upravy.tabulka.typ');
        }
    }

    private static function nactiTagy(
        array      $vybraneTagy,
        EditorTagu $editorTagu,
        XTemplate  $xtpl,
    ) {
        $vsechnyTagy             = $editorTagu->getTagy();
        $pocetVsechTagu          = count($vsechnyTagy);
        $nazevPredchoziKategorie = null;
        foreach ($vsechnyTagy as $indexTagu => $mappedTag) {
            $encodedTag = [];
            foreach ($mappedTag as $tagKey => $tagValue) {
                $encodedTag[$tagKey] = htmlspecialchars($tagValue, ENT_QUOTES | ENT_HTML5);
            }
            $jeNovaKategorie = $nazevPredchoziKategorie !== $encodedTag['nazev_kategorie'];
            $xtpl->assign('id_tagu', $encodedTag['id']);
            $xtpl->assign('nazev_tagu', $encodedTag['nazev']);
            $xtpl->assign('tag_selected', in_array($encodedTag['nazev'], $vybraneTagy, true)
                ? 'selected'
                : '');
            $xtpl->assign(
                'previous_optgroup_tag_end',
                $jeNovaKategorie
                    ? '</optgroup>'
                    : '',
            );
            $xtpl->assign(
                'optgroup_tag_start',
                $jeNovaKategorie
                    ? '<optgroup label="' . mb_ucfirst($encodedTag['nazev_kategorie']) . '">'
                    : '',
            );
            $xtpl->assign(
                'last_optgroup_tag_end',
                $indexTagu + 1 === $pocetVsechTagu
                    ? '</optgroup>'
                    : '',
            );
            $xtpl->parse('upravy.tabulka.tag');
            $nazevPredchoziKategorie = $encodedTag['nazev_kategorie'];
        }
    }

    /**
     * Vrátí, jestli se volající stránka snaží získat JSON data pro ověření formu
     */
    public static function editorTestJson()
    {
        if (isset($_POST[self::AJAXKLIC]))
            return true;
        else
            return false;
    }

    /**
     * Zpracuje data odeslaná formulářem s vloženým editorem
     * vrací null pokud se nic nestalo nebo aktualizovaný objekt Aktivita,
     *   pokud k nějaké aktualizaci došlo.
     */
    public static function editorZpracuj(): ?Aktivita
    {
        if (!isset($_POST[self::POSTKLIC])) {
            return null;
        }

        // úprava přijatých dat
        $a = (array)$_POST[self::POSTKLIC];
        // v případě nezobrazení tabulky a tudíž chybějícího text. pole s url (viz šablona) se použije hidden pole s původní url
        if (empty($a[Sql::URL_AKCE]) && !empty($_POST[self::POSTKLIC . 'staraUrl'])) {
            $a[Sql::URL_AKCE] = $_POST[self::POSTKLIC . 'staraUrl'];
        }
        if (empty($a[Sql::URL_AKCE])) {
            $a[Sql::URL_AKCE] = RemoveDiacritics::toConstantLikeValue($a[Sql::NAZEV_AKCE]);
            $zakladUrl        = $a[Sql::URL_AKCE];
            $poradiUrl        = 1;
            while (self::urlJeObsazena($a)) {
                $poradiUrl++;
                $a[Sql::URL_AKCE] = $zakladUrl . '-' . $poradiUrl;
            }
        }
        // přepočet času
        if (empty($a['den']) || empty($a[Sql::ZACATEK]) || empty($a[Sql::KONEC])) {
            if (!empty($a['den']) || !empty($a[Sql::ZACATEK]) || !empty($a[Sql::KONEC])) {
                chyba('Buďto vyplň den se začátkem i koncem, nebo nic. Čas byl zrušen.', false);
            }
            $a[Sql::ZACATEK] = null;
            $a[Sql::KONEC]   = null;
        } else {
            $zacatekDen      = Program::denAktivityDleZacatku($a);
            $a[Sql::ZACATEK] = ($zacatekDen)->add(new \DateInterval('PT' . $a[Sql::ZACATEK] . 'H'))->formatDb();

            $konecDen = Program::denAktivityDleKonce($a);

            $a[Sql::KONEC] = ($konecDen)->add(new \DateInterval('PT' . $a[Sql::KONEC] . 'H'))->formatDb();
        }
        unset($a['den']);
        // extra položky kvůli sep. tabulkám
        $organizatori = $a['organizatori'] ?? [];
        unset($a['organizatori']);
        $popis = $a[Sql::POPIS];
        unset($a[Sql::POPIS]);

        $a[Sql::DITE] = !empty($a[Sql::DITE])
            ? implode(
                ',',
                array_map(static function (
                    $diteId,
                ) {
                    return (int)$diteId;
                }, $a[Sql::DITE]),
            )
            : null;

        $rodiceIds = [];
        if (!empty($a['rodic'])) {
            $rodiceIds = array_map(static function (
                $rodicId,
            ) {
                return (int)$rodicId;
            }, $a['rodic']);
            unset($a['rodic']);
        }

        if (!empty($a[Sql::TEAMOVA]) && isset($a[Sql::TEAM_MIN], $a[Sql::TEAM_MAX]) && $a[Sql::TEAM_MIN] > $a[Sql::TEAM_MAX]) {
            chyba(
                sprintf(
                    'Minimální kapacita týmu (%d) nemůže být větší než maximální kapacita týmu (%d). Kapacity týmu byly zrušeny.',
                    $a[Sql::TEAM_MIN],
                    $a[Sql::TEAM_MAX],
                ),
                false,
            );
            unset($a[Sql::TEAMOVA], $a[Sql::TEAM_MIN], $a[Sql::TEAM_MAX]);
        }

        $chyby = self::editorChyby($a);
        if ($chyby) {
            varovani(implode('; ', $chyby), false);
        }

        $tagIds = [];
        foreach ((array)post(self::TAGYKLIC) as $tagId) {
            $tagId = (int)$tagId;
            if ($tagId) {
                $tagIds[] = $tagId;
            }
        }

        $obrazekSoubor = postFile(self::OBRAZEK_KLIC);
        $obrazekUrl    = post(self::OBRAZEK_KLIC . 'Url');

        $odmenaZaHodinu = (int)post(self::ODMENA_ZA_HODINU_KLIC);

        $aktivita = self::uloz($a, $popis, $organizatori, $tagIds, $obrazekSoubor, $obrazekUrl, $odmenaZaHodinu);
        self::varujBylaLiMistnostObsazena($aktivita);

        if ($rodiceIds) {
            $detiIds    = $aktivita->detiIds();
            $rodicIDite = [];
            foreach ($rodiceIds as $rodicId) {
                $rodic = self::zId($rodicId);
                if ($rodic) {
                    if (in_array($rodicId, $detiIds, false)) {
                        $rodicIDite[] = $rodic;
                    } else {
                        $rodic->pridejDite($aktivita->id());
                    }
                }
            }
            if ($rodicIDite) {
                chyba(
                    'Aktivita nemůže být "dítě" a zároveň "rodič" jedné a té samé aktivitě. Tyto nebyly nastaveny jako rodiče: '
                    . implode(', ', array_map([__CLASS__, 'dejRozsirenyNazevAktivity'], $rodicIDite)),
                    false,
                );
            }
        }

        oznameni('Aktivita byla uložena', false);

        return $aktivita;
    }

    private static function varujBylaLiMistnostObsazena(Aktivita $aktivita)
    {
        $lokaceId = $aktivita->lokaceId();
        if (!$lokaceId) {
            return;
        }
        $result = ImportSqlMappedValuesChecker::checkLocationByAccessibility(
            $lokaceId,
            $aktivita->zacatek()?->format(DateTimeCz::FORMAT_DB),
            $aktivita->konec()?->format(DateTimeCz::FORMAT_DB),
            $aktivita,
            $aktivita->typ(),
            new ImportValuesDescriber(Urls::urlAdminDetailAktivity(null)),
        );
        if ($result->hasWarnings()) {
            foreach ($result->getWarnings() as $warning) {
                varovani($warning, false);
            }
        }
        if ($result->hasErrorLikeWarnings()) {
            foreach ($result->getErrorLikeWarnings() as $errorLikeWarning) {
                chyba($errorLikeWarning, false);
            }
        }
        if ($result->hasError()) {
            chyba($result->getError(), false);
        }
    }

    public static function uloz(
        array   $data,
        ?string $markdownPopis,
        array   $organizatoriIds,
        array   $tagIds,
        string  $obrazekSoubor = null,
        string  $obrazekUrl = null,
        int     $odmenaZaHodinu = null,
    ): Aktivita {
        $data[Sql::BEZ_SLEVY]    = (int)!empty($data[Sql::BEZ_SLEVY]);    // checkbox pro "bez_slevy"
        $data[Sql::NEDAVA_BONUS] = (int)!empty($data[Sql::NEDAVA_BONUS]); // checkbox pro "nedava_bonus"
        $nastavujeKorekci        = empty($data[Sql::ID_AKCE]) /* nová aktivita */
                                   || array_key_exists(Sql::PROBEHLA_KOREKCE, $data); /* checkbox pro korekci se zobrazil na základě práv */
        if (array_key_exists(Sql::PROBEHLA_KOREKCE, $data)/** editace korekce; reakce na změnu textu viz @see popis */
        ) {
            $data[Sql::PROBEHLA_KOREKCE] = (int)!empty($data[Sql::PROBEHLA_KOREKCE]); // checkbox pro "probehla_korekce"
        }
        $data[Sql::CENA] = (int)($data[Sql::CENA] ?? 0);

        if (empty($data[Sql::POPIS]) && empty($data[Sql::ID_AKCE])) {
            $data[Sql::POPIS] = 0; // uložíme později jako jako $markdownPopis,teď jenom vyřešíme "Field Sql::POPIS doesn't have a default value"
        }

        $teamova             = !empty($data[Sql::TEAMOVA]);
        $data[Sql::TEAMOVA]  = (int)$teamova; //checkbox pro "teamova"
        $data[Sql::TEAM_MIN] = $teamova
            ? (int)$data[Sql::TEAM_MIN]
            : null;
        $data[Sql::TEAM_MAX] = $teamova
            ? (int)$data[Sql::TEAM_MAX]
            : null;

        if ($teamova) {
            // Vedoucí týmu může ručně nastavit kapacitu nižší, dokud je větší rovna team_min. V takovém
            // případě se NESMÍ kapacita změnit při např. úpravě popisu aktivity z adminu. DB trigger
            // trigger_check_and_apply_team_limit toto zajišťuje s pomocí sloupce team_limit
            $data[Sql::KAPACITA]   = $data[Sql::TEAM_MAX] ?? 0;
            $data[Sql::KAPACITA_F] = 0;
            $data[Sql::KAPACITA_M] = 0;
        } else {
            $data[Sql::KAPACITA]   = !empty($data[Sql::KAPACITA])
                ? (int)$data[Sql::KAPACITA]
                : 0;
            $data[Sql::KAPACITA_F] = !empty($data[Sql::KAPACITA_F])
                ? (int)$data[Sql::KAPACITA_F]
                : 0;
            $data[Sql::KAPACITA_M] = !empty($data[Sql::KAPACITA_M])
                ? (int)$data[Sql::KAPACITA_M]
                : 0;
        }

        $data[Sql::PATRI_POD] = !empty($data[Sql::PATRI_POD])
            ? $data[Sql::PATRI_POD]
            : null;
        $data[Sql::LOKACE]    = !empty($data[Sql::LOKACE])
            ? $data[Sql::LOKACE]
            : null;

        if (!empty($data[Sql::TYP]) && (int)$data[Sql::TYP] === TypAktivity::BRIGADNICKA && $odmenaZaHodinu
            && !empty($data[Sql::ZACATEK]) && !empty($data[Sql::KONEC])
        ) {
            $trvaniVSekundach = (new \DateTimeImmutable($data[Sql::KONEC]))->getTimestamp() - (new \DateTimeImmutable($data[Sql::ZACATEK]))->getTimestamp();
            if ($trvaniVSekundach) {
                $trvaniVHodinach = (int)round($trvaniVSekundach / 3600);
                if ($trvaniVHodinach > 0) {
                    $data[Sql::CENA] = $odmenaZaHodinu * $trvaniVHodinach;
                }
            }
        }

        // uložení změn do akce_seznam
        if (empty($data[Sql::PATRI_POD]) && !empty($data[Sql::ID_AKCE])) {
            // editace jediné aktivity
            dbInsertUpdate('akce_seznam', $data);
            $aktivita = self::zId($data[Sql::ID_AKCE]);
        } elseif (!empty($data[Sql::PATRI_POD])) {
            // editace aktivity z rodiny instancí
            $doHlavni   = [Sql::URL_AKCE, Sql::POPIS, Sql::VYBAVENI];    // věci, které se mají změnit jen u hlavní (main) `instance
            $doAktualni = [Sql::LOKACE, Sql::ZACATEK, Sql::KONEC];       // věci, které se mají změnit jen u aktuální instance
            $aktivita   = self::zId($data[Sql::ID_AKCE]);       // instance už musí existovat
            if (array_key_exists(ActivitiesImportSqlColumn::STAV, $data)) {
                $aktivita->zmenStav($data[ActivitiesImportSqlColumn::STAV]);
                unset($data[ActivitiesImportSqlColumn::STAV]); // stav se může měnit jenom u jedné instance
            }
            // (zbytek se změní v obou)
            // určení hlavní aktivity
            $idHlavni = $aktivita->idHlavni();
            $patriPod = $data[Sql::PATRI_POD];
            unset($data[Sql::PATRI_POD]); // změny v hlavní aktivitě
            $zmenyHlavni               = array_diff_key($data, array_flip($doAktualni));
            $zmenyHlavni[Sql::ID_AKCE] = $idHlavni;
            dbInsertUpdate('akce_seznam', $zmenyHlavni); // změny v konkrétní instanci
            $zmenyAktualni = array_diff_key($data, array_flip($doHlavni));
            dbInsertUpdate('akce_seznam', $zmenyAktualni); // změny u všech
            $zmenyVse = array_diff_key($data, array_flip(array_merge($doHlavni, $doAktualni)));
            unset($zmenyVse[Sql::PATRI_POD], $zmenyVse[Sql::ID_AKCE]); // id se nesmí updatovat!
            dbUpdate('akce_seznam', $zmenyVse, [Sql::PATRI_POD => $patriPod]);
        } else {
            // vkládání nové aktivity
            // inicializace hodnot pro novou aktivitu
            $data[Sql::ID_AKCE] = null;
            $data['rok']        = ROCNIK;
            if ($data[Sql::TEAMOVA]) $data[Sql::KAPACITA] = $data[Sql::TEAM_MAX] ?? 0; // při vytváření nové aktivity se kapacita inicializuje na max. teamu
            if (empty($data[Sql::NAZEV_AKCE])) $data[Sql::NAZEV_AKCE] = '(bez názvu)';
            if (empty($data[Sql::STAV])) {
                $data[Sql::STAV] = StavAktivity::NOVA;
            }
            if (!array_key_exists(Sql::PROBEHLA_KOREKCE, $data) && empty($data[Sql::ID_AKCE])) {
                $data[Sql::PROBEHLA_KOREKCE] = (int)dbOneCol(
                    'SELECT EXISTS(
                            SELECT 1
                            FROM akce_seznam a
                            WHERE a.nazev_akce = $0
                            AND a.popis_kratky = $2
                            AND a.probehla_korekce = 1
                            AND EXISTS(SELECT 1 FROM texty t JOIN akce_seznam aa ON aa.popis = t.id WHERE t.text = $1 AND aa.probehla_korekce = 1)
                        )',
                    [0 => $data[Sql::NAZEV_AKCE], 1 => $data[Sql::POPIS], 2 => $data[Sql::POPIS_KRATKY]],
                );
            }
            // vložení
            dbInsertUpdate(Sql::AKCE_SEZNAM_TABULKA, $data);
            $data[Sql::ID_AKCE] = dbInsertId();
            $aktivita           = self::zId($data[Sql::ID_AKCE]);
            $aktivita->nova     = true;
        }

        if ($obrazekSoubor) {
            $aktivita->obrazek(\Obrazek::zSouboru($obrazekSoubor));
        } elseif ($obrazekUrl) {
            $aktivita->obrazek(\Obrazek::zUrl($obrazekUrl));
        }
        $aktivita->organizatori($organizatoriIds);
        $aktivita->popis($markdownPopis, resetujKorekci: !$nastavujeKorekci);
        $aktivita->nastavTagyPodleId($tagIds);

        return $aktivita;
    }

    public function id(): int
    {
        return (int)$this->a[Sql::ID_AKCE];
    }

    /**
     * @return Aktivita[] pole instancí této aktivity (vč. sebe sama, i pokud více
     *  instancí nemá)
     */
    private function instance(): array
    {
        if ($this->patriPod()) {
            $ids = dbOneArray('SELECT id_akce FROM akce_seznam WHERE patri_pod = $1', [$this->patriPod()]);

            return Aktivita::zIds($ids);
        }

        return [$this];
    }

    /**
     * @return int[] pole s ID instancí této aktivity (vč. sebe sama, i pokud více *  instancí nemá)
     */
    private function idInstanci(): array
    {
        if ($this->patriPod()) {
            $ids = dbOneArray('SELECT id_akce FROM akce_seznam WHERE patri_pod = $1', [$this->patriPod()]);

            return array_map(static function (
                $id,
            ) {
                return (int)$id;
            }, $ids);
        }

        return [$this->id()];
    }

    public function pocetInstanci(): int
    {
        if (!$this->a[Sql::PATRI_POD]) {
            return 0;
        }

        return (int)dbOneCol('SELECT COUNT(*) FROM akce_seznam WHERE patri_pod = $1', [$this->a[Sql::PATRI_POD]]);
    }

    public function hlavni(): Aktivita
    {
        if (!$this->a[Sql::PATRI_POD]) {
            return $this;
        }

        return static::zId($this->idHlavni());
    }

    public function idHlavni(): int
    {
        if (!$this->a[Sql::PATRI_POD]) {
            return $this->id();
        }
        $idHlavniAkce = dbOneCol('SELECT id_hlavni_akce FROM akce_instance WHERE id_instance = ' . $this->a[Sql::PATRI_POD]);
        if ($idHlavniAkce) {
            return (int)$idHlavniAkce;
        }
        throw new \RuntimeException("Chybí záznam o hlavní aktivitě pro instanci {$this->a[Sql::PATRI_POD]}");
    }

    public function jeHlavni(): bool
    {
        return $this->idHlavni() === $this->id();
    }

    public function jeInstance(): bool
    {
        return !$this->jeHlavni();
    }

    /**
     * Vytvoří novou instanci aktivity
     * @return self nově vytvořená instance
     */
    public function instancuj()
    {
        $akt = dbOneLine('SELECT * FROM akce_seznam WHERE id_akce=' . $this->id());
        //odstraníme id, url a popisek, abychom je nepoužívali/neduplikovali při vkládání
        //stav se vloží implicitní hodnota v DB
        unset($akt[Sql::ID_AKCE], $akt[Sql::URL_AKCE], $akt[Sql::STAV], $akt[Sql::ZAMCEL]);
        $akt[Sql::VYBAVENI] = '';
        if ($akt[Sql::TEAMOVA]) {
            $akt[Sql::KAPACITA] = $akt[Sql::TEAM_MAX];
        }
        if ($akt[Sql::PATRI_POD]) { //aktivita už má instanční skupinu, použije se stávající
            dbInsert('akce_seznam', $akt);
            $idNoveAktivity = dbInsertId();
        } else { //aktivita je zatím bez instanční skupiny - vytvoříme
            dbBegin();
            try {
                $patriPod            = $this->vytvorInstanci();
                $akt[Sql::PATRI_POD] = $patriPod;
                dbInsert('akce_seznam', $akt);
                $idNoveAktivity = dbInsertId();
                dbCommit();
            } catch (\Exception $exception) {
                dbRollback();
                throw $exception;
            }
        }

        // nastavení vlastností pomocí OO rozhraní
        $novaAktivita = self::zId($idNoveAktivity);
        $novaAktivita->nastavTagy($this->tagy());

        return $novaAktivita;
    }

    private function vytvorInstanci(): int
    {
        if ($this->patriPod()) {
            return $this->patriPod();
        }
        dbQuery('INSERT INTO akce_instance(id_hlavni_akce) VALUES ($1)', [$this->id()]);
        $patriPod = dbInsertId();
        dbQuery('UPDATE akce_seznam SET patri_pod = $1 WHERE id_akce = $2', [$patriPod, $this->id()]); // update původní aktivity

        return $patriPod;
    }

    /**
     * @param self[] $aktivity
     * @return bool jestli zadané aktivity jsou platným výběrem dalších kol
     *  stávající aktivity
     */
    protected function jsouDalsiKola(array $aktivity)
    {
        $dalsiKola = $this->dalsiKola();

        if (count($aktivity) !== count($dalsiKola)) {
            return false;
        }

        foreach ($this->dalsiKola() as $i => $varianty) {
            $idsVariant = [];
            foreach ($varianty as $varianta) {
                $idsVariant[] = $varianta->id();
            }

            $idVybraneVarianty = $aktivity[$i]->id();

            if (!in_array($idVybraneVarianty, $idsVariant)) {
                return false;
            }
        }

        return true;
    }

    /** Vrací celkovou kapacitu aktivity */
    public function kapacita()
    {
        return $this->a[Sql::KAPACITA] + $this->a[Sql::KAPACITA_M] + $this->a[Sql::KAPACITA_F];
    }

    /**
     * @return array<int, Aktivita>
     */
    protected function kolekce(): array
    {
        return $this->kolekce;
    }

    /** Vrátí DateTime objekt konce aktivity */
    public function konec(): ?DateTimeCz
    {
        if ($this->a[Sql::KONEC] && is_string($this->a[Sql::KONEC])) {
            $this->a[Sql::KONEC] = new DateTimeCz($this->a[Sql::KONEC]);
        }

        return $this->a[Sql::KONEC]
            ?: null;
    }

    /**
     * @return string krátký popis aktivity (plaintext)
     */
    public function kratkyPopis()
    {
        return $this->a['popis_kratky'];
    }

    /** Vrátí lokaci (ndef. formát, ale musí podporovat __toString) */
    public function lokace(): ?\Lokace
    {
        if (is_numeric($this->lokace)) {
            $this->prednactiN1([
                'atribut' => Sql::LOKACE,
                'cil'     => \Lokace::class,
            ]);
        }

        return $this->lokace
            ?: null;
    }

    public function lokaceId(): ?int
    {
        return $this->a[Sql::LOKACE] !== null
            ? (int)$this->a[Sql::LOKACE]
            : null;
    }

    /**
     * Vrátí pole uživatelů, kteří jsou sledujícími na aktivitě .
     * @return Uzivatel[]
     */
    public function seznamSledujicich(): array
    {
        return $this->seznamUcastniku()[StavPrihlaseni::SLEDUJICI] ?? [];
    }

    /**
     * Vrátí pole uživatelů, kteří jsou přihlášeni k aktivitě, nebo na ní dokonce už dorazili .
     * @return Uzivatel[]
     */
    public function seznamPrihlasenychNeboDorazivsich(): array
    {
        return [
            ...$this->seznamUcastniku()[StavPrihlaseni::PRIHLASEN] ?? [],
            ...$this->seznamUcastniku()[StavPrihlaseni::PRIHLASEN_A_DORAZIL] ?? [],
            ...$this->seznamUcastniku()[StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK] ?? [],
        ];
    }

    /**
     * @return array<\Uzivatel>
     */
    private function seznamUcastniku(?DataSourcesCollector $dataSourcesCollector = null): array
    {
        self::seznamUcastnikuDSC($dataSourcesCollector);

        if (!isset($this->seznamUcastniku)) {
            if ($this->prednacitat) {
                if (!array_key_exists($this->id(), self::$seznamUcastnikuCache)) {
                    // pouze pokud je někdo na aktivitu přihlášen, jinak nemá cenu zkoušet načítat její účastníky
                    if ($this->prihlaseniRaw($dataSourcesCollector) !== '') {
                        // array + array přidá nové záznamy s novými klíči, ale nepřepíše původní
                        self::$seznamUcastnikuCache += self::seznamUcastnikuAktivit(
                            Sql::AKCE_SEZNAM_TABULKA . '.' . Sql::ROK . '=' . $this->systemoveNastaveni->rocnik(),
                            $dataSourcesCollector,
                        );
                    }
                }
                self::$seznamUcastnikuCache[$this->id()] ??= [];
                $this->seznamUcastniku                   = self::$seznamUcastnikuCache[$this->id()];
            } else {
                $this->seznamUcastniku = self::seznamUcastnikuAktivit(
                    'zdroj.id_akce=' . $this->id(),
                    $dataSourcesCollector,
                )[$this->id()] ?? [];
            }
        }

        return $this->seznamUcastniku;
    }

    private static function seznamUcastnikuDSC(?DataSourcesCollector $dataSourcesCollector): void
    {
        self::prihlaseniRawDSC($dataSourcesCollector);
        self::seznamUcastnikuAktivitDSC($dataSourcesCollector);
    }

    private static function seznamUcastnikuAktivit(
        string                $where = 'TRUE',
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): array {
        self::seznamUcastnikuAktivitDSC($dataSourcesCollector);

        $data = dbFetchAll(
            <<<SQL
                SELECT zdroj.id_akce, zdroj.id_uzivatele, zdroj.id_stavu_prihlaseni
                FROM akce_prihlaseni AS zdroj
                JOIN akce_seznam on zdroj.id_akce = akce_seznam.id_akce
                WHERE {$where}
                UNION ALL
                SELECT zdroj.id_akce, zdroj.id_uzivatele, zdroj.id_stavu_prihlaseni
                FROM akce_prihlaseni_spec AS zdroj
                JOIN akce_seznam on zdroj.id_akce = akce_seznam.id_akce
                WHERE {$where}
            SQL,
        );

        $idsUcasnikuPodleAkceAStavu = [];
        foreach ($data as [Sql::ID_AKCE => $idAktivity, 'id_uzivatele' => $idUzivatele, 'id_stavu_prihlaseni' => $idStavuPrilaseni]) {
            $idsUcasnikuPodleAkceAStavu[$idAktivity][$idStavuPrilaseni][] = $idUzivatele;
        }
        $seznamUcastniku = [];
        foreach ($idsUcasnikuPodleAkceAStavu as $idAktivity => $idsUcasnikuPodleStavu) {
            foreach ($idsUcasnikuPodleStavu as $idStavuPrihlaseni => $idsUcasnikuSeStejnymStavem) {
                $seznamUcastniku[(int)$idAktivity][(int)$idStavuPrihlaseni] = Uzivatel::zIds(
                    $idsUcasnikuSeStejnymStavem,
                    true,
                );
            }
        }

        return $seznamUcastniku;
    }

    private static function seznamUcastnikuAktivitDSC(
        ?DataSourcesCollector $dataSourcesCollector,
    ): void {
        $dataSourcesCollector?->addDataSources([
            Sql::AKCE_SEZNAM_TABULKA,
            AkcePrihlaseniSqlStruktura::AKCE_PRIHLASENI_TABULKA,
            AkcePrihlaseniSpecSqlStruktura::AKCE_PRIHLASENI_SPEC_TABULKA,
        ]);
        Uzivatel::zIdsDSC($dataSourcesCollector);
    }

    public function nazev(): string
    {
        return (string)$this->a[Sql::NAZEV_AKCE];
    }

    /**
     * Aktivita negeneruje slevu ("bonus") organizátorovi
     * @deprecated Použij nedavaBonus
     */
    public function nedavaSlevu(): bool
    {
        return $this->nedavaBonus();
    }

    /**
     * @return bool Zda vypravěč za vedení této aktivity nedostane od Gameconu finanční bonus.
     */
    public function nedavaBonus()
    {
        return (bool)$this->a[Sql::NEDAVA_BONUS];
    }

    /**
     * Jestli objekt aktivity představuje nově vytvořený řádek v databázi, nebo
     * byl jenom z DB načten.
     * @return bool false - načtený z databáze, true - nový, vložený do databáze
     */
    public function nova()
    {
        return $this->nova;
    }

    /**
     * Vrací absolutní adresu k obrázku aktivity. Ošetřeno cacheování.
     */
    public function obrazek(\Obrazek $obrazek = null): ?\Nahled
    {
        $soubor = $this->cestaObrazku();
        if (!$obrazek) {
            try {
                return \Nahled::zeSouboru($soubor)->pasuj(400);
            } catch (\Exception $e) {
                return null;
            }
        }
        $obrazek->fitCrop(2048, 2048);
        $obrazek->uloz($soubor);

        return null;
    }

    public function cestaObrazku(): string
    {
        return rtrim(WWW, '/') . '/soubory/systemove/aktivity/' . $this->a[Sql::URL_AKCE] . '.jpg';
    }

    public function maObrazek(): bool
    {
        return file_exists($this->cestaObrazku());
    }

    public function urlObrazku(): string
    {
        return rtrim(URL_WEBU, '/') . '/soubory/systemove/aktivity/' . $this->a[Sql::URL_AKCE] . '.jpg';
    }

    /** (Správný) alias pro obsazenostHtml() */
    public function obsazenost()
    {
        return $this->obsazenostHtml();
    }

    /** Vrátí html kód s políčky určujícímí obsazenost */
    public function obsazenostHtml()
    {
        $prihlasenoMuzu      = $this->pocetPrihlasenychMuzu(); // počty
        $prihlasenoZen       = $this->pocetPrihlasenychZen();
        $prihlasenoCelkem    = $prihlasenoMuzu + $prihlasenoZen;
        $kapacitaMuzi        = (int)$this->a[Sql::KAPACITA_M]; // kapacity
        $kapacitaZeny        = (int)$this->a[Sql::KAPACITA_F];
        $kapacitaUniverzalni = (int)$this->a[Sql::KAPACITA];
        $kapacitaCelkova     = $kapacitaUniverzalni + $kapacitaMuzi + $kapacitaZeny;
        if (!$kapacitaCelkova) {
            return '';
        }
        if (!$this->prihlasovatelna() && !$this->probehnuta()) { // u proběhnutých aktivit se zobrazí čísla. Možno měnit.
            return " <span class=\"neprihlasovatelna\">($prihlasenoCelkem/$kapacitaCelkova)</span>";
        }
        switch ($this->volno()) {
            case 'u':
            case 'x':
                return " ($prihlasenoCelkem/$kapacitaCelkova)";
            case 'f':
                return ' <span class="f">(' . $prihlasenoZen . '/' . $kapacitaZeny . ')</span>' .
                       ' <span class="m">(' . $prihlasenoMuzu . '/' . ($kapacitaMuzi + $kapacitaUniverzalni) . ')</span>';
            case 'm':
                return ' <span class="f">(' . $prihlasenoZen . '/' . ($kapacitaZeny + $kapacitaUniverzalni) . ')</span>' .
                       ' <span class="m">(' . $prihlasenoMuzu . '/' . $kapacitaMuzi . ')</span>';
            default :
                return '';
        }
    }

    public function obsazenostObj(?DataSourcesCollector $dataSourcesCollector = null)
    {
        $prihlasenoMuzu      = $this->pocetPrihlasenychMuzu($dataSourcesCollector); // počty
        $prihlasenoZen       = $this->pocetPrihlasenychZen($dataSourcesCollector);
        $kapacitaMuzi        = (int)$this->a[Sql::KAPACITA_M]; // kapacity
        $kapacitaZeny        = (int)$this->a[Sql::KAPACITA_F];
        $kapacitaUniverzalni = (int)$this->a[Sql::KAPACITA];

        return [
            'm'  => $prihlasenoMuzu,
            'f'  => $prihlasenoZen,
            'km' => $kapacitaMuzi,
            'kf' => $kapacitaZeny,
            'ku' => $kapacitaUniverzalni,
        ];
    }

    /**
     * Odhlásí uživatele z aktivity
     * @todo kontroly? (např. jestli je aktivní přihlašování?) (administrativní
     *  odhlašování z DrD počítá s možnosti odhlásit např. od semifinále dál)
     */
    public function odhlas(
        \Uzivatel $u,
        \Uzivatel $odhlasujici,
        string    $zdrojOdhlaseni,
                  $params = 0,
    ) {
        foreach ($this->deti() as $dite) {                    // odhlášení z potomků
            $dite->odhlas($u, $odhlasujici, $zdrojOdhlaseni); // spoléhá na odolnost proti odhlašování z aktivit kde uživatel není
        }
        if (!$this->prihlasen($u)) {
            return; // ignorovat pokud přihlášen není tak či tak
        }
        // reálné odhlášení
        $idAktivity  = $this->id();
        $idUzivatele = $u->id();
        $prihlasenOd = $this->prihlasenOd($u) ?? $this->systemoveNastaveni->ted(); // prihlasenOd by tady mělo být vždy, ale pro jistotu...
        dbQuery("DELETE FROM akce_prihlaseni WHERE id_uzivatele=$idUzivatele AND id_akce=$idAktivity");
        $this->dejPrezenci()->zalogujOdhlaseni($u, $odhlasujici, $zdrojOdhlaseni);
        if ($this->systemoveNastaveni->kontrolovatPokutuZaOdhlaseni()
            && !($params & self::BEZ_POKUT)
            && (
                $this->zbyvaHodinDoZacatku() < $this->systemoveNastaveni->kolikHodinPredAktivitouUzJePokutaZaOdhlaseni()
                && $this->nestihlRychleOdhlaseniBezPokuty($prihlasenOd, $this->systemoveNastaveni)
            )
        ) { // pokuta aktivní
            dbQuery(<<<SQL
INSERT INTO akce_prihlaseni_spec SET id_uzivatele=$idUzivatele, id_akce=$idAktivity, id_stavu_prihlaseni=$0
ON DUPLICATE KEY UPDATE id_stavu_prihlaseni=$0
SQL,
                [StavPrihlaseni::POZDE_ZRUSIL],
            );
        }
        if ($this->a[Sql::ZAMCEL] == $idUzivatele) {
            dbQuery("UPDATE akce_seznam SET zamcel=NULL, zamcel_cas=NULL, team_nazev=NULL WHERE id_akce=$idAktivity");
        }
        if ($this->a[Sql::TEAMOVA] && $this->pocetPrihlasenych() === 1) { // odhlašuje se poslední hráč
            dbQuery("UPDATE akce_seznam SET kapacita=team_max WHERE id_akce=$idAktivity");
        }
        // Poslání mailu lidem na watchlistu
        if ($this->volno() === "x" && !($params & self::NEPOSILAT_MAILY_SLEDUJICIM)) { // Před odhlášením byla aktivita plná
            $this->poslatMailSledujicim();
        }
        $this->refresh();
    }

    private function nestihlRychleOdhlaseniBezPokuty(
        \DateTimeImmutable $prihlasenOd,
        SystemoveNastaveni $systemoveNastaveni,
    ): bool {
        return $prihlasenOd->modify("+{$systemoveNastaveni->kolikMinutJeOdhlaseniBezPokuty()} minutes")->getTimestamp() < $systemoveNastaveni->ted()->getTimestamp();
    }

    public function dejPrezenci(): AktivitaPrezence
    {
        if (!$this->prezence) {
            $this->prezence = new AktivitaPrezence($this, $this->systemoveNastaveni, $this->dejFilesystem());
        }

        return $this->prezence;
    }

    private function dejFilesystem(): Filesystem
    {
        if (!$this->filesystem) {
            $this->filesystem = new Filesystem();
        }

        return $this->filesystem;
    }

    /**
     * Odhlásí uživatele z náhradníků (watchlistu)
     */
    public function odhlasSledujiciho(
        Uzivatel  $u,
        ?Uzivatel $odhlasujici,
    ) {
        if (!$u->prihlasenJakoSledujici($this)) { // Ignorovat pokud není přihlášen jako sledující
            return;
        }
        // Uložení odhlášení do DB
        dbQuery(
            "DELETE FROM akce_prihlaseni_spec WHERE id_uzivatele=$0 AND id_akce=$1 AND id_stavu_prihlaseni=$2",
            [$u->id(), $this->id(), StavPrihlaseni::SLEDUJICI],
        );
        $this->dejPrezenci()->zalogujZeSeOdhlasilJakoSledujici($u, $odhlasujici);
        $this->refresh();
    }

    /**
     * Odhlásí ze všech sledování aktivit ve stejný čas jako aktivita po přihlášení na aktivitu.
     * @return bool True pokud došlo k odhlášení nějakých sledování
     */
    public function odhlasZeSledovaniAktivitVeStejnemCase(
        Uzivatel $u,
        Uzivatel $odhlasujici,
    ): bool {
        $konfliktniAktivity = self::zIds(dbOneArray("
      SELECT p.id_akce
      FROM akce_prihlaseni_spec p
      JOIN akce_seznam a ON a.id_akce = p.id_akce
      WHERE
        p.id_stavu_prihlaseni = $3 AND
        p.id_uzivatele = $0 AND
        NOT (a.konec <= $1 OR $2 <= a.zacatek) -- aktivita 'a' se kryje s aktuální aktivitou
    ", [
            $u->id(), $this->a[Sql::ZACATEK], $this->a[Sql::KONEC], StavPrihlaseni::SLEDUJICI,
        ]));
        foreach ($konfliktniAktivity as $aktivita) {
            $aktivita->odhlasSledujiciho($u, $odhlasujici);
        }

        return count($konfliktniAktivity) > 0;
    }

    /** Vráti aktivitu ze stavu připravená do stavu publikovaná */
    public function odpriprav()
    {
        if ($this->idStavu() === StavAktivity::PUBLIKOVANA) {
            return;
        }
        if ($this->idStavu() !== StavAktivity::PRIPRAVENA) {
            throw new \Chyba('Aktivita není v stavu "připravená"');
        }
        dbQuery('UPDATE akce_seznam SET stav=$1 WHERE id_akce=$2', [StavAktivity::PUBLIKOVANA, $this->id()]);
    }

    /**
     * Vrátí pole uživatelů, kteří jsou organizátory této aktivity. Při zadaném
     * parametru poli ID nastaví tyto organizátory.
     * @return Uzivatel[]|void
     */
    public function organizatori(
        array                 $ids = null,
        ?DataSourcesCollector $dataSourcesCollector = null,
    ) {
        Uzivatel::zIdsDSC($dataSourcesCollector);

        if ($ids !== null) {
            dbQuery('DELETE FROM akce_organizatori WHERE id_akce = ' . $this->id());
            if ($ids) {
                $sqlValues = array_map(
                    fn(
                        $idOrganizatora,
                    ) => '(' . $this->id() . ',' . (int)$idOrganizatora . ')',
                    $ids,
                );
                dbQuery('INSERT INTO akce_organizatori(id_akce, id_uzivatele)
            VALUES ' . implode(',', $sqlValues));
            }

            return;
        }
        if (!isset($this->organizatori)) {
            $this->organizatori = Uzivatel::zIds(ids: $this->organizatoriRaw(), dataSourcesCollector: $dataSourcesCollector);
        }

        return $this->organizatori;
    }

    /**
     * @return array|int[]
     */
    public function dejOrganizatoriIds(): array
    {
        return array_map(
            'intval',
            dbOneArray('SELECT id_uzivatele FROM akce_organizatori WHERE id_akce = $1', [$this->id()]),
        );
    }

    /**
     * Vrátí pole uživatelů, kteří jsou organizátory jakékoli ze skupiny instancí
     * aktivity. Pokud nemá instance, vrátí organizátory aktivity jak jsou.
     */
    public function organizatoriSkupiny()
    {
        if ($this->a[Sql::PATRI_POD]) {
            return Uzivatel::zIds(dbOneCol('
        SELECT GROUP_CONCAT(ao.id_uzivatele)
        FROM akce_seznam a
        LEFT JOIN akce_organizatori ao USING (id_akce)
        WHERE a.patri_pod = ' . $this->a[Sql::PATRI_POD],
            ));
        }

        return $this->organizatori();
    }

    /**
     * @return string[]|\ArrayIterator Vrátí iterátor jmen organizátorů v lidsky čitelné podobě.
     */
    public function orgJmena()
    {
        $jmena = new \ArrayIteratorToString();
        foreach ($this->organizatori() as $o) {
            $jmena[] = $o->jmenoNick();
        }

        return $jmena;
    }

    /**
     * @return string[]|\ArrayIterator Vrátí iterátor loginů organizátorů
     */
    public function orgLoginy()
    {
        $loginy = new \ArrayIteratorToString();
        foreach ($this->organizatori() as $o) {
            $loginy[] = $o->login();
        }

        return $loginy;
    }

    /** Alias */
    public function otoc()
    {
        $this->refresh();
    }

    public function patriPodAktivitu(): ?Aktivita
    {
        $patriPodInstanciId = $this->patriPod();
        if (!$patriPodInstanciId) {
            return null;
        }
        $hlavniAktivitaId = dbOneCol(<<<SQL
SELECT akce_instance.id_hlavni_akce FROM akce_instance
WHERE akce_instance.id_instance = $1
SQL
            , [$patriPodInstanciId],
        );
        if (!$hlavniAktivitaId) {
            trigger_error("Aktivita {$this->id()} tvrdí, že je instancí aktivity {$hlavniAktivitaId}, ale v tabulce instancí taková není.", E_USER_WARNING);

            return null;
        }
        $hlavniAktivita = Aktivita::zId($hlavniAktivitaId);
        if (!$hlavniAktivita) {
            trigger_error("Aktivita {$this->id()} tvrdí, že je instancí aktivity {$hlavniAktivitaId}, ale taková aktivita neexistuje.", E_USER_WARNING);

            return null;
        }

        return $hlavniAktivita;
    }

    /** Skupina (id) aktivit. Spíše hack, raději refaktorovat */
    public function patriPod(): ?int
    {
        return $this->a[Sql::PATRI_POD]
            ? (int)$this->a[Sql::PATRI_POD]
            : null;
    }

    /**
     * Vrátí formátovaný (html) popisek aktivity
     */
    public function popis(
        string $popis = null,
        bool   $resetujKorekci = false,
    ) {
        if ($popis === null) {
            return dbMarkdown($this->a[Sql::POPIS]);
        }
        $oldId = $this->a[Sql::POPIS];
        $id    = dbTextHash($popis);
        $zmeny = [];
        // popis musíme změnit všem aktivitám, které "patří pod" jinou, i kdyby se text nezměnil, abychom ho případně rozkopírovali kde ještě není
        if ($this->a[Sql::PATRI_POD] || $id != $oldId) {
            $zmeny[Sql::POPIS] = $id;
        }
        if ($resetujKorekci && $id != $oldId) {
            $zmeny[Sql::PROBEHLA_KOREKCE] = 0;
        }

        if ($zmeny === []) {
            return $popis;
        }
        dbUpdate(
            'akce_seznam',
            $zmeny,
            $this->a[Sql::PATRI_POD]
                ? [Sql::PATRI_POD => $this->a[Sql::PATRI_POD]]
                : [Sql::ID_AKCE => $this->id()],
        );
        $this->a[Sql::POPIS] = $id;

        dbTextClean($oldId);

        return $popis;
    }

    public function getPopisRaw(): ?string
    {
        return dbOneCol(<<<SQL
SELECT text
FROM texty
WHERE id = $1
SQL
            , [$this->a[Sql::POPIS]],
        );
    }

    /**
     * Vrátí form(y) s vybírátky plus a mínus pro změny počtů míst teamových akt.
     * @todo parametry typu komplexnost výpisu a že nemůže měnit kdokoli aktivut
     * ale jen ten kdo je na ni přihlášený (vs. orgové v adminu)
     */
    protected function plusminus(
        Uzivatel $u = null,
                 $parametry = 0,
    ) {
        // kontroly
        if (!$this->a[Sql::TEAMOVA] || $this->a[Sql::STAV] != StavAktivity::AKTIVOVANA) return '';
        if ($parametry & self::PLUSMINUS && (!$u || !$this->prihlasen($u))) return '';
        // tisk formu
        $out = '';
        if ($this->a[Sql::TEAM_MAX] > $this->a[Sql::KAPACITA]) {
            $out .= ' <form method="post" style="display:inline"><input type="hidden" name="' . self::PN_PLUSMINUSP . '" value="' . $this->id() . '"><a href="#" onclick="this.parentNode.submit(); return false">▲</a></form>';
        }
        if ($this->a[Sql::TEAM_MIN] < $this->a[Sql::KAPACITA] && $this->pocetPrihlasenych() < $this->a[Sql::KAPACITA]) {
            $out .= ' <form method="post" style="display:inline"><input type="hidden" name="' . self::PN_PLUSMINUSM . '" value="' . $this->id() . '"><a href="#" onclick="this.parentNode.submit(); return false">▼</a></form>';
        }

        return $out;
    }

    /** Zpracuje formy na měnění počtu míst team. aktivit */
    protected static function plusminusZpracuj($reload = true)
    {
        if (post(self::PN_PLUSMINUSP)) {
            dbQueryS('UPDATE akce_seznam SET kapacita = kapacita + 1 WHERE id_akce = $1', [post(self::PN_PLUSMINUSP)]);
            if ($reload) back();

            return;
        }
        if (post(self::PN_PLUSMINUSM)) {
            dbQueryS('UPDATE akce_seznam SET kapacita = kapacita - 1 WHERE id_akce = $1', [post(self::PN_PLUSMINUSM)]);
            if ($reload) back();

            return;
        }
    }

    /**
     * @return int počet týmů přihlášených na tuto aktivitu
     */
    protected function pocetTeamu(): int
    {
        $id      = $this->id();
        $idRegex = '(^|,)' . $this->id() . '(,|$)'; // reg. výraz odpovídající id aktivity v seznamu odděleném čárkami

        return (int)dbOneCol('
      SELECT COUNT(id_akce)
      FROM (
        -- vybereme aktivity základního kola, z kterých se dá dostat do této aktivity (viz WHERE)
        SELECT a.id_akce
        FROM akce_seznam a
        -- připojíme k každé aktivitě přihlášené účastníky
        LEFT JOIN akce_prihlaseni prihlaseni_zaklad ON prihlaseni_zaklad.id_akce = a.id_akce
        -- připojíme k každému účastníkovi, jestli je přihlášen i na tuto semifinálovou aktivitu
        LEFT JOIN akce_prihlaseni prihlaseni_toto ON prihlaseni_toto.id_uzivatele = prihlaseni_zaklad.id_uzivatele AND prihlaseni_toto.id_akce = $0
        WHERE a.dite RLIKE $1
        GROUP BY a.id_akce
        -- vybereme jenom aktivity, z který je víc jak 0 přihlášeno i na toto semifinále
        HAVING COUNT(prihlaseni_toto.id_uzivatele) > 0
      ) poddotaz
    ', [$id, $idRegex]);
    }

    /**
     * Pošle mail potenciálním náhradníkům o volném místě na aktivitě.
     */
    private function poslatMailSledujicim(): void
    {
        $emaily = dbOneArray("
      SELECT u.email1_uzivatele
      FROM akce_prihlaseni_spec a
      JOIN uzivatele_hodnoty u ON u.id_uzivatele = a.id_uzivatele
      WHERE a.id_akce = $0 AND a.id_stavu_prihlaseni = $1
    ", [$this->id(), StavPrihlaseni::SLEDUJICI]);
        foreach ($emaily as $email) {
            $mail = GcMail::vytvorZGlobals();
            $mail->predmet('Gamecon: Volné místo na aktivitě ' . $this->nazev());
            $mail->text(hlaskaMail('uvolneneMisto', $this->nazev(), $this->denCasSkutecny()));
            $mail->adresat($email);
            $mail->odeslat();
        }
    }

    /**
     * Přihlásí uživatele na aktivitu
     *
     * @throws \Chyba
     */
    public function prihlas(
        Uzivatel $uzivatel,
        Uzivatel $prihlasujici,
                 $parametry = 0,
        bool     $jenPritomen = false,
        bool     $hlaskyVeTretiOsobe = false,
    ): bool {
        if ($this->prihlasen($uzivatel)) {
            return false;
        }

        $this->zkontrolujZdaSeMuzePrihlasit(
            $uzivatel,
            $prihlasujici,
            $parametry,
            $jenPritomen,
            $hlaskyVeTretiOsobe,
        ); // odhlášení náhradnictví v kolidujících aktivitách
        $this->odhlasZeSledovaniAktivitVeStejnemCase($uzivatel, $prihlasujici); // přihlášení na samu aktivitu (uložení věcí do DB)
        $idAktivity  = $this->id();
        $idUzivatele = $uzivatel->id();
        if ($this->a[Sql::TEAMOVA]
            && $this->pocetPrihlasenych() === 0
            && $this->prihlasovatelna() /* kvuli řetězovým teamovým aktivitám schválně bez ignore parametru */
        ) {
            $this->zamknoutProTeam($uzivatel);
        }
        dbQuery(
            'INSERT INTO akce_prihlaseni SET id_uzivatele=$0, id_akce=$1, id_stavu_prihlaseni=$2',
            [$idUzivatele, $idAktivity, StavPrihlaseni::PRIHLASEN],
        );
        $this->dejPrezenci()->zalogujPrihlaseni($uzivatel, $prihlasujici);
        // vrací se, storno rušíme a započítáme cenu za běžnou návštěvu aktivity
        $this->zrusPredchoziStornoPoplatek($uzivatel);

        $this->refresh();

        return true;
    }

    private function zrusPredchoziStornoPoplatek(Uzivatel $uzivatel)
    {
        dbQuery(
            'DELETE FROM akce_prihlaseni_spec WHERE id_uzivatele=$0 AND id_akce=$1 AND id_stavu_prihlaseni=$2',
            [$uzivatel->id(), $this->id(), StavPrihlaseni::POZDE_ZRUSIL],
        );
    }

    public function zkontrolujZdaSeMuzePrihlasit(
        Uzivatel $uzivatel,
        Uzivatel $prihlasujici,
        int      $parametry = 0,
        bool     $jenPritomen = false,
        bool     $hlaskyVeTretiOsobe = false,
    ): void {
        if ($jenPritomen) {
            if ($this->dorazilJakoCokoliv($uzivatel)) {
                return; // na současnou aktivitu už dorazil, takže se vlastně na ní může přihlásit
            }
        } elseif ($this->prihlasen($uzivatel)) {
            return;
        }
        if ($this->zacatek() && $this->konec() && !$uzivatel->maVolno($this->zacatek(), $this->konec(), null, $jenPritomen)) {
            throw new \Chyba(
                ($hlaskyVeTretiOsobe
                    ? 'Uživatel ' . $uzivatel->jmenoVolitelnyNick() . ' '
                    : ''
                ) .
                hlaska($hlaskyVeTretiOsobe
                    ? 'maKoliziAktivit'
                    : 'masKoliziAktivit'),
            );
        }
        if (!$uzivatel->gcPrihlasen()) {
            throw new \Chyba(
                ($hlaskyVeTretiOsobe
                    ? 'Uživatel ' . $uzivatel->jmenoVolitelnyNick() . ' '
                    : ''
                ) .
                hlaska($hlaskyVeTretiOsobe
                    ? 'neniPrihlasenNaGc'
                    : 'nejsiPrihlasenNaGc'),
            );
        }
        if (!(self::IGNOROVAT_LIMIT & $parametry) && $this->volno() !== 'u' && $this->volno() !== $uzivatel->pohlavi()) {
            throw new \Chyba(hlaska('plno'));
        }
        if (!(self::IGNOROVAT_PRIHLASENI_NA_SOUROZENCE & $parametry)) {
            foreach ($this->deti() as $dite) { // nemůže se přihlásit na aktivitu, pokud už je přihášen na jinou aktivitu se stejnými potomky
                foreach ($dite->rodice() as $rodic) {
                    if ($rodic->prihlasen($uzivatel)) {
                        throw new \Chyba(hlaska($hlaskyVeTretiOsobe
                            ? 'uzJePrihlasen'
                            : 'uzJsiPrihlasen'));
                    }
                }
            }
        }
        $teamKapacita = $this->tymovaKapacita();
        if ($teamKapacita !== null) {
            $jeNovyTym = false; // jestli se uživatel přihlašuje jako první z nového/dalšího týmu
            foreach ($this->rodice() as $rodic) {
                if ($rodic->prihlasen($uzivatel) && $rodic->pocetPrihlasenych() === 1) {
                    $jeNovyTym = true;
                    break;
                }
            }
            if ($jeNovyTym && $this->pocetTeamu() >= $teamKapacita) {
                throw new \Chyba('Na aktivitu ' . $this->nazev() . ': ' . $this->denCasSkutecny() . ' je už přihlášen maximální počet týmů');
            }
        }

        if ($this->jeBrigadnicka() && !$uzivatel->jeBrigadnik()) {
            throw new \Chyba(
                'Na tuto aktivitu se může přihlásit pouze brigádník. '
                . ($hlaskyVeTretiOsobe
                    ? ($uzivatel->jmenoVolitelnyNick() . ': ' . hlaska('neniBrigadnik'))
                    : hlaska('nejsiBrigadnik')),
            );
        }

        // potlačitelné kontroly
        if ($this->a[Sql::ZAMCEL] && !($parametry & self::ZAMEK)) {
            throw new \Chyba(hlaska('zamcena')); // zamčena pro tým, nikoli zamčena / uzavřena
        }
        if ($this->probehnuta() && $this->lzeJestePridavatUcastniky($prihlasujici)) {
            $parametry |= self::ZPETNE; // přestože je zamčená nebo dokonce uzavřená, stále ji ještě lze (po nějakou dobu) editovat
        }
        if ($prihlasujici->maPravoNaPrihlasovaniNaDosudNeotevrene()) {
            $parametry |= self::NEOTEVRENE;
            $parametry |= self::DOPREDNE;
        }

        if (!($prihlasovatelna = $this->prihlasovatelna($parametry))) {
            if ($parametry & self::STAV) {
                // hack na ignorování stavu
                $puvodniStav        = $this->a[Sql::STAV];
                $this->a[Sql::STAV] = StavAktivity::AKTIVOVANA; // nastavíme stav jako by bylo vše ok
                $prihlasovatelna    = $this->prihlasovatelna($parametry);
                $this->a[Sql::STAV] = $puvodniStav;
            }
            if (!$prihlasovatelna) {
                $duvod = '';
                if (self::UKAZAT_DETAILY_CHYBY & $parametry) {
                    $duvod = ': ' . $this->procNeniPrihlasovatelna($parametry);
                }
                throw new \Chyba('Aktivita není otevřena pro přihlašování' . $duvod);
            }
        }

        // přihlášení na navázané aktivity
        if ($this->a[Sql::DITE]) {
            $deti = $this->deti();
            if (count($deti) === 1) {
                try {
                    reset($deti)->prihlas($uzivatel, $prihlasujici, self::STAV | ($parametry & self::UKAZAT_DETAILY_CHYBY));
                } catch (\Chyba $chyba) {
                    throw new \Chyba('Nepodařilo se přihlásit na navazující aktivitu s chybou: ' . $chyba->getMessage());
                } catch (\Throwable $throwable) {
                    Vyjimkovac::vytvorZGlobals()->zaloguj($throwable);
                    throw new \Chyba('Nepodařilo se přihlásit na navazující aktivitu. Interní chyba systému.');
                }
            } elseif ($this->pocetPrihlasenych() > 0) { // (není teamleader)
                // vybrání jednoho uživatele, který už na navázané aktivity přihlášen je
                $vzor   = Uzivatel::zId(substr(explode(',', $this->prihlaseniRaw())[1], 0, -2));
                $uspech = false;
                foreach ($deti as $dite) {
                    // přihlášení na navázané aktivity podle vzoru vybraného uživatele
                    if ($dite->prihlasen($vzor)) {
                        $dite->prihlas($uzivatel, $prihlasujici, self::STAV | ($parametry & self::UKAZAT_DETAILY_CHYBY));
                        $uspech = true;
                        break;
                    }
                }
                if (!$uspech) {
                    throw new \Exception('Nepodařilo se určit výběr dalšího kola.');
                }
            }
        }
    }

    private function tymovaKapacita(): ?int
    {
        if (isset($this->a[Sql::TEAM_KAPACITA])) {
            return (int)$this->a[Sql::TEAM_KAPACITA];
        }

        return null;
    }

    public function zkontrolujZdaSeMuzeOdhlasit(
        Uzivatel $ucastnik,
        Uzivatel $odhlasujici,
    ) {
        if ($this->prihlasen($ucastnik)
            && $this->probehnuta()
            && !$this->ucastniciOdebratelni($odhlasujici)
        ) {
            throw new \Chyba('Aktivita už je uzavřena a nelze z ní odhlašovat.');
        }
    }

    /**
     * Není zamknout jako zamknout. Tohle pouze zamkne aktivitu pro účastníky mimo tým.
     * Pokud hledáš opravdové zamknutí, @param Uzivatel $zamykajici
     * @return void
     * @see zamkni
     *
     */
    public function zamknoutProTeam(Uzivatel $zamykajici)
    {
        dbUpdate(
            'akce_seznam',
            ['zamcel' => $zamykajici->id(), 'zamcel_cas' => dbNow()],
            [Sql::ID_AKCE => $this->id()],
        );
        $this->a[Sql::ZAMCEL] = (string)$zamykajici->id();
    }

    /** Jestli je uživatel  přihlášen na tuto aktivitu */
    public function prihlasen(Uzivatel $u): bool
    {
        return str_contains($this->prihlaseniRaw(), ',' . $u->id() . $u->pohlavi());
    }

    /** Jestli je uživatel organizátorem / vypravěčem této aktivity */
    public function organizuje(Uzivatel $u): bool
    {
        return str_contains(
            ',' . $this->organizatoriRaw() . ',',
            ',' . $u->id() . ',',
        );
    }

    /**
     * @return bool true, pokud je uživatel přihlášen jako sledující aktivity (ve watchlistu).
     */
    public function prihlasenJakoSledujici(Uzivatel $uzivatel)
    {
        return (bool)dbOneCol("
        SELECT 1
        FROM akce_prihlaseni_spec
        WHERE id_akce=$1 AND id_uzivatele=$2 AND id_stavu_prihlaseni = $3
      ", [$this->id(), $uzivatel->id(), StavPrihlaseni::SLEDUJICI]);
    }

    public function prihlasenOd(Uzivatel $uzivatel): ?\DateTimeImmutable
    {
        return $this->dejPrezenci()->prihlasenOd($uzivatel);
    }

    /**
     * Vrátí serializovaný seznam přihlášených a pokud takový neexistuje, načte
     * ho. Formát seznamu je: ,1204m0,864f2,742f1,...,1001m1, kde čísla jsou id
     * uživatelů, písmena pohlaví a čísla z pohlavím stav přihlášení.
     * @see ucastnici
     */
    private function prihlaseniRaw(?DataSourcesCollector $dataSourcesCollector = null): string
    {
        self::prihlaseniRawDSC($dataSourcesCollector);

        if (!array_key_exists('prihlaseni', $this->a)) {
            if ($this->prednacitat) {
                if (!array_key_exists($this->id(), self::$prihlaseniNaAktivityRawCache)) {
                    // array + array přidá nové záznamy s novými klíči, ale nepřepíše původní
                    self::$prihlaseniNaAktivityRawCache += self::nactiPrihlaseniNaAktivityRaw(
                        Sql::AKCE_SEZNAM_TABULKA . '.' . Sql::ROK . '=' . $this->rok(),
                    );
                }
                self::$prihlaseniNaAktivityRawCache[$this->id()] ??= '';
                $this->a['prihlaseni']                           = self::$prihlaseniNaAktivityRawCache[$this->id()];
            } else {
                $this->a['prihlaseni'] = $this->nactiPrihlaseniRaw();
            }
        }

        return (string)$this->a['prihlaseni'];
    }

    private static function prihlaseniRawDSC(?DataSourcesCollector $dataSourcesCollector): void
    {
        self::nactiPrihlaseniNaAktivityRawDSC($dataSourcesCollector);
        self::nactiPrihlaseniRawDSC($dataSourcesCollector);
    }

    private function organizatoriRaw(): string
    {
        return (string)$this->a['organizatori'];
    }

    private static function nactiPrihlaseniNaAktivityRaw(
        string                $where = 'TRUE',
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): array {
        self::nactiPrihlaseniNaAktivityRawDSC($dataSourcesCollector);

        return dbFetchPairs(<<<SQL
            SELECT akce_seznam.id_akce,
                   CONCAT(
                        ',', -- musí začínat na čárku, aby se dal odlišit začátek ID
                        GROUP_CONCAT(
                            akce_prihlaseni.id_uzivatele,
                            uzivatele_hodnoty.pohlavi,
                            akce_prihlaseni.id_stavu_prihlaseni ORDER BY (
                                SELECT MAX(kdy)
                                FROM akce_prihlaseni_log
                                WHERE akce_prihlaseni_log.id_akce = akce_prihlaseni.id_akce
                                  AND akce_prihlaseni_log.id_uzivatele = akce_prihlaseni.id_uzivatele
                                GROUP BY akce_prihlaseni_log.id_uzivatele, akce_prihlaseni_log.id_akce
                            ) ASC -- od nejdříve přihlášeného kvůli řazení učastníků v přehledech
                        ),
                        ',' -- musí končit na čárku, aby se dal odlišit konec ID
                    ) AS prihlaseni
            FROM akce_seznam
            LEFT JOIN akce_prihlaseni ON akce_seznam.id_akce = akce_prihlaseni.id_akce
            LEFT JOIN uzivatele_hodnoty ON uzivatele_hodnoty.id_uzivatele = akce_prihlaseni.id_uzivatele
            WHERE {$where}
            GROUP BY akce_seznam.id_akce
            SQL,
        );
    }

    private static function nactiPrihlaseniNaAktivityRawDSC(
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): void {
        $dataSourcesCollector?->addDataSources([
            Sql::AKCE_SEZNAM_TABULKA,
            AkcePrihlaseniSqlStruktura::AKCE_PRIHLASENI_TABULKA,
            UzivateleHodnotySqlStruktura::UZIVATELE_HODNOTY_TABULKA,
            AkcePrihlaseniLogSqlStruktura::AKCE_PRIHLASENI_LOG_TABULKA,
        ]);
    }

    private function nactiPrihlaseniRaw(?DataSourcesCollector $dataSourcesCollector = null): string
    {
        return (string)(
            self::nactiPrihlaseniNaAktivityRaw(
                Sql::AKCE_SEZNAM_TABULKA . '.' . Sql::ID_AKCE . "={$this->id()}",
                $dataSourcesCollector,
            )[$this->id()]
            ?? ''
        );
    }

    private static function nactiPrihlaseniRawDSC(?DataSourcesCollector $dataSourcesCollector): void
    {
        self::nactiPrihlaseniNaAktivityRawDSC($dataSourcesCollector);
    }

    /** Počet přihlášených */
    public function pocetPrihlasenych(): int
    {
        if ($p = $this->prihlaseniRaw()) {
            return substr_count($p, ',') - 1;
        }

        return 0;
    }

    protected function pocetPrihlasenychMuzu(?DataSourcesCollector $dataSourcesCollector = null): int
    {
        return substr_count($this->prihlaseniRaw($dataSourcesCollector), 'm');
    }

    protected function pocetPrihlasenychZen(?DataSourcesCollector $dataSourcesCollector = null): int
    {
        return substr_count($this->prihlaseniRaw($dataSourcesCollector), 'f');
    }

    /**
     * @see \Gamecon\Aktivita\StavPrihlaseni
     * Vrátí stav přihlášení uživatele na aktivitu. Pokud není přihlášen, vrací
     * hodnotu StavPrihlaseni::NEPRIHLASEN.
     */
    public function stavPrihlaseni(
        Uzivatel              $u,
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): int {
        $prihlaseni = $this->prihlaseniRaw($dataSourcesCollector);
        $usymbol    = ',' . $u->id() . $u->pohlavi();
        $pos        = strpos($prihlaseni, $usymbol);
        if ($pos !== false) {
            return (int)substr($prihlaseni, $pos + strlen($usymbol), 1);
        }

        foreach ($this->seznamUcastniku($dataSourcesCollector) as $idStavuPrihlaseni => $ucastniciAktivity) {
            foreach ($ucastniciAktivity as $ucastnikAktivity) {
                if ($ucastnikAktivity->id() === $u->id()) {
                    return $idStavuPrihlaseni;
                }
            }
        }

        return StavPrihlaseni::NEPRIHLASEN;
    }

    /**
     * @return int[]
     */
    public function dejStavyVsechPrihlasenych(): array
    {
        $prihlaseni = trim($this->prihlaseniRaw(), ',');
        preg_match('~\d+[mw](?<stav>\d+)(,$)~', $prihlaseni, $matches);

        return array_map('intval', $matches[Sql::STAV] ?? []);
    }

    public function dorazilJakoCokoliv(Uzivatel $ucastnik): bool
    {
        $stavPrihlaseni = $this->stavPrihlaseni($ucastnik);

        return StavPrihlaseni::dorazilJakoCokoliv($stavPrihlaseni);
    }

    public function dorazilJakoNahradnik(Uzivatel $ucastnik): bool
    {
        return $this->stavPrihlaseni($ucastnik) === StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK;
    }

    public function dorazilJakoPredemPrihlaseny(Uzivatel $ucastnik): bool
    {
        return $this->stavPrihlaseni($ucastnik) === StavPrihlaseni::PRIHLASEN_A_DORAZIL;
    }

    public function nedorazilNeboZrusil(Uzivatel $ucastnik): bool
    {
        $stavPrihlaseni = $this->stavPrihlaseni($ucastnik);

        return StavPrihlaseni::nedorazilNeboZrusil($stavPrihlaseni);
    }

    public function platiStorno(Uzivatel $uzivatel): bool
    {
        return StavPrihlaseni::platiStorno($this->stavPrihlaseni($uzivatel));
    }

    /** Zdali chceme, aby se na aktivitu bylo možné běžně přihlašovat */
    public function prihlasovatelna(
        int $parametry = 0,
    ) {
        return $this->procNeniPrihlasovatelna($parametry) === '';
    }

    private function procNeniPrihlasovatelna(
        int $parametry,
    ): string {
        $dopredne   = $parametry & self::DOPREDNE;
        $zpetne     = $parametry & self::ZPETNE;
        $neotevrene = $parametry & self::NEOTEVRENE;
        $interni    = $parametry & self::INTERNI;

        if (!( // ← inverze ↓
            $this->systemoveNastaveni->probihaRegistraceAktivit()
            || ($dopredne && pred($this->systemoveNastaveni->prvniVlnaKdy()))
            || ($zpetne && po($this->systemoveNastaveni->prihlasovaniUcastnikuDo()))
        )) {
            return sprintf(
                'Není spuštěna registrace aktivit (začíná %s a končí %s)',
                $this->systemoveNastaveni->prvniVlnaKdy()->formatCasStandard(),
                $this->systemoveNastaveni->prihlasovaniUcastnikuDo()->formatCasStandard(),
            );
        }
        if (!( // ← inverze ↓
            $this->idStavu() === StavAktivity::AKTIVOVANA
            || ($neotevrene && in_array($this->idStavu(), [StavAktivity::PRIPRAVENA, StavAktivity::PUBLIKOVANA]))
            || ($interni && $this->idStavu() == StavAktivity::NOVA && $this->typ()->jeInterni())
            || ($zpetne && $this->probehnuta())
        )) {
            return sprintf(
                'Aktivita není ve stavu použitelném pro přihlašování. Je ve stavu "%s" (%d). Povoleno: technické %s, zpětně %s',
                StavAktivity::dejNazev((int)$this->a[Sql::STAV]),
                $this->a[Sql::STAV],
                $interni
                    ? 'ANO'
                    : 'NE',
                $zpetne
                    ? 'ANO'
                    : 'NE',
            );
        }
        if (!$this->a[Sql::ZACATEK]) {
            return 'Aktivitě chybí čas začátku';
        }
        if (!$this->a[Sql::TYP]) {
            return 'Aktivitě chybí typ';
        }

        return '';
    }

    /**
     * @return bool jestli je na aktivitu povoleno přihlašování náhradníků
     */
    public function prihlasovatelnaProSledujici(): bool
    {
        return !$this->tymova() && !$this->a[Sql::DITE];
    }

    /**
     * Vrátí html kód pro přihlášení / odhlášení / informaci o zaplněnosti pro
     * daného uživatele. Pokud není zadán, vrací prázdný řetězec.
     * @todo v rodině instancí maximálně jedno přihlášení?
     * @todo konstanty pro jména POST proměnných? viz prihlasovatkoZpracuj
     */
    public function prihlasovatko(
        Uzivatel $u = null,
                 $parametry = 0,
    ) {
        $out = '';
        if (!$u) {
            $out = $this->formatujDuvodProTesting('Nejsi přihlášený/ná');
        } elseif (!$u->gcPrihlasen()) {
            $out = $this->formatujDuvodProTesting('Nejsi přihlášený/ná na letoční GC');
        } elseif (!$this->prihlasovatelna($parametry)) {
            $out = $this->formatujDuvodProTesting($this->procNeniPrihlasovatelna($parametry));
        } elseif ($this->jeBrigadnicka() && !$u->jeBrigadnik()) {
            $out = $this->formatujDuvodProTesting('Aktivita je brigádnická, ale ty nejsi brigádník');
        } else {
            if (($stav = $this->stavPrihlaseni($u)) > -1 && $stav != StavPrihlaseni::SLEDUJICI) {
                if ($stav == StavPrihlaseni::PRIHLASEN || $parametry & self::ZPETNE) {
                    $out .=
                        '<form method="post" style="display:inline">' .
                        '<input type="hidden" name="odhlasit" value="' . $this->id() . '">' .
                        '<a href="#" onclick="this.parentNode.submit(); return false">odhlásit</a>' .
                        '</form>';
                }
                if ($stav == StavPrihlaseni::PRIHLASEN_A_DORAZIL) {
                    $out .= '<em>účast</em>';
                }
                if ($stav == StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK) {
                    $out .= '<em>jako náhradník</em>';
                }
                if ($stav == StavPrihlaseni::PRIHLASEN_ALE_NEDORAZIL) {
                    $out .= '<em>neúčast</em>';
                }
                if ($stav == StavPrihlaseni::POZDE_ZRUSIL) {
                    $out .= '<em>pozdní odhlášení</em>';
                }
            } elseif ($u->organizuje($this)) {
                $out = $this->formatujDuvodProTesting('Tuto aktivitu organizuješ');
            } elseif ($this->a[Sql::ZAMCEL]) {
                $hajeniTymuHodin = self::HAJENI_TEAMU_HODIN;
                $out             = <<<HTML
<span class="hinted">&#128274;<!--🔒 zámek --><span class="hint">Kapitán týmu má celkem {$hajeniTymuHodin} hodin na vyplnění svého týmu</span></span>
HTML
                                   . $this->formatujDuvodProTesting('Aktivita už je zamknutá');
            } else {
                $volno = $this->volno();
                if ($volno === 'u' || $volno == $u->pohlavi()) {
                    $out =
                        '<form method="post" style="display:inline">' .
                        '<input type="hidden" name="prihlasit" value="' . $this->id() . '">' .
                        '<a href="#" onclick="this.parentNode.submit(); return false">přihlásit</a>' .
                        '</form>';
                } elseif ($volno === 'f') {
                    $out = 'pouze ženská místa';
                } elseif ($volno === 'm') {
                    $out = 'pouze mužská místa';
                } elseif ($this->prihlasovatelnaProSledujici()) {
                    if ($u->prihlasenJakoSledujici($this)) {
                        $out =
                            '<form method="post" style="display:inline">' .
                            '<input type="hidden" name="odhlasSledujiciho" value="' . $this->id() . '">' .
                            '<a href="#" onclick="this.parentNode.submit(); return false">zrušit sledování</a>' .
                            '</form>';
                    } else {
                        $out =
                            '<form method="post" style="display:inline">' .
                            '<input type="hidden" name="prihlasSledujiciho" value="' . $this->id() . '">' .
                            '<a href="#" onclick="this.parentNode.submit(); return false">sledovat</a>' .
                            '</form>';
                    }
                }
            }
        }
        if ($parametry & self::PLUSMINUS_KAZDY) {
            $out .= '&emsp;' . $this->plusminus($u);
        }

        return $out;
    }

    public function formatujDuvodProTesting(string $duvod): string
    {
        return (defined('TESTING') && TESTING)
               || $this->systemoveNastaveni->jsmeNaLocale()
               || $this->systemoveNastaveni->jsmeNaBete()
            ? '<span class="hinted">🙋<span class="hint"><em>(toto se ukazuje pouze na testu)</em><br>' . $duvod . ' </span></span>'
            : '';
    }

    /** Zpracuje post data z přihlašovátka. Pokud došlo ke změně, vyvolá reload pokud není vypnutý */
    public static function prihlasovatkoZpracuj(
        ?Uzivatel $u,
        ?Uzivatel $prihlasujici,
                  $parametry = 0,
    ) {
        if (static::prihlasovatkoZpracujBezBack($u, $prihlasujici, $parametry)) {
            back();
        }
    }

    /** Zpracuje post data z přihlašovátka. Vrátí jestli došlo ke změně */
    public static function prihlasovatkoZpracujBezBack(
        ?Uzivatel $u,
        ?Uzivatel $prihlasujici,
                  $parametry = 0,
    ) {
        if ($u) {
            $prihlasujici = $prihlasujici ?? $u;
            if (post('prihlasit')) {
                $aktivita = self::zId(post('prihlasit'));
                if ($aktivita) {
                    $aktivita->prihlas($u, $prihlasujici, $parametry);
                }

                return true;
            }
            if (post('odhlasit')) {
                $bezPokut = ($parametry & self::ZPETNE)
                    ? self::BEZ_POKUT
                    // v případě zpětných změn bez pokut
                    : 0;
                $aktivita = self::zId(post('odhlasit'));
                if ($aktivita) {
                    $aktivita->odhlas(
                        $u,
                        $prihlasujici,
                        $u?->id() === $prihlasujici?->id()
                            ? 'rucne-vlastni-odhlaseni'
                            : 'rucni-odhlaseni-adminem',
                        $bezPokut,
                    );
                }

                return true;
            }
            if (post('prihlasSledujiciho')) {
                $aktivita = self::zId(post('prihlasSledujiciho'));
                if ($aktivita) {
                    $aktivita->prihlasSledujiciho($u, $prihlasujici);
                }

                return true;
            }
            if (post('odhlasSledujiciho')) {
                $aktivita = self::zId(post('odhlasSledujiciho'));
                if ($aktivita) {
                    $aktivita->odhlasSledujiciho($u, $prihlasujici);
                }

                return true;
            }
        }
        if ($parametry & self::PLUSMINUS_KAZDY) {
            return self::plusminusZpracuj();
        }

        return false;
    }

    /**
     * Přihlásí uživatele jako sledujícího (watchlist)
     */
    public function prihlasSledujiciho(
        Uzivatel  $u,
        ?Uzivatel $prihlasujici,
    ) {
        // Aktivita musí mít přihlašování náhradníků povoleno
        if (!$this->prihlasovatelnaProSledujici()) {
            throw new \Chyba('Na aktivitu se nelze přihlašovat jako sledující.');
        }
        // Uživatel nesmí být přihlášen na aktivitu nebo jako náhradník
        if ($this->prihlasen($u) || $this->prihlasenJakoSledujici($u)) {
            return;
        }
        // Uživatel nesmí mít ve stejný slot jinou přihlášenou aktivitu
        if (!$u->maVolno($this->zacatek(), $this->konec())) {
            throw new ChybaKolizeAktivit();
        }
        // Uživatel musí být přihlášen na GameCon
        if (!$u->gcPrihlasen()) {
            throw new \Chyba(hlaska('nejsiPrihlasenNaGc'));
        }

        // Uložení přihlášení do DB
        dbQuery("INSERT INTO akce_prihlaseni_spec SET id_uzivatele=$0, id_akce=$1, id_stavu_prihlaseni=$2", [$u->id(), $this->id(), StavPrihlaseni::SLEDUJICI]);
        $this->dejPrezenci()->zalogujZeSePrihlasilJakoSledujici($u, $prihlasujici);
        $this->refresh();
    }

    /**
     * Přihlásí na aktivitu vybrané uživatele jako tým vč. přihlášení na vybraná
     * navazující kola a úpravy počtu míst v týmu.
     * @param Uzivatel[] $uzivatele
     * @param string $nazevTymu
     * @param int $pocetMist požadovaný počet míst v týmu
     * @param self[] $dalsiKola - pořadí musí odpovídat návaznosti kol
     * @param int $parametry
     */
    public function prihlasTym(
        array    $uzivatele,
        Uzivatel $prihlasujici,
        ?string  $nazevTymu = null,
        ?int     $pocetMist = null,
        ?array   $dalsiKola = [],
                 $parametry = 0,
    ) {
        if (!$this->tymova()) {
            throw new \Exception('Nelze přihlásit tým na netýmovou aktivitu.');
        }
        if (!$this->a[Sql::ZAMCEL]) {
            throw new \Exception('Pro přihlášení týmu musí být aktivita zamčená.');
        }
        if (!$this->jsouDalsiKola($dalsiKola)) {
            throw new \Exception('Nepovolený výběr dalších kol.');
        }

        $lidr       = Uzivatel::zId($this->a[Sql::ZAMCEL]);
        $chybnyClen = null; // nastavíme v případě, že u daného člena týmu nastala při přihlášení chyba

        dbBegin();
        try {
            // přihlášení týmlídra na zvolená další kola (pokud jsou)
            // nutno jít od konce, jinak vazby na potomky můžou vyvolat chyby kvůli
            // duplicitním pokusům o přihlášení
            foreach (array_reverse($dalsiKola) as $kolo) {
                $kolo->prihlas($lidr, $prihlasujici, self::STAV | $parametry);
            }

            // přihlášení členů týmu
            foreach ($uzivatele as $clen) {
                try {
                    $this->prihlas($clen, $prihlasujici, self::ZAMEK | ($parametry & self::UKAZAT_DETAILY_CHYBY));
                } catch (\Exception $e) {
                    $chybnyClen = $clen;
                    throw $e;
                }
            }

            // doplňující úpravy aktivity
            dbUpdate('akce_seznam', [
                Sql::ZAMCEL     => null,
                Sql::ZAMCEL_CAS => null,
                Sql::TEAM_NAZEV => $nazevTymu
                    ?: null,
                Sql::KAPACITA   => $pocetMist
                    ?: dbNoChange(),
            ], [
                Sql::ID_AKCE => $this->id(),
            ]);

            $this->refresh();
        } catch (\Exception $e) {
            dbRollback();
            if ($chybnyClen) {
                throw new \Chyba(hlaska('chybaClenaTymu', $chybnyClen->jmenoNick(), $chybnyClen->id(), $e->getMessage()));
            }
            throw $e;
        }
        dbCommit(); // maily přihlášeným
        $mail = GcMail::vytvorZGlobals(
            hlaskaMail(
                'prihlaseniTeamMail',
                $lidr,
                $lidr->jmenoNick(),
                $this->nazev(),
                $this->denCasSkutecny(),
            ),
        );
        $mail->predmet('Přihláška na ' . $this->nazev());
        foreach ($uzivatele as $clen) {
            $mail->adresat($clen->mail());
            $mail->odeslat();
        }
    }

    public function publikuj()
    {
        $this->zmenStav(StavAktivity::PUBLIKOVANA);
    }

    private function zmenStav(int $novyStav)
    {
        if (!StavAktivity::jeZnamy($novyStav)) {
            throw new \LogicException("Neznámý stav aktivity '$novyStav'");
        }
        dbQuery('UPDATE akce_seznam SET stav=$1 WHERE id_akce=$2', [$novyStav, $this->id()]);
    }

    /** Nastaví aktivitu jako "připravena pro aktivaci" */
    public function priprav()
    {
        $this->zmenStav(StavAktivity::PRIPRAVENA);
    }

    public function probehnuta(): bool
    {
        return in_array($this->a[Sql::STAV], StavAktivity::probehnuteStavy());
    }

    public function bezpecneEditovatelna(): bool
    {
        return in_array($this->idStavu(), [StavAktivity::NOVA, StavAktivity::PUBLIKOVANA, StavAktivity::PRIPRAVENA], true);
    }

    public function stav(): StavAktivity
    {
        if (is_numeric($this->stav)) {
            $this->prednactiN1([
                'atribut' => 'stav',
                'cil'     => StavAktivity::class,
            ]);
        }

        return $this->stav;
    }

    public function idStavu(): ?int
    {
        $idStavu = $this->a[ActivitiesImportSqlColumn::STAV];

        return $idStavu !== null
            ? (int)$idStavu
            : null;
    }

    /**
     * Vrací surový databázový řádek, nepoužívat (pouze pro debug a zpětnou kompatibilitu, postupně odstranit).
     */
    public function rawDb(): array
    {
        return $this->a;
    }

    /** Aktualizuje stav aktivity podle databáze */
    public function refresh(): void
    {
        $this->a               = self::zId($this->id())->a;
        $this->prezence        = null;
        $this->seznamUcastniku = null;
    }

    /**
     * Vrátí aktivity, u kterých je tato aktivita jako jedno z dětí
     *
     * @return array<Aktivita>
     */
    public function rodice(): array
    {
        return self::zWhere(
            systemoveNastaveni: $this->systemoveNastaveni,
            dalsiPouziteSqlTabulky: [],
            where1: 'WHERE a.dite rlike "(^|,)' . $this->id() . '(,|$)"',
        );
    }

    /**
     * Smaže aktivitu z DB
     */
    public function smaz(Uzivatel $mazajici)
    {
        dbBegin();
        try {
            foreach ($this->prihlaseni() as $u) {
                $this->odhlas(
                    $u,
                    $mazajici,
                    'smazani-aktivity',
                    self::BEZ_POKUT | self::NEPOSILAT_MAILY_SLEDUJICIM,
                );
            }
            $idInstance             = $this->patriPod();
            $idNoveMaterskeAktivity = null;
            if ($idInstance) {
                $materskaProInstanciId = dbOneCol('SELECT id_instance FROM akce_instance WHERE id_hlavni_akce = $1', [$this->id()]);
                if ($materskaProInstanciId && (int)$materskaProInstanciId !== (int)$idInstance) {
                    throw new \RuntimeException(sprintf('Aktivita s ID %d tvrdí, že patří pod instanci %d, ale patří pod %d', $this->id(), $idInstance, $materskaProInstanciId));
                }
                $zbyde = (int)dbOneCol(
                    'SELECT COUNT(*) AS zbyde FROM akce_seznam WHERE id_akce != $1 AND patri_pod = $2',
                    [$this->id(), $idInstance],
                );
                if ($materskaProInstanciId) {
                    $idNoveMaterskeAktivity = dbOneCol(
                        'SELECT MIN(id_akce) AS idNoveMaterskeAktivity FROM akce_seznam WHERE id_akce != $1 AND patri_pod = $2',
                        [$this->id(), $materskaProInstanciId],
                    );
                }
                // nezbyde žádná, nebo jen jediná instance, zrušit instanci
                if ($zbyde <= 1) {
                    dbQuery('UPDATE akce_seznam SET patri_pod = NULL WHERE patri_pod = ' . $idInstance);
                    dbQuery('DELETE FROM akce_instance WHERE id_instance = ' . $idInstance);
                } elseif ($idNoveMaterskeAktivity) {
                    dbQuery('UPDATE akce_instance SET id_hlavni_akce = $1 WHERE id_instance = $2', [$idNoveMaterskeAktivity, $idInstance]);
                }
            }

            dbQuery('DELETE FROM akce_prihlaseni_spec WHERE id_akce = $1 AND id_stavu_prihlaseni = $2', [$this->id(), StavPrihlaseni::SLEDUJICI]);
            dbQuery('DELETE FROM akce_organizatori WHERE id_akce = $1', [$this->id()]);
            dbQuery('DELETE FROM akce_seznam WHERE id_akce = $1', [$this->id()]); // posledni kvuli SQL cizim klicum a cascade

            if ($idNoveMaterskeAktivity) {
                // zrušená aktivita byla mateřskou => je potřeba uložit url a popisek do nové instance (až po smazání původní mateřské aktivity kvůli unikátnímu klíči)
                dbQuery(
                    'UPDATE akce_seznam SET url_akce = $1, popis = $2, vybaveni = $3 WHERE id_akce = $4',
                    [$this->a[Sql::URL_AKCE], $this->a[Sql::POPIS], $this->a[Sql::VYBAVENI], $idNoveMaterskeAktivity],
                );
            }

            dbCommit();
        } catch (\Exception $e) {
            dbRollback();
            throw $e;
        }

        // invalidace aktuální instance
        $this->a = null;
    }

    /**
     * @return string[]
     */
    public function tagy(): array
    {
        if ($this->a['tagy']) {
            return explode(',', $this->a['tagy']);
        }

        return [];
    }

    /**
     * @return int[]
     */
    public function tagyId(): array
    {
        return $this->parseIds($this->a['ids_tagu'] ?? '');
    }

    public function nastavTagy(array $tagy)
    {
        // nastavit tagy aktivitám
        $idInstanci    = $this->idInstanci();
        $idInstanciSql = dbQa($idInstanci);
        dbQuery("DELETE FROM akce_sjednocene_tagy WHERE id_akce IN ($idInstanciSql)");
        if ($tagy) {
            $tagySql = dbQa($tagy);
            dbQuery(<<<SQL
INSERT INTO akce_sjednocene_tagy(id_akce, id_tagu)
SELECT akce_seznam.id_akce, sjednocene_tagy.id
FROM akce_seznam
JOIN sjednocene_tagy ON sjednocene_tagy.nazev IN ($tagySql)
AND akce_seznam.id_akce IN ($idInstanciSql)
SQL,
            );
        }

        $this->otoc();
    }

    public function nastavTagyPodleId(array $idTagu)
    {
        // nastavit tagy aktivitám
        foreach ($this->instance() as $aktivita) {
            dbQuery('DELETE FROM akce_sjednocene_tagy WHERE id_akce = $1', [$aktivita->id()]);
            if ($idTagu) {
                dbQuery(
                    'INSERT INTO akce_sjednocene_tagy(id_akce, id_tagu) SELECT $1, id FROM sjednocene_tagy WHERE id IN (' . dbQa($idTagu) . ')',
                    [$aktivita->id()],
                );
            }
        }

        $this->otoc();
    }

    public function tym()
    {
        if ($this->tymova() && $this->pocetPrihlasenych() > 0 && !$this->a[Sql::ZAMCEL]) {
            return new \Tym($this, $this->a);
        }

        return null;
    }

    public function tymMaxKapacita(): ?int
    {
        return (string)$this->a[Sql::TEAM_MAX] !== ''
            ? (int)$this->a[Sql::TEAM_MAX]
            : null;
    }

    public function tymMinKapacita(): ?int
    {
        return (string)$this->a[Sql::TEAM_MIN] !== ''
            ? (int)$this->a[Sql::TEAM_MIN]
            : null;
    }

    /**
     * Je aktivita týmová?
     */
    public function tymova(): bool
    {
        return (bool)$this->a[Sql::TEAMOVA];
    }

    /**
     * @return DateTimeCz|null jestli a do kdy je týmová aktivita zamčená
     */
    public function tymZamcenyDo(): ?\DateTimeInterface
    {
        if ($this->a[Sql::ZAMCEL_CAS]) {
            $dateTime = new DateTimeCz($this->a[Sql::ZAMCEL_CAS]);
            $dateTime->add(new \DateInterval('PT' . self::HAJENI_TEAMU_HODIN . 'H'));

            return $dateTime;
        }

        return null;
    }

    /**
     * @return DateTimeCz|null jestli je týmová aktivita zamčená tímto uživatelem
     */
    public function zamcenoUzivatelem(\Uzivatel $u = null): bool
    {
        return !!$u && $this->a[Sql::ZAMCEL] == $u->id();
    }

    public function typ(): TypAktivity
    {
        if (!$this->typ && !is_numeric($this->typ)) {
            trigger_error("Aktivita '{$this->id()}' nemá typ", E_USER_WARNING);

            $this->typ = TypAktivity::zId(TypAktivity::SYSTEMOVA);
        }
        if (is_numeric($this->typ)) {
            $typId = $this->typ;
            $this->prednactiN1([
                'atribut' => Sql::TYP,
                'cil'     => TypAktivity::class,
            ]);
            if ($this->typ === null) {
                throw new \RuntimeException("Aktivita '{$this->id()}' má typ ID '{$typId}', který ale v DB není.");
            }
        }

        return $this->typ;
    }

    public function typId(): int
    {
        // todo: tohle je trochu naruby. Víme typId bez vytvoření dotazu do db, takže bychom neměli potřebovat celý typ abychom zjistili jeho id které dávno známe
        return $this->typ()->id();
    }

    public function jeBrigadnicka(): bool
    {
        return $this->typ()->jeBrigadnicka();
    }

    /**
     * Vrátí pole s přihlášenými účastníky, včetně náhradníků
     * @return Uzivatel[]
     */
    public function prihlaseni(): array
    {
        $prihlaseniZakodovano = array_filter(explode(',', $this->prihlaseniRaw()));
        $ids                  = array_map(static function (
            string $prihlasenyZakodovano,
        ) {
            // například '588m0'
            preg_match(
                '~(?<idUzivatele>\d+)(?<pohlavi>[mf])(?<idStavuPrihlaseni>\d+)~',
                $prihlasenyZakodovano,
                $matches,
            );

            return (int)$matches['idUzivatele'];
        }, $prihlaseniZakodovano);
        $uzivatele            = Uzivatel::zIds($ids);
        usort($uzivatele, static function (
            Uzivatel $nejakyUzivatel,
            Uzivatel $jinyUzivatel,
        ) use
        (
            $ids,
        ) {
            /**
             * Chceme zachovat původní pořadí přihlášených. ids byly seřazeny, ale @see Uzivatel::zIds
             * nám to rozhodilo.
             */
            return array_search($nejakyUzivatel->id(), $ids, false) <=> array_search($jinyUzivatel->id(), $ids, false);
        });

        return $uzivatele;
    }

    /**
     * @return array<int>
     */
    private function parseIds(string $imploded): array
    {
        return array_map(
            'intval',
            array_filter(
                array_map('trim', explode(',', $imploded)),
            ),
        );
    }

    /**
     * @return Uzivatel[]
     */
    public function dorazili(): array
    {
        return array_filter($this->prihlaseni(), function (
            Uzivatel $prihlaseny,
        ) {
            return $this->dorazilJakoCokoliv($prihlaseny);
        });
    }

    /**
     * @return string absolutní url k anotaci aktivity na webu
     */
    public function url(): string
    {
        static $typy;
        if (!$typy) {
            $o = dbQuery('SELECT id_typu, url_typu_mn FROM akce_typy');
            while ($r = mysqli_fetch_row($o)) {
                $typy[$r[0]] = $r[1];
            }
        }

        return URL_WEBU . '/' . $typy[$this->a[Sql::TYP]] . '#' . $this->a[Sql::URL_AKCE];
    }

    /**
     * @return string část url identifikující aktivitu (unikátní v dané linii)
     */
    public function urlId()
    {
        return $this->a[Sql::URL_AKCE];
    }

    /** Vrátí, jestli aktivita bude aktivována v budoucnu, později než v další vlně */
    public function vBudoucnu(): bool
    {
        return $this->a[Sql::STAV] == StavAktivity::PUBLIKOVANA;
    }

    /** Vrátí, jestli aktivita bude aktivována v další vlně */
    public function vDalsiVlne(?DataSourcesCollector $dataSourcesCollector = null): bool
    {
        $dataSourcesCollector?->addDataSource(SystemoveNastaveniSqlStruktura::SYSTEMOVE_NASTAVENI_TABULKA);

        return $this->a[Sql::STAV] == StavAktivity::PRIPRAVENA
               || (!$this->systemoveNastaveni->probihaRegistraceAktivit()
                   && $this->a[Sql::STAV] == StavAktivity::AKTIVOVANA
               );
    }

    /** Vrátí typ volných míst na aktivitě */
    public function volno(?DataSourcesCollector $dataSourcesCollector = null)
    {
        $prihlasenoMuzu = $this->pocetPrihlasenychMuzu($dataSourcesCollector);
        $prihlasenoZen  = $this->pocetPrihlasenychZen($dataSourcesCollector);
        $unisexKapacita = $this->a[Sql::KAPACITA];
        $kapacitaMuzu   = $this->a[Sql::KAPACITA_M];
        $kapacitaZen    = $this->a[Sql::KAPACITA_F];
        if (($unisexKapacita + $kapacitaMuzu + $kapacitaZen) <= 0) {
            return 'u'; //aktivita bez omezení
        }
        if ($prihlasenoMuzu + $prihlasenoZen >= $unisexKapacita + $kapacitaMuzu + $kapacitaZen) {
            return 'x'; //beznadějně plno
        }
        if ($prihlasenoMuzu >= $unisexKapacita + $kapacitaMuzu) {
            return 'f'; //muži zabrali všechna univerzální i mužská místa
        }
        if ($prihlasenoZen >= $unisexKapacita + $kapacitaZen) {
            return 'm'; //LIKE WTF? (opak předchozího)
        }

        //else
        return 'u'; //je volno a žádné pohlaví nevyžralo limit míst
    }

    public function getKapacitaUnisex(): int
    {
        return (int)$this->a[Sql::KAPACITA];
    }

    public function getKapacitaMuzu(): int
    {
        return (int)$this->a[Sql::KAPACITA_M];
    }

    public function getKapacitaZen(): int
    {
        return (int)$this->a[Sql::KAPACITA_F];
    }

    /** Jestli volno pro daného uživatele (nebo aspoň pro někoho, pokud null) */
    public function volnoPro(Uzivatel $u = null)
    {
        $v = $this->volno();
        if ($u) {
            return $v === 'u' || $v == $u->pohlavi();
        }

        return $v !== 'x';
    }

    /**
     * Jestli má uživatel aktivitu vidět (případně jestli má být vidět veřejně,
     * pokud $u == null).
     */
    public function viditelnaPro(
        ?Uzivatel $uzivatel,
    ) {
        return (
            (in_array($this->a[Sql::STAV], StavAktivity::bezneViditelneStavy(), false) // podle stavu je aktivita viditelná
             && !(TypAktivity::jeInterniDleId($this->a[Sql::TYP]) && $this->probehnuta()) // ale skrýt technické a brigádnické proběhnuté
            )
            || ($uzivatel && ($this->prihlasen($uzivatel) || $this->organizuje($uzivatel)))
        );
    }

    /**
     * @return string text s informací o extra vybavení pro tuto aktivitu
     */
    public function vybaveni()
    {
        if ($this->a[Sql::PATRI_POD]) {
            return dbOneCol('SELECT MAX(vybaveni) FROM akce_seznam WHERE patri_pod = $1', [$this->a[Sql::PATRI_POD]]);
        }

        return dbOneCol('SELECT vybaveni FROM akce_seznam WHERE id_akce = $1', [$this->id()]);
    }

    /**
     * Vrátí formulář pro výběr teamu na aktivitu. Pokud není zadán uživatel,
     * vrací nějakou false ekvivalentní hodnotu.
     * @todo ideálně převést na nějaké statické metody týmu nebo samostatnou třídu
     */
    public function vyberTeamu(Uzivatel $u = null)
    {
        if (!$u || $this->a[Sql::ZAMCEL] != $u->id() || !$this->prihlasovatelna()) {
            return null;
        }

        $t     = new XTemplate(__DIR__ . '/templates/tym-formular.xtpl'); // obecné proměnné šablony
        $zbyva = strtotime($this->a[Sql::ZAMCEL_CAS]) + self::HAJENI_TEAMU_HODIN * 60 * 60 - time();
        $t->assign([
            'zbyva'                => floor($zbyva / 3600) . ' hodin ' . floor($zbyva % 3600 / 60) . ' minut',
            'postname'             => self::TEAMKLIC,
            'prihlasenyUzivatelId' => $u->id(),
            'aktivitaId'           => $this->id(),
            'cssUrlAutocomplete'   => URL_WEBU . '/soubory/blackarrow/_spolecne/auto-complete.css',
            'jsUrlAutocomplete'    => URL_WEBU . '/soubory/blackarrow/_spolecne/auto-complete.min.js',
            'jsUrl'                => URL_WEBU . '/soubory/blackarrow/tym-formular/tym-formular.js',
        ]); // výběr instancí, pokud to aktivita vyžaduje
        if ($this->a[Sql::DITE]) {

            // načtení "kol" (podle hloubky zanoření v grafu instancí)
            $urovne[] = [$this];
            do {
                $dalsi = [];
                foreach (end($urovne) as $a) {
                    if ($a->a[Sql::DITE]) {
                        $dalsi = [...$dalsi, ...$this->parseIds($a->a[Sql::DITE] ?? '')];
                    }
                }
                if ($dalsi) {
                    $urovne[] = self::zIds($dalsi);
                }
            } while ($dalsi);
            unset($urovne[0]); // aktuální aktivitu už má přihlášenu - ignorovat

            // vybírací formy dle "kol"
            foreach ($urovne as $i => $uroven) {
                $t->assign('postnameKolo', self::TEAMKLIC_KOLA . '[' . $i . ']');
                foreach ($uroven as $varianta) {
                    $t->assign([
                        'koloId' => $varianta->id(),
                        'nazev'  => $varianta->nazev() . ': ' . $varianta->denCasSkutecny(),
                    ]);
                    $t->parse('formular.kola.uroven.varianta');
                }
                $t->parse('formular.kola.uroven');
            }
            $t->parse('formular.kola');

        }

        // políčka pro výběr míst
        for ($i = 0; $i < $this->kapacita() - 1; $i++) {
            $t->assign('postnameMisto', self::TEAMKLIC . '[' . $i . ']');
            if ($i >= $this->a[Sql::TEAM_MIN] - 1) { // -1 za týmlídra
                $t->parse('formular.misto.odebrat');
            }
            $t->parse('formular.misto');
        }

        // název (povinný pro DrD)
        if ($this->a[Sql::TYP] == TypAktivity::DRD) {
            $t->parse('formular.nazevPovinny');
        } else {
            $t->parse('formular.nazevVolitelny');
        }

        // výpis celého formuláře
        $t->parse('formular');

        return $t->text('formular');
    }

    /**
     * Zpracuje data formuláře pro výběr teamu a vrátí případné chyby jako json.
     * Ukončuje skript.
     */
    public static function vyberTeamuZpracuj(
        ?Uzivatel $leader,
        ?Uzivatel $prihlasujici,
    ) {
        if (!$leader || !post(self::TEAMKLIC . 'Aktivita')) {
            return;
        }

        $a = Aktivita::zId(post(self::TEAMKLIC . 'Aktivita'));
        if ($leader->id() != $a->a[Sql::ZAMCEL]) {
            throw new \Chyba('Nejsi teamleader.');
        }

        // načtení zvolených parametrů z formuláře (spoluhráči, kola, ...)
        $up      = post(self::TEAMKLIC) ?? [];
        $zamceno = 0;
        foreach ($up as $i => $uid) {
            if ($uid == -1 || !$uid) {
                unset($up[$i]);
            }
            if ($uid == -1) {
                $zamceno++;
            }
        }
        $clenove      = Uzivatel::zIds($up);
        $novaKapacita = $a->kapacita() - $zamceno;
        $nazev        = post(self::TEAMKLIC . 'Nazev');
        $dalsiKola    = array_values(array_map(function (
            $id,
        ) { // array_map kvůli nutnosti zachovat pořadí
            return self::zId($id);
        }, post(self::TEAMKLIC_KOLA)
            ?: [])); // přihlášení týmu
        try {
            $a->prihlasTym($clenove, $prihlasujici, $nazev, $novaKapacita, $dalsiKola);
            $chyby = [];
        } catch (\Chyba $ch) {
            $chyby = [$ch->getMessage()];
        }

        echo json_encode(['chyby' => $chyby]);
        exit();
    }

    /**
     * Pozor! Toto neplatí pro online prezenci, kde se ručně přidaní účastníci rovnou označují jako "Dorazil náhradník"
     * a proto se ihned do tabulky akce_prihlaseni přidá záznam se stavem DORAZIL_JAKO_NAHRADNIK Pro online prezenci to
     * znamená jen "někdo už dorazil".
     *
     * Má aktivita vyplněnou prezenci?
     * (aktivity s 0 lidmi jsou považovány za nevyplněné vždycky)
     */
    public function nekdoUzDorazil()
    {
        return StavPrihlaseni::PRIHLASEN < dbOneCol('SELECT MAX(id_stavu_prihlaseni) FROM akce_prihlaseni WHERE id_akce = ' . $this->id());
    }

    /**
     * Vrátí DateTime objekt začátku aktivity
     * @return DateTimeCz|null
     */
    public function zacatek(): ?DateTimeCz
    {
        if ($this->a[Sql::ZACATEK] && is_string($this->a[Sql::ZACATEK])) {
            $this->a[Sql::ZACATEK] = new DateTimeCz($this->a[Sql::ZACATEK]);
        }

        return $this->a[Sql::ZACATEK]
            ?: null;
    }

    /** Je aktivita už proběhlá resp. už uzavřená pro změny? */
    public function zamcena(): bool
    {
        return $this->stav()->jeZamcena();
    }

    public function uzavrenaOd(): ?\DateTimeImmutable
    {
        if (!$this->uzavrena()) {
            return null;
        }
        if (!$this->uzavrenaOd) {
            $posledniZmenaAPosledniStav = $this->posledniZmenaAPosledniStav();
            if (!$posledniZmenaAPosledniStav) {
                return null;
            }
            ['id_stav' => $stavId, 'kdy' => $kdy] = $posledniZmenaAPosledniStav;
            if ($stavId != StavAktivity::UZAVRENA) {
                return null;
            }
            $this->uzavrenaOd = \DateTimeImmutable::createFromFormat(DateTimeCz::FORMAT_DB, $kdy);
        }

        return $this->uzavrenaOd;
    }

    private function posledniZmenaAPosledniStav(): array
    {
        return dbOneLine(<<<SQL
SELECT akce_stavy_log.id_stav, akce_stavy_log.kdy FROM akce_stavy_log
JOIN akce_seznam ON akce_stavy_log.id_akce = akce_seznam.id_akce
JOIN akce_stav ON akce_stavy_log.id_stav = akce_stav.id_stav
WHERE akce_stavy_log.id_akce = {$this->id()}
ORDER BY akce_stavy_log.akce_stavy_log_id DESC -- autoincrement, takže nejnovější má největší ID
LIMIT 1
SQL,
        )
            ?: [];
    }

    public function ucastniciOdebratelni(Uzivatel $odhlasujici): bool
    {
        return $this->ucastniciOdebratelniDo($odhlasujici) >= $this->systemoveNastaveni->ted();
    }

    public function ucastniciOdebratelniDo(Uzivatel $odhlasujici): \DateTimeImmutable
    {
        if (!$this->probehnuta()) {
            return $this->systemoveNastaveni->spocitanyKonecLetosnihoGameconu();
        }
        // Ze zamčené aktivity mohou účastníky odebírat (odpotvrzovat) jen její vypravěči či z admin stránky Prezence
        if ($this->zamcena()
            && ($this->maOrganizatora($odhlasujici) || $odhlasujici->maPravoNaPristupDoPrezence())
        ) {
            return $this->systemoveNastaveni->spocitanyKonecLetosnihoGameconu();
        }

        /*
         * Nechceme dovolit editaci účastníků už uzavřených aktivit ani vypravěčům a adminům.
         * (při odebrání by jim naskákala storna, při opětovném přidání bychom museli storno zrušit)
         */

        return $this->systemoveNastaveni->ted()->modify('-1 second');
    }

    public function lzeJestePridavatUcastniky(Uzivatel $prihlasujici): bool
    {
        return $this->ucastniciPridatelniDo($prihlasujici) >= $this->systemoveNastaveni->ted();
    }

    public function ucastniciPridatelniDo(Uzivatel $prihlasujici): \DateTimeImmutable
    {
        if ($prihlasujici->maPravoNaZmenuHistorieAktivit()) {
            // až do začátku příštího GC
            return \DateTimeImmutable::createFromMutable(
                DateTimeGamecon::zacatekGameconu($this->systemoveNastaveni->rocnik() + 1),
            );
        }

        if (!$this->zamcena() && !$this->uzavrena()) {
            return $this->systemoveNastaveni->ucastniciPridatelniDoNeuzavrenePrezenceDo();
        }

        if (!$this->maOrganizatora($prihlasujici) && !$prihlasujici->maPravoNaPristupDoPrezence()) {
            // na zamknutou nebo dokonce uzavřenou aktivitu už mohou účastníky přidávat jen organizátoři nebo někteří admini
            return $this->dejDrivejsiZacatekNeboPredChvilkou();
        }
        // jak organizátoři tak admini s přístupem do Prezence mohou stále přidávat na zamčenou aktivitu
        if (!$this->uzavrena()) {
            return $this->systemoveNastaveni->ucastniciPridatelniDoNeuzavrenePrezenceDo();
        }
        if (!$this->maOrganizatora($prihlasujici)) {
            // admini s přístupem do Prezence, kteří nejsou vypravěči této aktivity, nemohou přidávat účastníky do uzavřené aktivity
            return $this->dejDrivejsiZacatekNeboPredChvilkou();
        }
        if (!$this->konec()) {
            return $this->systemoveNastaveni->spocitanyKonecLetosnihoGameconu();
        }

        // vypravěči mohou přidávat účastníky k uzavřené aktivitě ještě několik minut po jejím konci
        return \DateTimeImmutable::createFromMutable(
            (clone $this->konec())
                ->modify("+ {$this->systemoveNastaveni->ucastnikyLzePridatXMinutPoUzavreniAktivity()} minutes"),
        );
    }

    private function dejDrivejsiZacatekNeboPredChvilkou(): \DateTimeImmutable
    {
        $zacatekAktivity = $this->zacatek();
        $predChvilkou    = $this->systemoveNastaveni->ted()->modify('-1 second');
        if (!$zacatekAktivity) {
            return $predChvilkou;
        }
        $zacatekAktivity = \DateTimeImmutable::createFromMutable($zacatekAktivity);

        return min($zacatekAktivity, $predChvilkou);
    }

    /** Je aktivita už proběhlá resp. už uzavřená pro změny? */
    public function uzavrena(): bool
    {
        return $this->stav()->jeUzavrena();
    }

    /** Zamče aktivitu pro další změny (k použití před jejím začátkem) */
    public function zamkni()
    {
        $this->zmenStavNa(StavAktivity::ZAMCENA);
    }

    private function zmenStavNa(int $stav)
    {
        dbQuery('UPDATE akce_seznam SET stav = $0 WHERE id_akce = ' . $this->id(), [$stav]);
        $this->a[Sql::STAV] = $stav;
        $this->stav         = $stav;
        $this->zalogujZmenuStavu($stav);
    }

    private function zalogujZmenuStavu(int $novyStav)
    {
        dbQuery('INSERT INTO akce_stavy_log(id_akce, id_stav, kdy) VALUES ($0, $1, NOW())', [$this->id(), $novyStav]);
        RazitkoPosledniZmenyPrihlaseni::smazRazitkaPoslednichZmen($this, $this->dejFilesystem());
    }

    /** Označí aktivitu jako uzavřenou, s vyplněnou prezencí */
    public function uzavri()
    {
        if (!$this->stav()->jeZamcena()) {
            $this->zamkni();
        }
        $this->zmenStavNa(StavAktivity::UZAVRENA);
    }

    /**
     * @param \DateTimeInterface $zacinajiciDo
     * @return array|int[]
     */
    public static function zamkniZacinajiciDo(
        \DateTimeInterface  $zacinajiciDo,
        ?SystemoveNastaveni $systemoveNastaveni = null,
    ): array {
        $systemoveNastaveni ??= SystemoveNastaveni::zGlobals();

        $ids = dbOneArray(<<<SQL
SELECT id_akce FROM akce_seznam
WHERE zacatek <= $0
    AND stav IN ($1)
    AND rok = $2
SQL,
            [
                0 => $zacinajiciDo->format(DateTimeCz::FORMAT_DB),
                1 => [
                    StavAktivity::NOVA,
                    StavAktivity::AKTIVOVANA,
                    StavAktivity::PUBLIKOVANA,
                    StavAktivity::PRIPRAVENA,
                    StavAktivity::SYSTEMOVA,
                ],
                2 => $systemoveNastaveni->rocnik(),
            ],
        );
        $ids = array_map('intval', $ids);
        foreach ($ids as $id) {
            Aktivita::zId($id, true)->zamkni();
        }

        return $ids;
    }

    /**
     * @param \DateTimeInterface $konciciNejmeneDo
     * @param \DateTimeInterface $konciciNejviceDo
     * @return int
     */
    public static function upozorniNaNeuzavreneKonciciOdDo(
        \DateTimeInterface  $konciciNejmeneDo,
        \DateTimeInterface  $konciciNejviceDo,
        ?SystemoveNastaveni $systemoveNastaveni = null,
    ): int {
        $systemoveNastaveni ??= SystemoveNastaveni::zGlobals();

        $ids = dbOneArray(<<<SQL
SELECT akce_seznam.id_akce
FROM akce_seznam
WHERE akce_seznam.konec BETWEEN $0 AND $1
    AND akce_seznam.stav NOT IN ($2)
    AND akce_seznam.rok = $3
    AND akce_seznam.cena > 0 -- u aktivit zdarma nás prezence tolik netrápí
SQL,
            [
                0 => $konciciNejmeneDo->format(DateTimeCz::FORMAT_DB),
                1 => $konciciNejviceDo->format(DateTimeCz::FORMAT_DB),
                2 => [StavAktivity::UZAVRENA, StavAktivity::SYSTEMOVA],
                3 => $systemoveNastaveni->rocnik(),
            ],
        );

        /** @var Aktivita[][] $vypraveciAktivit */
        $vypraveciAktivit = [];
        $ids              = array_map('intval', $ids);
        foreach ($ids as $id) {
            $neuzavrenaAktivita = Aktivita::zId($id, true);
            foreach ($neuzavrenaAktivita->dejOrganizatoriIds() as $idVypravece) {
                $vypraveciAktivit[$idVypravece]   = $vypraveciAktivit[$idVypravece] ?? [];
                $vypraveciAktivit[$idVypravece][] = $neuzavrenaAktivita;
            }
        }

        foreach ($vypraveciAktivit as $idVypravece => $neuzavreneAktivity) {
            $vypravec = Uzivatel::zId($idVypravece); // například nechceme posílat mail vypravěčským skupinám
            if ((!$vypravec->jeVypravec() && !$vypravec->jePartner()) || $vypravec->mrtvyMail()) {
                continue;
            }
            $text = 'Zkontroluj prezenci a zavři';
            foreach ($neuzavreneAktivity as $neuzavrenaAktivita) {
                $url  = $vypravec->mojeAktivityAdminUrl()
                        . '#' . OnlinePrezenceHtml::nazevProAnchor($neuzavrenaAktivita);
                $text .= "<br><a href='$url'>{$neuzavrenaAktivita->nazev()}</a> (skončila {$neuzavrenaAktivita->konec()->formatCasNaMinutyStandard()})";
            }
            $mail = GcMail::vytvorZGlobals();
            $mail->predmet(
                'Gamecon: Uzavři prosím prezenci na ' . (count($neuzavreneAktivity) === 1
                    ? 'aktivitě'
                    : (count($neuzavreneAktivity) . ' aktivitách')),
            );
            $mail->text($text);
            $mail->adresat($vypravec->mail());
            $mail->odeslat();
        }

        return count($vypraveciAktivit);
    }

    public function pridejDite(int $idDitete)
    {
        $detiIds = $this->detiIds();
        if (in_array($idDitete, $detiIds, true)) {
            return;
        }
        $detiIds[]          = $idDitete;
        $detiIds            = array_unique($detiIds);
        $detiString         = implode(',', $detiIds);
        $this->a[Sql::DITE] = $detiString;
        dbQuery('UPDATE akce_seznam SET dite = $1 WHERE id_akce = ' . $this->id(), [$detiString]);
    }

    /**
     * Vrátí pole aktivit s zadaným filtrem a řazením. Filtr funguje jako asoc.
     * pole s filtrovanými hodnotami, řazení jako pole s pořadím dle priorit.
     * Podporované volby filtru: (vše id nebo boolean)
     *  rok, typ, organizator, jenViditelne
     * @return Aktivita[]
     * @todo explicitní filtr i pro řazení (např. pole jako mapa veřejný řadící
     *  parametr => sloupec
     */
    public static function zFiltru(
        SystemoveNastaveni    $systemoveNastaveni,
        array                 $filtr,
        array                 $razeni = [],
        ?int                  $limit = null,
        bool                  $prednacitat = false,
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): array {
        $dalsiPouziteSqlTabulky = [];
        // sestavení filtrů
        $wheres1 = [];
        $wheres2 = [];
        if (!empty($filtr[FiltrAktivity::ROK])) {
            $wheres1[] = 'a.rok = ' . (int)$filtr[FiltrAktivity::ROK];
        }
        if (!empty($filtr[FiltrAktivity::NAZEV_AKCE])) {
            $wheres1[] = 'TRIM(a.nazev_akce) = ' . dbQv(trim($filtr[FiltrAktivity::NAZEV_AKCE]));
        }
        if (!empty($filtr[FiltrAktivity::TYP])) {
            $wheres1[] = 'a.typ = ' . (int)$filtr[FiltrAktivity::TYP];
        }
        if (!empty($filtr[FiltrAktivity::ORGANIZATOR])) {
            $dalsiPouziteSqlTabulky[] = AkceOrganizatoriSqlStruktura::AKCE_ORGANIZATORI_TABULKA;
            $wheres1[]                = 'a.id_akce IN (SELECT id_akce FROM akce_organizatori WHERE id_uzivatele = ' . (int)$filtr[FiltrAktivity::ORGANIZATOR] . ')';
        }
        if (!empty($filtr[FiltrAktivity::JEN_VIDITELNE])) {
            $wheres1[] = 'a.stav IN (' . implode(',', StavAktivity::bezneViditelneStavy()) . ')
                AND NOT (a.typ IN (' . implode(',', TypAktivity::interniTypy()) . ') AND a.stav IN (' . implode(',', StavAktivity::probehnuteStavy()/** stejné jako @see Aktivita::probehnuta */) . '))';
        }
        if (!empty($filtr[FiltrAktivity::JEN_ZAMCENE])) {
            $wheres1[] = 'a.stav = ' . StavAktivity::ZAMCENA;
        }
        if (!empty($filtr[FiltrAktivity::JEN_NEUZAVRENE])) {
            $wheres1[] = 'a.stav != ' . StavAktivity::UZAVRENA;
        }
        if (!empty($filtr[FiltrAktivity::JEN_NEVYPLNENE])) { // byli tam přihlášení nějací hráči a nikdo nedorazil
            $dalsiPouziteSqlTabulky[] = AkcePrihlaseniSqlStruktura::AKCE_PRIHLASENI_TABULKA;
            $dalsiPouziteSqlTabulky[] = AkcePrihlaseniSpecSqlStruktura::AKCE_PRIHLASENI_SPEC_TABULKA;
            $wheres1[]                = 'NOT EXISTS(SELECT 1 FROM akce_prihlaseni ap WHERE ap.id_akce = a.id_akce AND ap.id_stavu_prihlaseni IN (1, 2))';
            $wheres1[]                = 'EXISTS(SELECT 1 FROM akce_prihlaseni_spec aps WHERE aps.id_akce = a.id_akce AND aps.id_stavu_prihlaseni = 3)';
        }
        if (!empty($filtr[FiltrAktivity::OD])) {
            $wheres1[] = dbQv($filtr[FiltrAktivity::OD]) . ' <= a.zacatek';
        }
        if (!empty($filtr[FiltrAktivity::DO])) {
            $wheres1[] = 'a.zacatek <= ' . dbQv($filtr[FiltrAktivity::DO]);
        }
        if (!empty($filtr[FiltrAktivity::STAV])) {
            $wheres1[] = 'a.stav IN (' . dbQv($filtr[FiltrAktivity::STAV]) . ')';
        }
        if (!empty($filtr[FiltrAktivity::BEZ_DALSICH_KOL])) {
            $wheres1[] = 'NOT (a.typ IN (' . TypAktivity::DRD . ',' . TypAktivity::LKD . ') AND cena = 0)';
        }
        if (!empty($filtr[FiltrAktivity::PRIHLASENI])) {
            $dalsiPouziteSqlTabulky[] = AkcePrihlaseniSqlStruktura::AKCE_PRIHLASENI_TABULKA;
            $wheres1[]                = 'EXISTS (SELECT * FROM akce_prihlaseni AS p WHERE p.id_akce = a.id_akce AND p.id_uzivatele IN (' . dbQa((array)$filtr[FiltrAktivity::PRIHLASENI]) . '))';
        }
        $where1    = implode(' AND ', $wheres1)
            ?: '1';
        $where2    = implode(' AND ', $wheres2)
            ?: '1'; // sestavení řazení
        $order     = null;
        $phpRazeni = [];
        $orderBy   = [];
        foreach ($razeni as $sloupec) {
            $directionRegExp = '~\s(?<direction>ASC|DESC)\s*$~i';
            preg_match($directionRegExp, $sloupec, $matches);
            $direction = 'ASC';
            if (!empty($matches['direction'])) {
                $direction = strtoupper(trim($matches['direction']));
                $sloupec   = preg_replace($directionRegExp, '', $sloupec);
            }
            if ($sloupec === 'organizatori') {
                $phpRazeni[$sloupec] = $direction;
            } else {
                $orderBy[] = sprintf('%s %s', dbQi($sloupec), $direction);
            }
        }
        if (count($orderBy) > 0) {
            $order = 'ORDER BY ' . implode(', ', $orderBy);
        }

        // select
        $aktivity = self::zWhere(
            systemoveNastaveni: $systemoveNastaveni,
            dalsiPouziteSqlTabulky: $dalsiPouziteSqlTabulky,
            where1: 'WHERE ' . $where1,
            where2: 'WHERE ' . $where2,
            order: $order,
            limit: $limit,
            prednacitat: $prednacitat,
            dataSourcesCollector: $dataSourcesCollector,
        );
        if (!empty($filtr[FiltrAktivity::JEN_VOLNE])) {
            foreach ($aktivity as $id => $a) {
                if ($a->volno($dataSourcesCollector) === 'x') {
                    unset($aktivity[$id]);
                }
            }
        }

        // řazení v php
        if (!empty($phpRazeni['organizatori'])) { // prozatím podporujeme jen řazení dle orga
            if ($phpRazeni['organizatori'] === 'DESC') {
                usort(
                    $aktivity,
                    static function (
                        Aktivita $a,
                        Aktivita $b,
                    ) use
                    (
                        $dataSourcesCollector,
                    ) {
                        $jmenoA = $a->organizatori($dataSourcesCollector)
                            ? current($a->organizatori($dataSourcesCollector))->jmenoNick()
                            : '';
                        $jmenoB = $b->organizatori($dataSourcesCollector)
                            ? current($b->organizatori($dataSourcesCollector))->jmenoNick()
                            : '';

                        return strcmp($jmenoB, $jmenoA);
                    },
                );
            } else {
                usort(
                    $aktivity,
                    static function (
                        Aktivita $a,
                        Aktivita $b,
                    ) use
                    (
                        $dataSourcesCollector,
                    ) {
                        $jmenoA = $a->organizatori($dataSourcesCollector)
                            ? current($a->organizatori($dataSourcesCollector))->jmenoNick()
                            : '';
                        $jmenoB = $b->organizatori($dataSourcesCollector)
                            ? current($b->organizatori($dataSourcesCollector))->jmenoNick()
                            : '';

                        return strcmp($jmenoA, $jmenoB);
                    },
                );
            }
        }

        return $aktivity;
    }

    /**
     * Pokusí se vyčíst aktivitu z dodaného ID.
     * @return self|null
     */
    public static function zId(
        string | int | null $id,
        bool                $zCache = false,
        SystemoveNastaveni  $systemoveNastaveni = null,
    ): ?Aktivita {
        if (!$id) {
            return null;
        }
        $id = (int)$id;
        if (!$id) {
            return null;
        }
        if ($zCache) {
            $cachovanaAktivita = self::$objekty['ids'][$id] ?? null;
            if ($cachovanaAktivita) {
                return $cachovanaAktivita;
            }
        }
        $systemoveNastaveni ??= SystemoveNastaveni::zGlobals();
        $aktivita           = current(self::zWhere(
            systemoveNastaveni: $systemoveNastaveni,
            dalsiPouziteSqlTabulky: [],
            where1: 'WHERE a.id_akce=' . $id,
        ));
        if (!$aktivita) {
            return null;
        }
        if ($zCache) {
            self::$objekty['ids'][$id] = $aktivita;
        }

        return $aktivita;
    }

    /**
     * Načte aktivitu z pole ID nebo řetězce odděleného čárkami
     * @return Aktivita[]
     */
    public static function zIds(
        $ids,
        SystemoveNastaveni $systemoveNastaveni = null,
    ): array {
        if (empty($ids)) {
            return [];
        }
        if (!is_array($ids)) {
            $ids = explode(',', $ids);
        }
        $ids = array_map('trim', $ids);
        $ids = array_filter($ids, static function (
            $id,
        ) {
            return $id !== '';
        });
        if (empty($ids)) {
            return [];
        }

        return self::zWhere(
            systemoveNastaveni: $systemoveNastaveni ?? SystemoveNastaveni::zGlobals(),
            dalsiPouziteSqlTabulky: [],
            where1: 'WHERE a.id_akce IN(' . dbQa($ids) . ')',
        );
    }

    public static function prednactiVse(SystemoveNastaveni $systemoveNastaveni): void
    {
        $aktivity = self::zWhere(
            systemoveNastaveni: $systemoveNastaveni,
            dalsiPouziteSqlTabulky: [],
            where1: '',
        );
        foreach ($aktivity as $aktivita) {
            self::$objekty['ids'][$aktivita->id()] = $aktivita;
        }
    }

    /**
     * Vrátí všechny aktivity, které vede daný uživatel
     * @return array<Aktivita>
     */
    public static function zOrganizatora(
        Uzivatel            $u,
        ?SystemoveNastaveni $systemoveNastaveni = null,
    ): array {
        $systemoveNastaveni ??= SystemoveNastaveni::zGlobals();

        return self::zWhere(
            systemoveNastaveni: $systemoveNastaveni,
            dalsiPouziteSqlTabulky: [AkceOrganizatoriSqlStruktura::AKCE_ORGANIZATORI_TABULKA],
            where1: "WHERE EXISTS (SELECT * FROM akce_organizatori ao WHERE ao.id_akce = a.id_akce AND ao.id_uzivatele = {$u->id()}) AND a.rok = {$systemoveNastaveni->rocnik()}",
        );
    }

    public function maOrganizatora(Uzivatel $organizator): bool
    {
        return in_array($organizator->id(), $this->dejOrganizatoriIds());
    }

    /**
     * Vrátí pole aktivit které se letos potenciálně zobrazí v programu
     */
    public static function zProgramu(
        string              $razeni,
        bool                $zCache = false,
        bool                $prednacitat = false,
        ?SystemoveNastaveni $systemoveNastaveni = null,
    ) {
        if ($zCache) {
            $objekt = self::$objekty['razeni'][$razeni] ?? null;
            if ($objekt) {
                return $objekt;
            }
        }

        $systemoveNastaveni ??= SystemoveNastaveni::zGlobals();

        $aktivity = self::zWhere(
            systemoveNastaveni: $systemoveNastaveni,
            dalsiPouziteSqlTabulky: [],
            where1: 'WHERE a.rok = $0 AND a.zacatek AND (a.stav != $1 OR a.typ IN ($2))',
            args: [
                0 => $systemoveNastaveni->rocnik(),
                1 => StavAktivity::NOVA,
                2 => TypAktivity::interniTypy(),
            ],
            order: 'ORDER BY DAY(zacatek) - IF(HOUR(zacatek) >= ' . dbQv(PROGRAM_ZACATEK) . ', 0, 1), ' . dbQi($razeni) . ', DAY(zacatek), HOUR(zacatek), nazev_akce',
            prednacitat: $prednacitat,
        );

        if ($zCache) {
            self::$objekty['razeni'][$razeni] = $aktivity;
            foreach ($aktivity as $aktivita) {
                self::$objekty['ids'][$aktivita->id()] ??= $aktivita;
            }
        }

        return $aktivity;
    }

    /**
     * Vrátí aktivity z rozmezí (aktuálně s začátkem v rozmezí konkrétně)
     * @return Aktivita[]
     * @todo možno přidat flag 'celé v rozmezí'
     */
    public static function zRozmezi(
        \DateTimeInterface  $od,
        \DateTimeInterface  $do,
                            $flags = 0,
                            $razeni = [],
        ?SystemoveNastaveni $systemoveNastaveni = null,
    ): array {
        $aktivity = self::zFiltru(
            systemoveNastaveni: $systemoveNastaveni ?? SystemoveNastaveni::zGlobals(),
            filtr: [
                FiltrAktivity::JEN_VIDITELNE  => (bool)($flags & self::VEREJNE),
                FiltrAktivity::JEN_ZAMCENE    => (bool)($flags & self::ZAMCENE),
                FiltrAktivity::JEN_NEUZAVRENE => (bool)($flags & self::NEUZAVRENE),
                FiltrAktivity::OD             => $od->format(DateTimeCz::FORMAT_DB),
                FiltrAktivity::DO             => $do->format(DateTimeCz::FORMAT_DB),
            ],
            razeni: $razeni,
        );
        if ($flags & self::JEN_VOLNE) {
            foreach ($aktivity as $i => $a) {
                if ($a->volno() === 'x') {
                    unset($aktivity[$i]);
                }
            }
        }

        return $aktivity;
    }

    /**
     * @param string $nazev
     * @param int $rocnik
     * @return Aktivita[]
     */
    public static function zNazvuARoku(
        string              $nazev,
        int                 $rocnik,
        ?SystemoveNastaveni $systemoveNastaveni = null,
    ): array {
        return self::zFiltru(
            systemoveNastaveni: $systemoveNastaveni ?? SystemoveNastaveni::zGlobals(),
            filtr: [
                FiltrAktivity::NAZEV_AKCE => $nazev,
                FiltrAktivity::ROK        => $rocnik,
            ],
        );
    }

    /**
     * @param DateTimeCz $od
     * @param DateTimeCz $do
     * @param int $flags
     * @param array|string[] $razeni
     * @return array|DateTimeCz[]
     */
    public static function zacatkyAktivit(
        DateTimeCz $od,
        DateTimeCz $do,
                   $flags = 0,
                   $razeni = [],
    ): array {
        $aktivity = self::zRozmezi($od, $do, $flags, $razeni);
        /** @var \DateTime[][] $zacatky */
        $zacatky = [];
        foreach ($aktivity as $aktivita) {
            $zacatekHodin = $aktivita->zacatek()->format('YmdH');
            if (!array_key_exists($zacatekHodin, $zacatky)) {
                $zacatky[$zacatekHodin] = $aktivita->zacatek();
            }
        }

        return $zacatky;
    }

    /**
     * Vrátí iterátor s aktivitami podle zadané where klauzule. Alias tabulky
     * akce_seznam je 'a'.
     * @param string $where1 obsah where klauzule (bez úvodního klíč. slova WHERE)
     * @param array|null $args volitelné pole argumentů pro dbQueryS()
     * @param string $order volitelně celá klauzule ORDER BY včetně klíč. slova
     * @return Aktivita[]
     * @todo třída která obstará reálný iterátor, nejenom obalení pole (nevýhoda pole je nezměněná nutnost čekat, než
     *     se celá odpověď načte a přesype do paměti)
     */
    protected static function zWhere(
        SystemoveNastaveni    $systemoveNastaveni,
        array                 $dalsiPouziteSqlTabulky,
        string                $where1,
        string                $where2 = '',
        ?array                $args = null,
        ?string               $order = null,
        ?int                  $limit = null,
        bool                  $prednacitat = false,
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): array {
        $limitSql = $limit !== null
            ? "LIMIT $limit"
            : '';

        $plainSql = dbQueryAssemble(
            q: <<<SQL
                SELECT t3.*,
                    (SELECT GROUP_CONCAT(sjednocene_tagy.nazev ORDER BY kst.poradi, sjednocene_tagy.nazev)
                        FROM sjednocene_tagy
                        JOIN akce_sjednocene_tagy ON akce_sjednocene_tagy.id_tagu = sjednocene_tagy.id
                        JOIN kategorie_sjednocenych_tagu kst ON sjednocene_tagy.id_kategorie_tagu = kst.id
                        WHERE akce_sjednocene_tagy.id_akce = t3.id_akce
                    ) AS tagy,
                    (SELECT GROUP_CONCAT(akce_sjednocene_tagy.id_tagu)
                        FROM akce_sjednocene_tagy
                        WHERE akce_sjednocene_tagy.id_akce = t3.id_akce
                    ) AS ids_tagu
                FROM (
                    SELECT t2.*,
                        IF(t2.patri_pod, (SELECT MAX(url_akce) FROM akce_seznam WHERE patri_pod = t2.patri_pod), t2.url_akce) AS url_temp
                    FROM (
                        SELECT a.*, al.poradi, akce_typy.poradi AS poradi_typu,
                            COALESCE(GROUP_CONCAT(akce_organizatori.id_uzivatele), '') as organizatori
                        FROM akce_seznam a
                        LEFT JOIN akce_lokace al ON al.id_lokace = a.lokace
                        LEFT JOIN akce_typy ON a.typ = akce_typy.id_typu
                        LEFT JOIN akce_organizatori ON akce_organizatori.id_akce = a.id_akce
                        $where1
                        GROUP BY a.id_akce
                    ) AS t2
                    $where2
                    GROUP BY t2.id_akce
                ) AS t3
              $order
              $limitSql
            SQL,
            pole: $args ?? [],
        );
        $db       = $systemoveNastaveni->db();
        $result   = $db->dbFetchAll(
            relatedTables: [
                ...[
                    SjednoceneTagySqlStruktura::SJEDNOCENE_TAGY_TABULKA,
                    AkceSjednoceneTagySqlStruktura::AKCE_SJEDNOCENE_TAGY_TABULKA,
                    KategorieSjednocenychTaguSqlStruktura::KATEGORIE_SJEDNOCENYCH_TAGU_TABULKA,
                    AkceSeznamSqlStruktura::AKCE_SEZNAM_TABULKA,
                    AkceLokaceSqlStruktura::AKCE_LOKACE_TABULKA,
                    AkceTypySqlStruktura::AKCE_TYPY_TABULKA,
                    AkceOrganizatoriSqlStruktura::AKCE_ORGANIZATORI_TABULKA,
                ],
                ...$dalsiPouziteSqlTabulky,
            ],
            sql: $plainSql,
            dataSourcesCollector: $dataSourcesCollector,
        );

        $kolekce = []; // pomocný sdílený seznam aktivit pro přednačítání

        foreach ($result as $r) {
            $r[Sql::URL_AKCE] = $r['url_temp'];
            $aktivita         = new static(
                dbRow: $r,
                systemoveNastaveni: $systemoveNastaveni,
                prednacitat: $prednacitat,
            );

            $aktivita->kolekce                   = &$kolekce;
            $aktivita->kolekce[$r[Sql::ID_AKCE]] = $aktivita;
        }

        return array_values($kolekce);
    }

    public static function idExistujiciInstancePodleUrl(
        string $url,
        int    $rocnik,
        int    $typId,
    ): ?int {
        $idInstance = dbOneCol(<<<SQL
SELECT akce_seznam.patri_pod
FROM akce_seznam
WHERE akce_seznam.url_akce = $1 AND akce_seznam.rok = $2 AND akce_seznam.typ = $3 AND akce_seznam.patri_pod IS NOT NULL
LIMIT 1
SQL
            , [$url, $rocnik, $typId],
        );

        return $idInstance
            ? (int)$idInstance
            : null;
    }

    public static function idMozneHlavniAktivityPodleUrl(
        string $url,
        int    $rocnik,
        int    $typId,
    ): ?int {
        $idHlavniAktivity = dbOneCol(<<<SQL
SELECT MIN(akce_seznam.id_akce)
FROM akce_seznam
WHERE akce_seznam.url_akce = $1 AND akce_seznam.rok = $2 AND akce_seznam.typ = $3
SQL
            , [$url, $rocnik, $typId],
        );

        return $idHlavniAktivity
            ? (int)$idHlavniAktivity
            : null;
    }

    public static function moznaHlavniAktivitaPodleUrl(
        string $url,
        int    $rocnik,
        int    $typId,
    ): ?Aktivita {
        $idHlavniAktivity = static::idMozneHlavniAktivityPodleUrl($url, $rocnik, $typId);
        if (!$idHlavniAktivity) {
            return null;
        }

        return static::zId($idHlavniAktivity);
    }

    /**
     * @param Aktivita[] $aktivity
     * @return Aktivita[]
     */
    public static function seradPodleTypuCiNazvu(array $aktivity): array
    {
        usort($aktivity, static function (
            self $a,
            self $b,
        ) {
            $c = $a->typId() - $b->typId(); // seřazní podle typu aktivity
            if ($c != 0) {
                return $c;
            }

            return strcmp($a->nazev(), $b->nazev()); // seřazení podle názvu aktivity
        });

        return $aktivity;
    }

    /**
     * @return Aktivita[]
     */
    public static function dejZruseneAktivityUzivatele(
        \Uzivatel $uzivatel,
        string    $zdrojOdhlaseni,
        int       $rocnik,
    ): array {
        $idcka = dbFetchColumn(<<<SQL
            SELECT id_akce
            FROM akce_prihlaseni_log
            WHERE akce_prihlaseni_log.zdroj_zmeny = $0
                AND akce_prihlaseni_log.id_uzivatele = {$uzivatel->id()}
                AND akce_prihlaseni_log.rocnik = {$rocnik}
            SQL,
            [$zdrojOdhlaseni],
        );

        return static::zIds($idcka);
    }
}
