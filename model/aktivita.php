<?php

use \Gamecon\Cas\DateTimeCz;
use Gamecon\Admin\Modules\Aktivity\Import\AktivitaSqlSloupce;

require_once __DIR__ . '/../admin/scripts/modules/aktivity/_editor-tagu.php';

/**
 * Třída aktivity
 */
class Aktivita
{

    use Prednacitani;

    private static $cache = [];

    private
        $a,         // databázový řádek s aktivitou
        $kolekce,   // nadřízená kolekce, v rámci které byla aktivita načtena
        $lokace,
        $stav,
        $nova,      // jestli jde o nově uloženou aktivitu nebo načtenou z DB
        $organizatori,
        $nahradnici,
        $typ;

    const
        AJAXKLIC = 'aEditFormTest',  // název post proměnné, ve které jdou data, pokud chceme ajaxově testovat jejich platnost a čekáme json odpověď
        KOLA = 'aTeamFormKolo',      // název post proměnné s výběrem kol pro team
        OBRKLIC = 'aEditObrazek',    // název proměnné, v které bude případně obrázek
        TAGYKLIC = 'aEditTag',       // název proměnné, v které jdou tagy
        POSTKLIC = 'aEditForm',      // název proměnné (ve výsledku pole), v které bude editační formulář aktivity předávat data
        TEAMKLIC = 'aTeamForm',      // název post proměnné s formulářem pro výběr teamu
        PN_PLUSMINUSP = 'cAktivitaPlusminusp',  // název post proměnné pro úpravy typu plus
        PN_PLUSMINUSM = 'cAktivitaPlusminusm',  // název post proměnné pro úpravy typu mínus
        HAJENI = 72,      // počet hodin po kterýc aktivita automatick vykopává nesestavený tým
        LIMIT_POPIS_KRATKY = 180,  // max počet znaků v krátkém popisku
        // stavy přihlášení
        PRIHLASEN = 0,
        DORAZIL = 1,
        DORAZIL_NAHRADNIK = 2,
        NEDORAZIL = 3,
        POZDE_ZRUSIL = 4,
        NAHRADNIK = 5,
        //ignore a parametry kolem přihlašovátka
        BEZ_POKUT = 0b00010000,   // odhlášení bez pokut
        NEPOSILAT_MAILY = 0b10000000,   // odhlášení bez mailů náhradníkům
        PLUSMINUS = 0b00000001,   // plus/mínus zkratky pro měnění míst v team. aktivitě
        PLUSMINUS_KAZDY = 0b00000010,   // plus/mínus zkratky pro každého
        STAV = 0b00000100,   // ignorování stavu
        TECHNICKE = 0b01000000,   // přihlašovat i skryté technické aktivity
        ZAMEK = 0b00001000,   // ignorování zamčení
        ZPETNE = 0b00100000,   // možnost zpětně měnit přihlášení
        // parametry kolem továrních metod
        JEN_VOLNE = 0b00000001,   // jen volné aktivity
        VEREJNE = 0b00000010;   // jen veřejně viditelné aktivity

    /**
     * Vytvoří aktivitu dle výstupu z databáze. Pokud výstup (např. položkou
     * "přihlášen") je vztažen vůči uživateli, je potřeba ho zadat teď jako $u,
     * později to nebude možné.
     * @param array $dbRow
     * @throws \Exception
     */
    private function __construct(array $dbRow) {
        if (!$dbRow) {
            throw new Exception('Nelze vytvořiit aktivitu. Prázdný parametr jejího konstruktoru.');
        }
        $this->a = $dbRow;
        $this->nova = false;
    }

    /**
     * Aktivuje (zpřístupní pro přihlašování) aktivitu
     */
    function aktivuj() {
        if (!$this->zacatek()) throw new Chyba('Aktivita nemá nastavený čas');
        dbQuery('UPDATE akce_seznam SET stav = $1 WHERE id_akce = $2', [Stav::AKTIVOVANA, $this->id()]);
        // TODO invalidate $this
    }

    /**
     * Jestli je na aktivitu zakázáno uplatnit jakékoli procentuální slevy
     */
    public function bezSlevy(): bool {
        return (bool)$this->a['bez_slevy'];
    }

    /**
     * Cena aktivity čitelná člověkem, poplatná aktuálnímu okamžiku. V případě
     * uvedení uživatele vrací pro něj specifickou cenu.
     */
    function cena(Uzivatel $u = null) {
        if ($this->a['typ'] == 10)
            return null;
        else if (!($this->cenaZaklad() > 0))
            return 'zdarma';
        else if ($this->a['bez_slevy'])
            return round($this->cenaZaklad()) . '&thinsp;Kč';
        else if ($u && $u->gcPrihlasen())
            return round($this->cenaZaklad() * $u->finance()->slevaAktivity()) . '&thinsp;Kč';
        else
            return round($this->cenaZaklad()) . '&thinsp;Kč';
    }

    /** Základní cena aktivity */
    function cenaZaklad() {
        return $this->a['cena'];
    }

    /**
     * @return array Vrací pole dalších kol této aktivity. Každé další kolo je
     *  samo polem, v kterém jsou jednotlivé aktivity (varianty) z kterých se dá
     *  v daném kole vybírat.
     */
    function dalsiKola() {
        $dalsiKola = [];
        $dalsiKolo = [$this];
        while ($dalsiKolo = current($dalsiKolo)->deti()) {
            $dalsiKola[] = $dalsiKolo;
        }
        return $dalsiKola;
    }

    /** Délka aktivity v hodinách (float) */
    function delka() {
        if ($zacatek = $this->zacatek())
            return ($this->konec()->getTimestamp() - $zacatek->getTimestamp()) / 3600;
        else
            return 0.0;
    }

    /**
     * @return string datum ve stylu Pátek 14-18
     */
    function denCas() {
        if ($this->zacatek() && $this->konec()) {
            return $this->zacatek()->format('l G') . '–' . $this->konec()->format('G');
        }
        return '';
    }

    /** Vrátí potomky této aktivity (=navázané aktivity, další kola, ...) */
    function deti(): array {
        return self::zIds($this->detiIds());
    }

    public function maDite(int $idDitete): bool {
        return in_array($idDitete, $this->detiIds(), true);
    }

    /**
     * @return int[]
     */
    public function detiIds(): array {
        if (!$this->a['dite']) {
            return [];
        }
        return array_map(
            static function ($idDitete) {
                return (int)$idDitete;
            },
            array_map('trim', explode(',', $this->a['dite']))
        );
    }

    /** Počet hodin do začátku aktivity (float) */
    function doZacatku() {
        return ($this->zacatek()->getTimestamp() - time()) / 3600;
    }

    /**
     * Vrátí HTML kód editoru aktivit určený pro vytváření a editaci aktivity.
     * Podle nastavení $a buď aktivitu edituje nebo vytváří.
     * @todo Zkusit refaktorovat editor na samostatnou třídu, pokud to půjde bez
     * vytvoření závislostí na vnitřní proměnné aktivity.
     */
    static function editor(Aktivita $a = null) {
        return self::editorParam(new EditorTagu(), $a);
    }

    /**
     * Vrátí pole obsahující chyby znemožňující úpravu aktivity. Hodnoty jsou
     * chybové hlášky. Význam indexů ndef (todo možno rozšířit).
     * @param $a Pole odpovídající strukturou vkládanému (upravovanému) řádku DB,
     * podle toho nemá (má) id aktivity
     */
    protected static function editorChyby($a) {
        $chyby = [];

        // kontrola dostupnosti organizátorů v daný čas
        if (!empty($a['den']) && !empty($a['zacatek']) && !empty($a['konec'])) {
            $zacatek = (new DateTimeCz($a['den']))->add('PT' . $a['zacatek'] . 'H');
            $konec = (new DateTimeCz($a['den']))->add('PT' . $a['konec'] . 'H');
            $ignorovatAktivitu = isset($a['id_akce']) ? self::zId($a['id_akce']) : null;
            foreach ($a['organizatori'] ?? [] as $orgId) {
                $org = Uzivatel::zId($orgId);
                if (!$org->maVolno($zacatek, $konec, $ignorovatAktivitu)) {
                    $chyby[] = 'Organizátor ' . $org->jmenoNick() . ' má v danou dobu jinou aktivitu.';
                    // TODO doplnit název kolizní aktivity
                }
            }
        }

        // kontrola duplicit url
        if (dbOneLineS('SELECT 1 FROM akce_seznam
      WHERE url_akce = $1 AND ( patri_pod IS NULL OR patri_pod != $2 ) AND id_akce != $3 AND rok = $4',
            [$a['url_akce'], $a['patri_pod'], $a['id_akce'], ROK])
        ) {
            $chyby[] = sprintf("Url '%s' je už letos použitá pro jinou aktivitu. Vyberte jinou, nebo použijte tlačítko „inst“ v seznamu aktivit pro duplikaci.", $a['url_akce']);
        }

        return $chyby;
    }

    /**
     * Vrátí v chyby v JSON formátu (pro ajax) nebo FALSE pokud žádné nejsou
     */
    static function editorChybyJson() {
        $a = $_POST[self::POSTKLIC];
        return json_encode(['chyby' => self::editorChyby($a)]);
    }

    /**
     * Vrátí html kód editoru, je možné parametrizovat, co se pomocí něj dá měnit
     */
    protected static function editorParam(EditorTagu $editorTagu, Aktivita $aktivita = null, $omezeni = []) {
        // inicializace šablony
        $xtpl = new XTemplate(__DIR__ . '/editor-aktivity.xtpl');
        $xtpl->assign('fields', self::POSTKLIC); // název proměnné (pole) v kterém se mají posílat věci z formuláře
        $xtpl->assign('ajaxKlic', self::AJAXKLIC);
        $xtpl->assign('obrKlic', self::OBRKLIC);
        $xtpl->assign('obrKlicUrl', self::OBRKLIC . 'Url');
        $xtpl->assign('aEditTag', self::TAGYKLIC);
        $xtpl->assign('limitPopisKratky', self::LIMIT_POPIS_KRATKY);

        if ($aktivita) {
            $aktivitaData = $aktivita->a; // databázový řádek
            $xtpl->assign($aktivitaData);
            $xtpl->assign('popis', dbText($aktivitaData['popis']));
            $xtpl->assign('urlObrazku', $aktivita->obrazek());
            $xtpl->assign('vybaveni', $aktivita->vybaveni());
        }

        $vybraneTagy = $aktivita ? $aktivita->tagy() : [];
        self::nactiTagy($vybraneTagy, $editorTagu, $xtpl);

        // načtení lokací
        if (!$omezeni || !empty($omezeni['lokace'])) {
            self::parseUpravyTabulkaLokace($aktivita, $xtpl);
        }

        if (!$omezeni || !empty($omezeni['deti'])) {
            self::parseUpravyTabulkaDeti($aktivita, $xtpl);
        }

        if (!$omezeni || !empty($omezeni['rodice'])) {
            self::parseUpravyTabulkaRodice($aktivita, $xtpl);
        }

        // editace dnů + časů
        if (!$omezeni || !empty($omezeni['zacatek'])) {
            // načtení dnů
            self::parseUpravyTabulkaDen($aktivita, $xtpl);
            // načtení časů
            self::parseUpravyTabulkaZacatekAKonec($aktivita, $xtpl);
        }

        // načtení organizátorů
        if (!$omezeni || !empty($omezeni['organizator'])) {
            self::parseUpravyTabulkaVypraveci($aktivita, $xtpl);
        }

        // načtení typů
        if (!$omezeni || !empty($omezeni['typ'])) {
            self::parseUpravyTabulkaTypy($aktivita, $xtpl);
        }

        // výstup
        if (!$omezeni) {
            $xtpl->parse('upravy.tabulka');
        }
        $xtpl->parse('upravy');
        return $xtpl->text('upravy');
    }

    private static function parseUpravyTabulkaLokace(?Aktivita $aktivita, XTemplate $xtpl) {
        $aktivitaData = $aktivita ? $aktivita->a : null; // databázový řádek
        $q = dbQuery('SELECT id_lokace, nazev FROM akce_lokace ORDER BY poradi');
        $xtpl->assign(['id_lokace' => null, 'nazev' => '(žádná)', 'selected' => '']);
        $xtpl->parse('upravy.tabulka.lokace');
        while ($lokaceData = mysqli_fetch_assoc($q)) {
            $xtpl->assign('selected', $aktivita && $aktivitaData['lokace'] == $lokaceData['id_lokace'] ? 'selected' : '');
            $xtpl->assign($lokaceData);
            $xtpl->parse('upravy.tabulka.lokace');
        }
    }

    private static function parseUpravyTabulkaDeti(?Aktivita $aktivita, XTemplate $xtpl) {
        $q = dbQuery(
            "SELECT id_akce FROM akce_seznam WHERE id_akce != $1 AND rok = $2 ORDER BY nazev_akce",
            [$aktivita ? $aktivita->id() : null, ROK]
        );
        $detiIds = $aktivita ? $aktivita->detiIds() : [];
        while ($mozneDiteData = mysqli_fetch_assoc($q)) {
            $mozneDiteId = $mozneDiteData['id_akce'];
            $xtpl->assign(
                'selected',
                in_array($mozneDiteId, $detiIds, false) ? 'selected' : ''
            );
            $mozneDite = Aktivita::zId($mozneDiteId, true);
            $xtpl->assign('id_ditete', $mozneDiteId);
            $xtpl->assign('nazev_ditete', self::dejRozsirenyNazevAktivity($mozneDite));
            $xtpl->parse('upravy.tabulka.dite');
        }
    }

    private static function dejRozsirenyNazevAktivity(Aktivita $aktivita): string {
        return sprintf(
            '%d - %s - %s %s-%s',
            $aktivita->id(),
            $aktivita->nazev(),
            $aktivita->zacatek() ? $aktivita->zacatek()->format('l') : '',
            $aktivita->zacatek() ? $aktivita->zacatek()->format('G') : '',
            $aktivita->konec() ? $aktivita->konec()->format('G') : '',
        );
    }

    private static function parseUpravyTabulkaRodice(?Aktivita $aktivita, XTemplate $xtpl) {
        $q = dbQuery(
            "SELECT id_akce FROM akce_seznam WHERE id_akce != $1 AND rok = $2 ORDER BY nazev_akce",
            [$aktivita ? $aktivita->id() : null, ROK]
        );
        while ($moznyRodicData = mysqli_fetch_assoc($q)) {
            $moznyRodicId = $moznyRodicData['id_akce'];
            $moznyRodic = Aktivita::zId($moznyRodicId, true);
            $xtpl->assign(
                'selected',
                $aktivita && $moznyRodic->maDite($aktivita->id()) ? 'selected' : ''
            );
            $xtpl->assign('id_rodice', $moznyRodicId);
            $xtpl->assign('nazev_rodice', self::dejRozsirenyNazevAktivity($moznyRodic));
            $xtpl->parse('upravy.tabulka.rodic');
        }
    }

    private static function parseUpravyTabulkaDen(?Aktivita $aktivita, XTemplate $xtpl) {
        $xtpl->assign([
            'selected' => $aktivita && !$aktivita->zacatek() ? 'selected' : '',
            'den' => 0,
            'denSlovy' => '?',
        ]);
        $xtpl->parse('upravy.tabulka.den');
        for ($den = new DateTimeCz(PROGRAM_OD); $den->pred(PROGRAM_DO); $den->plusDen()) {
            $xtpl->assign([
                'selected' => $aktivita && $den->stejnyDen($aktivita->zacatek()) ? 'selected' : '',
                'den' => $den->format('Y-m-d'),
                'denSlovy' => $den->format('l'),
            ]);
            $xtpl->parse('upravy.tabulka.den');
        }
    }

    private static function parseUpravyTabulkaZacatekAKonec(?Aktivita $aktivita, XTemplate $xtpl) {
        $aZacatek = $aktivita && $aktivita->zacatek()
            ? (int)$aktivita->zacatek()->format('G')
            : null;
        $aKonec = $aktivita && $aktivita->konec()
            ? (int)$aktivita->konec()->sub(new DateInterval('PT1H'))->format('G')
            : null;
        $hodinyZacatku = range(PROGRAM_ZACATEK, PROGRAM_KONEC - 1, 1);
        array_unshift($hodinyZacatku, null);
        foreach ($hodinyZacatku as $hodinaZacatku) {
            $xtpl->assign('selected', $aZacatek === $hodinaZacatku ? 'selected' : '');
            $xtpl->assign('zacatek', $hodinaZacatku);
            $xtpl->assign('zacatekSlovy', $hodinaZacatku !== null ? ($hodinaZacatku . ':00') : '?');
            $xtpl->parse('upravy.tabulka.zacatek');

            $xtpl->assign('selected', $aKonec === $hodinaZacatku ? 'selected' : '');
            $xtpl->assign('konec', ($hodinaZacatku !== null ? $hodinaZacatku + 1 : null));
            $xtpl->assign('konecSlovy', $hodinaZacatku !== null ? (($hodinaZacatku + 1) . ':00') : '?');
            $xtpl->parse('upravy.tabulka.konec');
        }
    }

    private static function parseUpravyTabulkaVypraveci(?\Aktivita $aktivita, \XTemplate $xtpl) {
        $q = dbQuery('
                SELECT u.id_uzivatele, u.login_uzivatele, u.jmeno_uzivatele, u.prijmeni_uzivatele
                FROM uzivatele_hodnoty u
                LEFT JOIN r_uzivatele_zidle z USING(id_uzivatele)
                LEFT JOIN r_prava_zidle p USING(id_zidle)
                WHERE p.id_prava = ' . \Gamecon\Pravo::PORADANI_AKTIVIT . '
                GROUP BY u.login_uzivatele
                ORDER BY u.login_uzivatele
            ');
        $vsichniOrg = [];
        while ($uzivatelData = mysqli_fetch_assoc($q)) {
            $vsichniOrg[$uzivatelData['id_uzivatele']] = Uzivatel::jmenoNickZjisti($uzivatelData);
        }
        $aktOrg = $aktivita
            ? array_map(static function (Uzivatel $e) {
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

    private static function parseUpravyTabulkaTypy(?Aktivita $aktivita, XTemplate $xtpl) {
        $aktivitaData = $aktivita ? $aktivita->a : null; // databázový řádek
        $xtpl->assign(['selected' => '', 'id_typu' => 0, 'typ_1p' => '(bez typu – organizační)']);
        $xtpl->parse('upravy.tabulka.typ');
        $q = dbQuery('SELECT id_typu, typ_1p FROM akce_typy');
        while ($akceTypData = mysqli_fetch_assoc($q)) {
            $xtpl->assign('selected', $aktivita && $akceTypData['id_typu'] == $aktivitaData['typ'] ? 'selected' : '');
            $xtpl->assign($akceTypData);
            $xtpl->parse('upravy.tabulka.typ');
        }
    }

    private static function nactiTagy(array $vybraneTagy, EditorTagu $editorTagu, XTemplate $xtpl) {
        $vsechnyTagy = $editorTagu->getTagy();
        $pocetVsechTagu = count($vsechnyTagy);
        $nazevPredchoziKategorie = null;
        foreach ($vsechnyTagy as $indexTagu => $mappedTag) {
            $encodedTag = [];
            foreach ($mappedTag as $tagKey => $tagValue) {
                $encodedTag[$tagKey] = htmlspecialchars($tagValue);
            }
            $jeNovaKategorie = $nazevPredchoziKategorie !== $encodedTag['nazev_kategorie'];
            $xtpl->assign('id_tagu', $encodedTag['id']);
            $xtpl->assign('nazev_tagu', $encodedTag['nazev']);
            $xtpl->assign('tag_selected', in_array($encodedTag['nazev'], $vybraneTagy, true) ? 'selected' : '');
            $xtpl->assign(
                'previous_optgroup_tag_end',
                $jeNovaKategorie
                    ? '</optgroup>'
                    : ''
            );
            $xtpl->assign(
                'optgroup_tag_start',
                $jeNovaKategorie
                    ? '<optgroup label="' . mb_ucfirst($encodedTag['nazev_kategorie']) . '">'
                    : ''
            );
            $xtpl->assign(
                'last_optgroup_tag_end',
                $indexTagu + 1 === $pocetVsechTagu
                    ? '</optgroup>'
                    : ''
            );
            $xtpl->parse('upravy.tabulka.tag');
            $nazevPredchoziKategorie = $encodedTag['nazev_kategorie'];
        }
    }

    /**
     * Vrátí, jestli se volající stránka snaží získat JSON data pro ověření formu
     */
    static function editorTestJson() {
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
    static function editorZpracuj(): ?Aktivita {
        if (!isset($_POST[self::POSTKLIC])) {
            return null;
        }

        // úprava přijatých dat
        $a = $_POST[self::POSTKLIC];
        // v případě nezobrazení tabulky a tudíž chybějícího text. pole s url (viz šablona) se použije hidden pole s původní url
        if (empty($a['url_akce']) && !empty($_POST[self::POSTKLIC . 'staraUrl'])) {
            $a['url_akce'] = $_POST[self::POSTKLIC . 'staraUrl'];
        }
        // přepočet času
        if (empty($a['den']) || empty($a['zacatek']) || empty($a['konec'])) {
            if (!empty($a['den']) || !empty($a['zacatek']) || !empty($a['konec'])) {
                chyba('Buďto vyplň den se začátkem i koncem, nebo nic. Čas byl zrušen.', false);
            }
            $a['zacatek'] = null;
            $a['konec'] = null;
        } else {
            $a['zacatek'] = (new DateTimeCz($a['den']))->add(new DateInterval('PT' . $a['zacatek'] . 'H'))->formatDb();
            $a['konec'] = (new DateTimeCz($a['den']))->add(new DateInterval('PT' . $a['konec'] . 'H'))->formatDb();
        }
        unset($a['den']);
        // extra položky kvůli sep. tabulkám
        $organizatori = $a['organizatori'] ?? [];
        unset($a['organizatori']);
        $popis = $a['popis'];
        unset($a['popis']);

        $a['dite'] = !empty($a['dite'])
            ? implode(
                ',',
                array_map(static function ($diteId) {
                    return (int)$diteId;
                }, $a['dite'])
            )
            : null;

        $rodiceIds = [];
        if (!empty($a['rodic'])) {
            $rodiceIds = array_map(static function ($rodicId) {
                return (int)$rodicId;
            }, $a['rodic']);
            unset($a['rodic']);
        }

        if (!empty($a['teamova']) && isset($a['team_min'], $a['team_max']) && $a['team_min'] > $a['team_max']) {
            chyba(
                sprintf(
                    'Minimální kapacita týmu (%d) nemůže být větší než maximální kapacita týmu (%d). Kapacity týmu byly zrušeny.',
                    $a['team_min'],
                    $a['team_max']
                ),
                false
            );
            unset($a['teamova'], $a['team_min'], $a['team_max']);
        }

        $tagIds = [];
        foreach ((array)post(self::TAGYKLIC) as $tagId) {
            $tagId = (int)$tagId;
            if ($tagId) {
                $tagIds[] = $tagId;
            }
        }

        $obrazekSoubor = postFile(self::OBRKLIC);
        $obrazekUrl = post(self::OBRKLIC . 'Url');

        $aktivita = self::uloz($a, $popis, $organizatori, $tagIds, $obrazekSoubor, $obrazekUrl);

        if ($rodiceIds) {
            $detiIds = $aktivita->detiIds();
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
                    false
                );
            }
        }

        oznameni('Aktivita byla uložena', false);
        return $aktivita;
    }

    public static function uloz(array $data, ?string $markdownPopis, array $organizatoriIds, array $tagIds, string $obrazekSoubor = null, string $obrazekUrl = null): Aktivita {
        $data['kapacita'] = !empty($data['kapacita']) ? $data['kapacita'] : 0;
        $data['kapacita_f'] = !empty($data['kapacita_f']) ? $data['kapacita_f'] : 0;
        $data['kapacita_m'] = !empty($data['kapacita_m']) ? $data['kapacita_m'] : 0;
        $data['bez_slevy'] = (int)!empty($data['bez_slevy']); //checkbox pro "bez_slevy"
        $data['teamova'] = (int)!empty($data['teamova']);   //checkbox pro "teamova"
        $data['team_min'] = $data['teamova'] ? (int)$data['team_min'] : null;
        $data['team_max'] = $data['teamova'] ? (int)$data['team_max'] : null;
        $data['patri_pod'] = !empty($data['patri_pod']) ? $data['patri_pod'] : null;
        $data['lokace'] = !empty($data['lokace']) ? $data['lokace'] : null;
        // u teamových aktivit se kapacita ignoruje - později se nechá jak je nebo přepíše minimem, pokud jde o novou aktivitu
        if ($data['teamova']) {
            unset($data['kapacita'], $data['kapacita_f'], $data['kapacita_m']);
        }

        // uložení změn do akce_seznam
        if (empty($data['patri_pod']) && !empty($data['id_akce'])) {
            // editace jediné aktivity
            dbInsertUpdate('akce_seznam', $data);
            $aktivita = self::zId($data['id_akce']);
        } elseif (!empty($data['patri_pod'])) {
            // editace aktivity z rodiny instancí
            $doHlavni = ['url_akce', 'popis', 'vybaveni'];  // věci, které se mají změnit jen u hlavní (master) `instance
            $doAktualni = ['lokace', 'zacatek', 'konec'];       // věci, které se mají změnit jen u aktuální instance
            $aktivita = self::zId($data['id_akce']); // instance už musí existovat
            if (array_key_exists(AktivitaSqlSloupce::STAV, $data)) {
                $aktivita->zmenStav($data[AktivitaSqlSloupce::STAV]);
                unset($data[AktivitaSqlSloupce::STAV]); // stav se může měnit jenom u jedné instance
            }
            // (zbytek se změní v obou)
            // určení hlavní aktivity
            $idHlavni = $aktivita->idHlavni();
            $patriPod = $data['patri_pod'];
            unset($data['patri_pod']);
            // změny v hlavní aktivitě
            $zmenyHlavni = array_diff_key($data, array_flip($doAktualni));
            $zmenyHlavni['id_akce'] = $idHlavni;
            dbInsertUpdate('akce_seznam', $zmenyHlavni);
            // změny v konkrétní instanci
            $zmenyAktualni = array_diff_key($data, array_flip($doHlavni));
            dbInsertUpdate('akce_seznam', $zmenyAktualni);
            // změny u všech
            $zmenyVse = array_diff_key($data, array_flip(array_merge($doHlavni, $doAktualni)));
            unset($zmenyVse['patri_pod'], $zmenyVse['id_akce']); // id se nesmí updatovat!
            dbUpdate('akce_seznam', $zmenyVse, ['patri_pod' => $patriPod]);
        } else {
            // vkládání nové aktivity
            // inicializace hodnot pro novou aktivitu
            $data['id_akce'] = null;
            $data['rok'] = ROK;
            if ($data['teamova']) $data['kapacita'] = $data['team_max'] ?? 0; // při vytváření nové aktivity se kapacita inicializuje na max. teamu
            if (empty($data['nazev_akce'])) $data['nazev_akce'] = '(bez názvu)';
            if (empty($data['stav'])) {
                $data['stav'] = Stav::NOVA;
            }
            // vložení
            dbInsertUpdate('akce_seznam', $data);
            $data['id_akce'] = dbInsertId();
            $aktivita = self::zId($data['id_akce']);
            $aktivita->nova = true;
        }

        if ($obrazekSoubor) {
            $aktivita->obrazek(Obrazek::zSouboru($obrazekSoubor));
        } else if ($obrazekUrl) {
            $aktivita->obrazek(Obrazek::zUrl($obrazekUrl));
        }
        $aktivita->organizatori($organizatoriIds);
        $aktivita->popis($markdownPopis);
        $aktivita->nastavTagyPodleId($tagIds);

        return $aktivita;
    }

    function id(): int {
        return (int)$this->a['id_akce'];
    }

    /**
     * @return Aktivita[] pole instancí této aktivity (vč. sebe sama, i pokud více
     *  instancí nemá)
     */
    private function instance(): array {
        if ($this->patriPod()) {
            $ids = dbOneArray('SELECT id_akce FROM akce_seznam WHERE patri_pod = $1', [$this->patriPod()]);
            return Aktivita::zIds($ids);
        }
        return [$this];
    }

    /**
     * @return int[] pole s ID instancí této aktivity (vč. sebe sama, i pokud více *  instancí nemá)
     */
    private function idInstanci(): array {
        if ($this->patriPod()) {
            $ids = dbOneArray('SELECT id_akce FROM akce_seznam WHERE patri_pod = $1', [$this->patriPod()]);
            return array_map(static function ($id) {
                return (int)$id;
            }, $ids);
        }
        return [$this->id()];
    }

    public function pocetInstanci(): int {
        if (!$this->a['patri_pod']) {
            return 0;
        }
        return (int)dbOneCol('SELECT COUNT(*) FROM akce_seznam WHERE patri_pod = $1', [$this->a['patri_pod']]);
    }

    public function hlavni(): Aktivita {
        if (!$this->a['patri_pod']) {
            return $this;
        }
        return static::zId($this->idHlavni());
    }

    public function idHlavni(): int {
        if (!$this->a['patri_pod']) {
            return $this->id();
        }
        $idHlavniAkce = dbOneCol('SELECT id_hlavni_akce FROM akce_instance WHERE id_instance = ' . $this->a['patri_pod']);
        if ($idHlavniAkce) {
            return (int)$idHlavniAkce;
        }
        throw new \RuntimeException("Chybí záznam o hlavní aktivitě pro instanci {$this->a['patri_pod']}");
    }

    public function jeHlavni(): bool {
        return $this->idHlavni() === $this->id();
    }

    public function jeInstance(): bool {
        return !$this->jeHlavni();
    }

    /**
     * Vytvoří novou instanci aktivity
     * @return self nově vytvořená instance
     */
    function instancuj() {
        $akt = dbOneLine('SELECT * FROM akce_seznam WHERE id_akce=' . $this->id());
        //odstraníme id, url a popisek, abychom je nepoužívali/neduplikovali při vkládání
        //stav se vloží implicitní hodnota v DB
        unset($akt['id_akce'], $akt['url_akce'], $akt['stav'], $akt['zamcel'], $akt['vybaveni']);
        if ($akt['teamova']) $akt['kapacita'] = $akt['team_max'];
        if ($akt['patri_pod']) { //aktivita už má instanční skupinu, použije se stávající
            dbInsert('akce_seznam', $akt);
            $idNoveAktivity = dbInsertId();
        } else { //aktivita je zatím bez instanční skupiny - vytvoříme
            dbBegin();
            try {
                $patriPod = $this->vytvorInstanci();
                $akt['patri_pod'] = $patriPod;
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

    private function vytvorInstanci(): int {
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
    protected function jsouDalsiKola($aktivity) {
        $dalsiKola = $this->dalsiKola();

        if (count($aktivity) != count($dalsiKola)) return false;

        foreach ($this->dalsiKola() as $i => $varianty) {
            $idsVariant = [];
            foreach ($varianty as $varianta) $idsVariant[] = $varianta->id();

            $idVybraneVarianty = $aktivity[$i]->id();

            if (!in_array($idVybraneVarianty, $idsVariant)) return false;
        }

        return true;
    }

    /** Vrací celkovou kapacitu aktivity */
    public function kapacita() {
        return $this->a['kapacita'] + $this->a['kapacita_m'] + $this->a['kapacita_f'];
    }

    protected function kolekce() {
        return $this->kolekce;
    }

    /** Vrátí DateTime objekt konce aktivity */
    function konec(): ?DateTimeCz {
        if ($this->a['konec'] && is_string($this->a['konec'])) {
            $this->a['konec'] = new DateTimeCz($this->a['konec']);
        }
        return $this->a['konec'] ?: null;
    }

    /**
     * @return string krátký popis aktivity (plaintext)
     */
    function kratkyPopis() {
        return $this->a['popis_kratky'];
    }

    /** Vrátí lokaci (ndef. formát, ale musí podporovat __toString) */
    function lokace(): ?Lokace {
        if (is_numeric($this->lokace)) {
            $this->prednactiN1([
                'atribut' => 'lokace',
                'cil' => Lokace::class,
            ]);
        }
        return $this->lokace ?: null;
    }

    function lokaceId(): ?int {
        return $this->a['lokace'] !== null
            ? (int)$this->a['lokace']
            : null;
    }

    /**
     * Vrátí pole uživatelů, kteří jsou náhradníky na aktivitě .
     */
    function nahradnici() {
        if (!isset($this->nahradnici)) {
            return Uzivatel::zIds(dbOneCol('
        SELECT GROUP_CONCAT(aps.id_uzivatele)
        FROM akce_seznam a
        LEFT JOIN akce_prihlaseni_spec aps ON aps.id_akce = a.id_akce
        WHERE aps.id_akce = ' . $this->id() . ' AND aps.id_stavu_prihlaseni = ' . self::NAHRADNIK
            ));
        }
        return $this->nahradnici;
    }

    function nazev(): string {
        return (string)$this->a['nazev_akce'];
    }

    /**
     * Aktivita negeneruje slevu organizátorovi
     */
    function nedavaSlevu(): bool {
        return (bool)$this->a['nedava_slevu'];
    }

    /**
     * Jestli objekt aktivity představuje nově vytvořený řádek v databázi, nebo
     * byl jenom z DB načten.
     * @return bool false - načtený z databáze, true - nový, vložený do databáze
     */
    function nova() {
        return $this->nova;
    }

    /**
     * Vrací absolutní adresu k obrázku aktivity. Ošetřeno cacheování.
     */
    function obrazek(Obrazek $obrazek = null): ?Nahled {
        $soub = $this->cestaObrazku();
        if (!$obrazek) {
            try {
                return Nahled::zSouboru($soub)->pasuj(400);
            } catch (Exception $e) {
                return null;
            }
        }
        $obrazek->fitCrop(2048, 2048);
        $obrazek->uloz($soub);
        return null;
    }

    public function cestaObrazku(): string {
        return rtrim(WWW, '/') . '/soubory/systemove/aktivity/' . $this->a['url_akce'] . '.jpg';
    }

    public function maObrazek(): bool {
        return file_exists($this->cestaObrazku());
    }

    public function urlObrazku(): string {
        return rtrim(URL_WEBU, '/') . '/soubory/systemove/aktivity/' . $this->a['url_akce'] . '.jpg';
    }

    /** (Správný) alias pro obsazenostHtml() */
    function obsazenost() {
        return $this->obsazenostHtml();
    }

    /** Vrátí html kód s políčky určujícímí obsazenost */
    function obsazenostHtml() {
        $m = $this->prihlasenoMuzu(); // počty
        $f = $this->prihlasenoZen();
        $c = $m + $f;
        $km = $this->a['kapacita_m']; // kapacity
        $kf = $this->a['kapacita_f'];
        $ku = $this->a['kapacita'];
        $kc = $ku + $km + $kf;
        if (!$kc)
            return '';
        if (!$this->prihlasovatelna() && !$this->probehnuta()) //u proběhnutých aktivit se zobrazí čísla. Možno měnit.
            return " <span class=\"neprihlasovatelna\">($c/$kc)</span>";
        switch ($this->volno()) {
            case 'u':
            case 'x':
                return " ($c/$kc)";
            case 'f':
                return ' <span class="f">(' . $f . '/' . $kf . ')</span>' .
                    ' <span class="m">(' . $m . '/' . ($km + $ku) . ')</span>';
            case 'm':
                return ' <span class="f">(' . $f . '/' . ($kf + $ku) . ')</span>' .
                    ' <span class="m">(' . $m . '/' . $km . ')</span>';
        }
    }

    /**
     * Odemče hromadně zamčené aktivity a odhlásí ty, kteří nesestavili teamy.
     * Vrací počet odemčených teamů (=>uvolněných míst)
     */
    static function odemciHromadne() {
        $o = dbQuery('SELECT id_akce, zamcel FROM akce_seznam WHERE zamcel AND zamcel_cas < NOW() - interval ' . self::HAJENI . ' hour');
        $i = 0;
        while (list($aid, $uid) = mysqli_fetch_row($o)) {
            Aktivita::zId($aid)->odhlas(Uzivatel::zId($uid));
            $i++;
        }
        return $i;
        // uvolnění zámku je součástí odhlášení, pokud je sám -> done
    }

    /**
     * Odhlásí uživatele z aktivity
     * @todo kontroly? (např. jestli je aktivní přihlašování?) (administrativní
     *  odhlašování z DrD počítá s možnosti odhlásit např. od semifinále dál)
     */
    function odhlas(Uzivatel $u, $params = 0) {
        foreach ($this->deti() as $dite) { // odhlášení z potomků
            $dite->odhlas($u); // spoléhá na odolnost proti odhlašování z aktivit kde uživatel není
        }
        if (!$this->prihlasen($u)) return; // ignorovat pokud přihlášen není tak či tak
        // reálné odhlášení
        $aid = $this->id();
        $uid = $u->id();
        dbQuery("DELETE FROM akce_prihlaseni WHERE id_uzivatele=$uid AND id_akce=$aid");
        dbQuery("INSERT INTO akce_prihlaseni_log SET id_uzivatele=$uid, id_akce=$aid, typ='odhlaseni'");
        if (ODHLASENI_POKUTA_KONTROLA && $this->doZacatku() < ODHLASENI_POKUTA1_H && !($params & self::BEZ_POKUT)) //pokuta aktivní
            dbQuery("INSERT INTO akce_prihlaseni_spec SET id_uzivatele=$uid, id_akce=$aid, id_stavu_prihlaseni=4");
        if ($this->a['zamcel'] == $uid)
            dbQuery("UPDATE akce_seznam SET zamcel=NULL, zamcel_cas=NULL, team_nazev=NULL WHERE id_akce=$aid");
        if ($this->a['teamova'] && $this->prihlaseno() == 1) // odhlašuje se poslední hráč
            dbQuery("UPDATE akce_seznam SET kapacita=team_max WHERE id_akce=$aid");
        // Poslání mailu lidem na watchlistu
        if ($this->volno() == "x" && !($params & self::NEPOSILAT_MAILY)) { // Před odhlášením byla aktivita plná
            $this->poslatMailNahradnikum();
        }
        $this->refresh();
    }

    /**
     * Odhlásí uživatele z náhradníků (watchlistu)
     */
    function odhlasNahradnika(Uzivatel $u) {
        // Ignorovat pokud není přihlášen jako náhradník
        if (!$u->prihlasenJakoNahradnikNa($this)) return;
        // Uložení odhlášení do DB
        dbQuery("DELETE FROM akce_prihlaseni_spec WHERE id_uzivatele=$0 AND id_akce=$1 AND id_stavu_prihlaseni=$2", [$u->id(), $this->id(), self::NAHRADNIK]);
        dbQuery("INSERT INTO akce_prihlaseni_log SET id_uzivatele=$0, id_akce=$1, typ='odhlaseni_watchlist'", [$u->id(), $this->id()]);
        $this->refresh();
    }

    /**
     * Odhlásí ze všech náhradnických slotů ve stejný čas jako aktivita po přihlášení na aktivitu.
     * @return bool True pokud došlo k odhlášení nějakých náhradnických slotů
     */
    public function odhlasZNahradnickychSlotu(Uzivatel $u): bool {
        $konfliktniAktivity = self::zIds(dbOneArray("
      SELECT p.id_akce
      FROM akce_prihlaseni_spec p
      JOIN akce_seznam a ON a.id_akce = p.id_akce
      WHERE
        p.id_stavu_prihlaseni = $3 AND
        p.id_uzivatele = $0 AND
        NOT (a.konec <= $1 OR $2 <= a.zacatek) -- aktivita 'a' se kryje s aktuální aktivitou
    ", [
            $u->id(), $this->a['zacatek'], $this->a['konec'], self::NAHRADNIK,
        ]));
        foreach ($konfliktniAktivity as $aktivita) {
            $aktivita->odhlasNahradnika($u);
        }
        return count($konfliktniAktivity) > 0;
    }

    /** Vráti aktivitu ze stavu připravená do stavu publikovaná */
    function odpriprav() {
        if ($this->idStavu() === Stav::PUBLIKOVANA) {
            return;
        }
        if ($this->idStavu() !== Stav::PRIPRAVENA) {
            throw new Exception('Aktivita není v stavu "připravená"');
        }
        dbQuery('UPDATE akce_seznam SET stav=$1 WHERE id_akce=$2', [Stav::PUBLIKOVANA, $this->id()]);
    }

    /**
     * Vrátí pole uživatelů, kteří jsou organizátory této aktivity. Při zadaném
     * parametru poli ID nastaví tyto organizátory.
     * @return Uzivatel[]
     * @todo dělat diff a ne delete/insert
     */
    function organizatori(array $ids = null) {
        if ($ids !== null) {
            dbQuery('DELETE FROM akce_organizatori WHERE id_akce = ' . $this->id());
        }
        if ($ids) {
            foreach ($ids as $id) {
                $id = (int)$id;
                if ($id) {
                    dbQuery('INSERT INTO akce_organizatori(id_akce, id_uzivatele)
            VALUES (' . $this->id() . ',' . $id . ')');
                }
            }
        } else {
            if (!isset($this->organizatori)) $this->prednactiMN([
                'atribut' => 'organizatori',
                'cil' => Uzivatel::class,
                'tabulka' => 'akce_organizatori',
                'zdrojSloupec' => 'id_akce',
                'cilSloupec' => 'id_uzivatele',
            ]);
            return $this->organizatori;
        }
    }

    /**
     * @return array|int[]
     */
    public function getOrganizatoriIds(): array {
        return dbOneArray('SELECT id_uzivatele FROM akce_organizatori WHERE id_akce = $1', [$this->id()]);
    }

    /**
     * Vrátí pole uživatelů, kteří jsou organizátory jakékoli ze skupiny instancí
     * aktivity. Pokud nemá instance, vrátí organizátory aktivity jak jsou.
     */
    function organizatoriSkupiny() {
        if ($this->a['patri_pod']) {
            return Uzivatel::zIds(dbOneCol('
        SELECT GROUP_CONCAT(ao.id_uzivatele)
        FROM akce_seznam a
        LEFT JOIN akce_organizatori ao USING (id_akce)
        WHERE a.patri_pod = ' . $this->a['patri_pod']
            ));
        }
        return $this->organizatori();
    }

    /**
     * @return string[]|ArrayIterator Vrátí iterátor jmen organizátorů v lidsky čitelné podobě.
     */
    function orgJmena() {
        $jmena = new ArrayIteratorTos;
        foreach ($this->organizatori() as $o) {
            $jmena[] = $o->jmenoNick();
        }
        return $jmena;
    }

    /**
     * @return string[]|ArrayIterator Vrátí iterátor loginů organizátorů
     */
    function orgLoginy() {
        $loginy = new ArrayIteratorTos;
        foreach ($this->organizatori() as $o) {
            $loginy[] = $o->login();
        }
        return $loginy;
    }

    /** Alias */
    function otoc() {
        $this->refresh();
    }

    public function patriPodAktivitu(): ?Aktivita {
        $patriPodInstanciId = $this->patriPod();
        if (!$patriPodInstanciId) {
            return null;
        }
        $hlavniAktivitaId = dbOneCol(<<<SQL
SELECT akce_instance.id_hlavni_akce FROM akce_instance
WHERE akce_instance.id_instance = $1
SQL
            , [$patriPodInstanciId]
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
    function patriPod(): ?int {
        return $this->a['patri_pod']
            ? (int)$this->a['patri_pod']
            : null;
    }

    /**
     * Vrátí formátovaný (html) popisek aktivity
     */
    function popis(string $popis = null) {
        if ($popis === null) {
            return dbMarkdown($this->a['popis']);
        }
        $oldId = $this->a['popis'];
        $id = dbTextHash($popis);
        if ($this->a['patri_pod']) {
            dbUpdate('akce_seznam', ['popis' => $id], ['patri_pod' => $this->a['patri_pod']]);
        } else {
            dbUpdate('akce_seznam', ['popis' => $id], ['id_akce' => $this->id()]);
        }
        $this->a['popis'] = $id;
        dbTextClean($oldId);
    }

    public function getPopisRaw(): ?string {
        return dbOneCol(<<<SQL
SELECT text
FROM texty
WHERE id = $1
SQL
            , [$this->a['popis']]
        );
    }

    /**
     * Vrátí form(y) s vybírátky plus a mínus pro změny počtů míst teamových akt.
     * @todo parametry typu komplexnost výpisu a že nemůže měnit kdokoli aktivut
     * ale jen ten kdo je na ni přihlášený (vs. orgové v adminu)
     */
    protected function plusminus(Uzivatel $u = null, $parametry = 0) {
        // kontroly
        if (!$this->a['teamova'] || $this->a['stav'] != Stav::AKTIVOVANA) return '';
        if ($parametry & self::PLUSMINUS && (!$u || !$this->prihlasen($u))) return '';
        // tisk formu
        $out = '';
        if ($this->a['team_max'] > $this->a['kapacita']) {
            $out .= ' <form method="post" style="display:inline"><input type="hidden" name="' . self::PN_PLUSMINUSP . '" value="' . $this->id() . '"><a href="#" onclick="this.parentNode.submit(); return false">▲</a></form>';
        }
        if ($this->a['team_min'] < $this->a['kapacita'] && $this->prihlaseno() < $this->a['kapacita']) {
            $out .= ' <form method="post" style="display:inline"><input type="hidden" name="' . self::PN_PLUSMINUSM . '" value="' . $this->id() . '"><a href="#" onclick="this.parentNode.submit(); return false">▼</a></form>';
        }
        return $out;
    }

    /** Zpracuje formy na měnění počtu míst team. aktivit */
    protected static function plusminusZpracuj(Uzivatel $u = null, $parametry = 0) {
        if (post(self::PN_PLUSMINUSP)) {
            dbQueryS('UPDATE akce_seznam SET kapacita = kapacita + 1 WHERE id_akce = $1', [post(self::PN_PLUSMINUSP)]);
            back();
        }
        if (post(self::PN_PLUSMINUSM)) {
            dbQueryS('UPDATE akce_seznam SET kapacita = kapacita - 1 WHERE id_akce = $1', [post(self::PN_PLUSMINUSM)]);
            back();
        }
    }

    /**
     * @return počet týmů přihlášených na tuto aktivitu
     */
    protected function pocetTeamu() {
        $id = $this->id();
        $idRegex = '(^|,)' . $this->id() . '(,|$)'; // reg. výraz odpovídající id aktivity v seznamu odděleném čárkami
        return dbOneCol('
      SELECT COUNT(1)
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
     * Pošle mail náhradníkům o volném místě na aktivitě.
     */
    private function poslatMailNahradnikum() {
        $emaily = dbOneArray("
      SELECT u.email1_uzivatele
      FROM akce_prihlaseni_spec a
      JOIN uzivatele_hodnoty u ON u.id_uzivatele = a.id_uzivatele
      WHERE a.id_akce = $0 AND a.id_stavu_prihlaseni = $1
    ", [$this->id(), self::NAHRADNIK]);
        foreach ($emaily as $email) {
            $mail = new GcMail();
            $mail->predmet('Gamecon: Volné místo na aktivitě ' . $this->nazev());
            $mail->text(hlaskaMail('uvolneneMisto', $this->nazev(), $this->denCas()));
            $mail->adresat($email);
            $mail->odeslat();
        }
    }

    /**
     * Přihlásí uživatele na aktivitu
     * @todo koncepčnější ignorování stavu
     */
    function prihlas(Uzivatel $u, $ignorovat = 0) {
        // kontroly
        if ($this->prihlasen($u)) {
            return;
        }
        if (!$u->maVolno($this->zacatek(), $this->konec())) {
            throw new Chyba(hlaska('kolizeAktivit'));
        }
        if (!$u->gcPrihlasen()) {
            throw new Exception('Nemáš aktivní přihlášku na GameCon.');
        }
        if ($this->volno() !== 'u' && $this->volno() !== $u->pohlavi()) {
            throw new Chyba(hlaska('plno'));
        }
        foreach ($this->deti() as $dite) { // nemůže se přihlásit na aktivitu, pokud už je přihášen na jinou aktivitu s stejnými potomky
            foreach ($dite->rodice() as $rodic) {
                if ($rodic->prihlasen($u)) {
                    throw new Chyba(hlaska('maxJednou'));
                }
            }
        }
        if ($this->a['team_kapacita'] !== null) {
            $jeNovyTym = false; // jestli se uživatel přihlašuje jako první z nového/dalšího týmu
            foreach ($this->rodice() as $rodic) {
                if ($rodic->prihlasen($u) && $rodic->prihlaseno() == 1) {
                    $jeNovyTym = true;
                    break;
                }
            }
            if ($jeNovyTym && $this->pocetTeamu() >= $this->a['team_kapacita']) {
                throw new Chyba('Na aktivitu ' . $this->nazev() . ': ' . $this->denCas() . ' je už přihlášen maximální počet týmů');
            }
        }

        // potlačitelné kontroly
        if ($this->a['zamcel'] && !($ignorovat & self::ZAMEK)) {
            throw new Chyba(hlaska('zamcena'));
        }
        if (!$this->prihlasovatelna($ignorovat)) {
            // hack na ignorování stavu
            $puvodniStav = $this->a['stav'];
            if ($ignorovat & self::STAV) {
                $this->a['stav'] = Stav::AKTIVOVANA; // nastavíme stav jako by bylo vše ok
            }
            $prihlasovatelna = $this->prihlasovatelna($ignorovat);
            $this->a['stav'] = $puvodniStav;
            if (!$prihlasovatelna) {
                throw new Exception('Aktivita není otevřena pro přihlašování.');
            }
        }

        // přihlášení na navázané aktivity (jen pokud není teamleader)
        if ($this->a['dite'] && $this->prihlaseno() > 0) {
            $deti = $this->deti();
            if (count($deti) === 1) {
                current($deti)->prihlas($u, self::STAV);
            } else {
                // vybrání jednoho uživatele, který už na navázané aktivity přihlášen je
                $vzor = Uzivatel::zId(substr(explode(',', $this->prihlaseniRaw())[1], 0, -2));
                $uspech = false;
                foreach ($deti as $dite) {
                    // přihlášení na navázané aktivity podle vzoru vybraného uživatele
                    if ($dite->prihlasen($vzor)) {
                        $dite->prihlas($u, self::STAV);
                        $uspech = true;
                        break;
                    }
                }
                if (!$uspech) {
                    throw new Exception('Nepodařilo se určit výběr dalšího kola.');
                }
            }
        }

        // odhlášení náhradnictví v kolidujících aktivitách
        $this->odhlasZNahradnickychSlotu($u);

        // přihlášení na samu aktivitu (uložení věcí do DB)
        $aid = $this->id();
        $uid = $u->id();
        if ($this->a['teamova'] && $this->prihlaseno() === 0 && $this->prihlasovatelna()) {
            dbUpdate('akce_seznam', ['zamcel' => $uid, 'zamcel_cas' => dbNow()], ['id_akce' => $aid]);
        }
        dbQuery("INSERT INTO akce_prihlaseni SET id_uzivatele=$uid, id_akce=$aid");
        dbQuery("INSERT INTO akce_prihlaseni_log SET id_uzivatele=$uid, id_akce=$aid, typ='prihlaseni'");
        if (ODHLASENI_POKUTA_KONTROLA) { //pokud by náhodou měl záznam za pokutu a přihlásil se teď, tak smazat
            dbQuery(
                'DELETE FROM akce_prihlaseni_spec WHERE id_uzivatele=$0 AND id_akce=$1 AND id_stavu_prihlaseni=4',
                [$uid, $aid]
            );
        }
        $this->refresh();
    }

    /** Jestli je uživatel  přihlášen na tuto aktivitu */
    function prihlasen(Uzivatel $u) {
        return strpos($this->prihlaseniRaw(), ',' . $u->id() . $u->pohlavi()) !== false;
    }

    /**
     * Vrátí serializovaný seznam přihlášených a pokud takový neexistuje, načte
     * ho. Formát seznamu je: ,1204m0,864f2,742f1,...,1001m1, kde čísla jsou id
     * uživatelů, písmena pohlaví a čísla z pohlavím stav přihlášení.
     * @see ucastnici
     */
    private function prihlaseniRaw() {
        if (!array_key_exists('prihlaseni', $this->a)) {
            throw new Exception ('Nenačteny počty přihlášených do aktivity.');
        }
        return $this->a['prihlaseni'];
    }

    /** Počet přihlášených */
    protected function prihlaseno(): int {
        if ($p = $this->prihlaseniRaw()) {
            return substr_count($p, ',') - 1;
        }
        return 0;
    }

    protected function prihlasenoMuzu() {
        return substr_count($this->prihlaseniRaw(), 'm');
    }

    protected function prihlasenoZen() {
        return substr_count($this->prihlaseniRaw(), 'f');
    }

    /**
     * Vrátí stav přihlášení uživatele na aktivitu. Pokud není přihlášen, vrací
     * hodnotu -1.
     */
    private function prihlasenStav(Uzivatel $u) {
        $prihlaseni = $this->prihlaseniRaw();
        $usymbol = ',' . $u->id() . $u->pohlavi();
        $pos = strpos($prihlaseni, $usymbol);
        if ($pos !== false) {
            return (int)substr($prihlaseni, $pos + strlen($usymbol), 1);
        }
        return -1;
    }

    /** Zdali chceme, aby se na aktivitu bylo možné běžně přihlašovat */
    function prihlasovatelna($parametry = 0) {
        $zpetne = $parametry & self::ZPETNE;
        $technicke = $parametry & self::TECHNICKE;
        // stav 4 je rezervovaný pro viditelné nepřihlašovatelné aktivity
        return (
            (REG_AKTIVIT || $zpetne && po(REG_GC_DO)) &&
            (
                $this->a['stav'] == Stav::AKTIVOVANA ||
                $technicke && $this->a['stav'] == Stav::NOVA && $this->a['typ'] == Typ::TECHNICKA ||
                $zpetne && $this->a['stav'] == Stav::PROBEHNUTA
            ) &&
            $this->a['zacatek'] &&
            $this->a['typ']
        );
    }

    /**
     * @return bool jestli je na aktivitu povoleno přihlašování náhradníků
     */
    function prihlasovatelnaNahradnikum() {
        return !$this->tymova() && !$this->a['dite'];
    }

    /**
     * Vrátí html kód pro přihlášení / odhlášení / informaci o zaplněnosti pro
     * daného uživatele. Pokud není zadán, vrací prázdný řetězec.
     * @todo v rodině instancí maximálně jedno přihlášení?
     * @todo konstanty pro jména POST proměnných? viz prihlasovatkoZpracuj
     */
    function prihlasovatko(Uzivatel $u = null, $parametry = 0) {
        $out = '';
        if ($u && $u->gcPrihlasen() && $this->prihlasovatelna($parametry)) {
            if (($stav = $this->prihlasenStav($u)) > -1) {
                if ($stav == 0 || $parametry & self::ZPETNE) {
                    $out .=
                        '<form method="post" style="display:inline">' .
                        '<input type="hidden" name="odhlasit" value="' . $this->id() . '">' .
                        '<a href="#" onclick="this.parentNode.submit(); return false">odhlásit</a>' .
                        '</form>';
                }
                if ($stav == 1) {
                    $out .= '<em>účast</em>';
                }
                if ($stav == 2) {
                    $out .= '<em>jako náhradník</em>';
                }
                if ($stav == 3) {
                    $out .= '<em>neúčast</em>';
                }
                if ($stav == 4) {
                    $out .= '<em>pozdní odhlášení</em>';
                }
            } elseif ($u->organizuje($this)) {
                $out = '';
            } elseif ($this->a['zamcel']) {
                $out = '&#128274;'; //zámek
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
                } elseif ($this->prihlasovatelnaNahradnikum()) {
                    if ($u->prihlasenJakoNahradnikNa($this)) {
                        $out =
                            '<form method="post" style="display:inline">' .
                            '<input type="hidden" name="odhlasNahradnika" value="' . $this->id() . '">' .
                            '<a href="#" onclick="this.parentNode.submit(); return false">zrušit sledování</a>' .
                            '</form>';
                    } else {
                        $out =
                            '<form method="post" style="display:inline">' .
                            '<input type="hidden" name="prihlasNahradnika" value="' . $this->id() . '">' .
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

    /** Zpracuje post data z přihlašovátka. Pokud došlo ke změně, vyvolá reload */
    static function prihlasovatkoZpracuj(Uzivatel $u = null, $parametry = 0) {
        if (post('prihlasit')) {
            self::zId(post('prihlasit'))->prihlas($u, $parametry);
            back();
        }
        if (post('odhlasit')) {
            $bezPokut = $parametry & self::ZPETNE ? self::BEZ_POKUT : 0; // v případě zpětných změn bez pokut
            self::zId(post('odhlasit'))->odhlas($u, $bezPokut);
            back();
        }
        if (post('prihlasNahradnika')) {
            self::zId(post('prihlasNahradnika'))->prihlasNahradnika($u);
            back();
        }
        if (post('odhlasNahradnika')) {
            self::zId(post('odhlasNahradnika'))->odhlasNahradnika($u);
            back();
        }
        if ($parametry & self::PLUSMINUS_KAZDY) {
            self::plusminusZpracuj($u, $parametry);
        }
    }

    /**
     * Dávkově přihlásí uživatele na tuto aktivitu a (bez postihu) odhlásí
     * aktivity, které s novou aktivitou kolidují
     * @param $uids pole s ID uživatelů
     */
    function prihlasPrepisHromadne($uids) {
        $pKolize = dbOneCol('
      SELECT GROUP_CONCAT(id_akce)
      FROM akce_seznam
      WHERE rok=' . ROK . '
      AND NOT ( ' . $this->a['konec'] . ' <= zacatek OR konec <= ' . $this->a['zacatek'] . ' )
    ');
        dbQuery("DELETE FROM akce_prihlaseni WHERE id_akce IN($pKolize) AND id_uzivatele IN(" . implode(',', $uids) . ')');
        dbQuery('INSERT INTO akce_prihlaseni(id_akce,id_uzivatele) VALUES (' . $this->id() . ',' . implode('),(' . $this->id() . ',', $uids) . ')');
    }

    /**
     * Přihlásí uživatele jako náhradníka (watchlist)
     */
    function prihlasNahradnika(Uzivatel $u) {
        // Aktivita musí mít přihlašování náhradníků povoleno
        if (!$this->prihlasovatelnaNahradnikum()) throw new Exception('Na aktivitu se nelze přihlašovat jako náhradník.');
        // Uživatel nesmí být přihlášen na aktivitu nebo jako náhradník
        if ($this->prihlasen($u) || $u->prihlasenJakoNahradnikNa($this)) return;
        // Uživatel nesmí mít ve stejný slot jinou přihlášenou aktivitu
        if (!$u->maVolno($this->zacatek(), $this->konec())) throw new Chyba(hlaska('kolizeAktivit'));
        // Uživatel musí být přihlášen na GameCon
        if (!$u->gcPrihlasen()) throw new Exception('Nemáš aktivní přihlášku na GameCon.');

        // Uložení přihlášení do DB
        dbQuery("INSERT INTO akce_prihlaseni_spec SET id_uzivatele=$0, id_akce=$1, id_stavu_prihlaseni=$2", [$u->id(), $this->id(), self::NAHRADNIK]);
        dbQuery("INSERT INTO akce_prihlaseni_log SET id_uzivatele=$0, id_akce=$1, typ='prihlaseni_watchlist'", [$u->id(), $this->id()]);
        $this->refresh();
    }

    /**
     * Přihlásí na aktivitu vybrané uživatele jako tým vč. přihlášení na vybraná
     * navazující kola a úpravy počtu míst v týmu.
     * @param Uzivatel[] $uzivatele
     * @param string $nazevTymu
     * @param int $pocetMist požadovaný počet míst v týmu
     * @param self[] $dalsiKola - pořadí musí odpovídat návaznosti kol
     */
    function prihlasTym($uzivatele, $nazevTymu = null, $pocetMist = null, $dalsiKola = []) {
        if (!$this->tymova()) throw new Exception('Nelze přihlásit tým na netýmovou aktivitu.');
        if (!$this->a['zamcel']) throw new Exception('Pro přihlášení týmu musí být aktivita zamčená.');
        if (!$this->jsouDalsiKola($dalsiKola)) throw new Exception('Nepovolený výběr dalších kol.');

        $lidr = Uzivatel::zId($this->a['zamcel']);
        $chybnyClen = null; // nastavíme v případě, že u daného člena týmu nastala při přihlášení chyba

        dbBegin();
        try {
            // přihlášení týmlídra na zvolená další kola (pokud jsou)
            // nutno jít od konce, jinak vazby na potomky můžou vyvolat chyby kvůli
            // duplicitním pokusům o přihlášení
            foreach (array_reverse($dalsiKola) as $kolo) {
                $kolo->prihlas($lidr, self::STAV);
            }

            // přihlášení členů týmu
            foreach ($uzivatele as $clen) {
                try {
                    $this->prihlas($clen, self::ZAMEK);
                } catch (Exception $e) {
                    $chybnyClen = $clen;
                    throw $e;
                }
            }

            // doplňující úpravy aktivity
            dbUpdate('akce_seznam', [
                'zamcel' => null,
                'zamcel_cas' => null,
                'team_nazev' => $nazevTymu ?: null,
                'kapacita' => $pocetMist ?: dbNoChange(),
            ], [
                'id_akce' => $this->id(),
            ]);

            $this->refresh();
        } catch (Exception $e) {
            dbRollback();
            if ($chybnyClen)
                throw new Chyba(hlaska('chybaClenaTymu', $chybnyClen->jmenoNick(), $chybnyClen->id(), $e->getMessage()));
            else
                throw $e;
        }
        dbCommit();

        // maily přihlášeným
        $mail = new GcMail(hlaskaMail('prihlaseniTeamMail',
            $lidr, $lidr->jmenoNick(), $this->nazev(), $this->denCas()
        ));
        $mail->predmet('Přihláška na ' . $this->nazev());
        foreach ($uzivatele as $clen) {
            $mail->adresat($clen->mail());
            $mail->odeslat();
        }
    }

    public function publikuj() {
        $this->zmenStav(Stav::PUBLIKOVANA);
    }

    private function zmenStav(int $novyStav) {
        if (!Stav::jeZnamy($novyStav)) {
            throw new \LogicException("Neznámý stav aktivity '$novyStav'");
        }
        dbQuery('UPDATE akce_seznam SET stav=$1 WHERE id_akce=$2', [$novyStav, $this->id()]);
    }

    /** Nastaví aktivitu jako "připravena pro aktivaci" */
    function priprav() {
        $this->zmenStav(Stav::PRIPRAVENA);
    }

    /** Zdali už aktivita začla a proběhla (rozhodný okamžik je vyjetí seznamů
     *  přihlášených na infopultu) */
    function probehnuta(): bool {
        return $this->a['stav'] == Stav::PROBEHNUTA;
    }

    public function bezpecneEditovatelna(): bool {
        return in_array($this->idStavu(), [Stav::NOVA, Stav::PUBLIKOVANA, Stav::PRIPRAVENA], true);
    }

    public function stav(): \Stav {
        if (is_numeric($this->stav)) {
            $this->prednactiN1([
                'atribut' => 'stav',
                'cil' => Stav::class,
            ]);
        }
        return $this->stav;
    }

    public function idStavu(): ?int {
        $idStavu = $this->a[AktivitaSqlSloupce::STAV];
        return $idStavu !== null
            ? (int)$idStavu
            : null;
    }

    /**
     * Vrací surový databázový řádek, nepoužívat (pouze pro debug a zpětnou kompatibilitu, postupně odstranit).
     */
    function rawDb(): array {
        return $this->a;
    }

    /** Aktualizuje stav aktivity podle databáze */
    function refresh() {
        $aktualni = self::zId($this->id());
        $this->a = $aktualni->a;
    }

    /** Vrátí aktivity, u kterých je tato aktivita jako jedno z dětí */
    function rodice() {
        return self::zWhere('WHERE a.dite rlike "(^|,)' . $this->id() . '(,|$)"');
    }

    /**
     * Smaže aktivitu z DB
     */
    function smaz() {
        dbBegin();
        try {
            foreach ($this->prihlaseni() as $u) {
                $this->odhlas($u, self::BEZ_POKUT | self::NEPOSILAT_MAILY);
            }
            $idInstance = $this->patriPod();
            $idNoveMaterskeAktivity = null;
            if ($idInstance) {
                $materskaProInstanciId = dbOneCol('SELECT id_instance FROM akce_instance WHERE id_hlavni_akce = $1', [$this->id()]);
                if ($materskaProInstanciId && (int)$materskaProInstanciId !== (int)$idInstance) {
                    throw new \RuntimeException(sprintf('Aktivita s ID %d tvrdí, že patří pod instanci %d, ale patří pod %d', $this->id(), $idInstance, $materskaProInstanciId));
                }
                $zbyde = (int)dbOneCol(
                    'SELECT COUNT(*) AS zbyde FROM akce_seznam WHERE id_akce != $1 AND patri_pod = $2',
                    [$this->id(), $idInstance]
                );
                if ($materskaProInstanciId) {
                    $idNoveMaterskeAktivity = dbOneCol(
                        'SELECT MIN(id_akce) AS idNoveMaterskeAktivity FROM akce_seznam WHERE id_akce != $1 AND patri_pod = $2',
                        [$this->id(), $materskaProInstanciId]
                    );
                }
                // nezbyde žádná, nebo jen jediná instance, zrušit instanci
                if ($zbyde <= 1) {
                    dbQuery('UPDATE akce_seznam SET patri_pod = NULL WHERE patri_pod = ' . $idInstance);
                    dbQuery('DELETE FROM akce_instance WHERE id_instance = ' . $idInstance);
                } else if ($idNoveMaterskeAktivity) {
                    dbQuery('UPDATE akce_instance SET id_hlavni_akce = $1 WHERE id_instance = $2', [$idNoveMaterskeAktivity, $idInstance]);
                }
            }

            dbQuery('DELETE FROM akce_prihlaseni_spec WHERE id_akce = $1 AND id_stavu_prihlaseni = $2', [$this->id(), self::NAHRADNIK]);
            dbQuery('DELETE FROM akce_organizatori WHERE id_akce = $1', [$this->id()]);
            dbQuery('DELETE FROM akce_seznam WHERE id_akce = $1', [$this->id()]); // posledni kvuli SQL cizim klicum a cascade

            if ($idNoveMaterskeAktivity) {
                // zrušená aktivita byla mateřskou => je potřeba uložit url a popisek do nové instance (až po smazání původní mateřské aktivity kvůli unikátnímu klíči)
                dbQuery(
                    'UPDATE akce_seznam SET url_akce = $1, popis = $2, vybaveni = $3 WHERE id_akce = $4',
                    [$this->a['url_akce'], $this->a['popis'], $this->a['vybaveni'], $idNoveMaterskeAktivity]
                );
            }

            dbCommit();
        } catch (Exception $e) {
            dbRollback();
            throw $e;
        }

        // invalidace aktuální instance
        $this->a = null;
    }

    /**
     * Vrátí iterátor tagů
     * @return string[]
     */
    function tagy(): array {
        if ($this->a['tagy']) {
            return explode(',', $this->a['tagy']);
        }
        return [];
    }

    function nastavTagy(array $tagy) {
        // nastavit tagy aktivitám
        $idInstanci = $this->idInstanci();
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
SQL
            );
        }

        $this->otoc();
    }

    function nastavTagyPodleId(array $idTagu) {
        // nastavit tagy aktivitám
        foreach ($this->instance() as $aktivita) {
            dbQuery('DELETE FROM akce_sjednocene_tagy WHERE id_akce = $1', [$aktivita->id()]);
            if ($idTagu) {
                dbQuery(
                    'INSERT INTO akce_sjednocene_tagy(id_akce, id_tagu) SELECT $1, id FROM sjednocene_tagy WHERE id IN (' . dbQa($idTagu) . ')',
                    [$aktivita->id()]
                );
            }
        }

        $this->otoc();
    }

    function tym() {
        if ($this->tymova() && $this->prihlaseno() > 0 && !$this->a['zamcel']) {
            return new Tym($this, $this->a);
        }
        return null;
    }

    function tymMaxKapacita(): ?int {
        return (string)$this->a['team_max'] !== ''
            ? (int)$this->a['team_max']
            : null;
    }

    function tymMinKapacita(): ?int {
        return (string)$this->a['team_min'] !== ''
            ? (int)$this->a['team_min']
            : null;
    }

    /**
     * Je aktivita týmová?
     */
    function tymova(): bool {
        return (bool)$this->a['teamova'];
    }

    /**
     * @return DateTimeCz|null jestli a do kdy je týmová aktivita zamčená
     */
    function tymZamcenyDo() {
        if ($this->a['zamcel_cas']) {
            $dt = new DateTimeCz($this->a['zamcel_cas']);
            $dt->add('PT' . self::HAJENI . 'H');
            return $dt;
        } else {
            return null;
        }
    }

    function typ(): Typ {
        if (is_numeric($this->typ)) {
            $this->prednactiN1([
                'atribut' => 'typ',
                'cil' => Typ::class,
            ]);
        }
        return $this->typ;
    }

    function typId(): ?int {
        return (string)$this->a['typ'] !== ''
            ? (int)$this->a['typ']
            : null;
    }

    /**
     * Vrátí pole s přihlášenými účastníky
     * @return Uzivatel[]
     */
    function prihlaseni(): array {
        $u = substr($this->prihlaseniRaw(), 1, -1);
        $u = preg_replace('@(m|f)\d+@', '', $u);
        return Uzivatel::zIds($u);
    }

    /**
     * Uloží údaje o prezenci u této aktivity
     * @param $dorazili uživatelé, kteří se nakonec aktivity zúčastnili
     */
    function ulozPrezenci($dorazili) {
        $prezence = new AktivitaPrezence($this);
        $prezence->uloz($dorazili);
    }

    /**
     * @return string absolutní url k anotaci aktivity na webu
     */
    function url() {
        static $typy;
        if (!$typy) {
            $o = dbQuery('SELECT id_typu, url_typu_mn FROM akce_typy');
            while ($r = mysqli_fetch_row($o)) {
                $typy[$r[0]] = $r[1];
            }
        }
        return URL_WEBU . '/' . $typy[$this->a['typ']] . '#' . $this->a['url_akce'];
    }

    /**
     * @return string část url identifikující aktivitu (unikátní v dané linii)
     */
    function urlId() {
        return $this->a['url_akce'];
    }

    /** Vrátí, jestli aktivita bude aktivována v budoucnu, později než v další vlně */
    function vBudoucnu(): bool {
        return $this->a['stav'] == Stav::PUBLIKOVANA;
    }

    /** Vrátí, jestli aktivita bude aktivována v další vlně */
    function vDalsiVlne() {
        return $this->a['stav'] == Stav::PRIPRAVENA || !REG_AKTIVIT && $this->a['stav'] == Stav::AKTIVOVANA;
    }

    /** Vrátí typ volných míst na aktivitě */
    function volno() {
        $m = $this->prihlasenoMuzu();
        $f = $this->prihlasenoZen();
        $ku = $this->a['kapacita'];
        $km = $this->a['kapacita_m'];
        $kf = $this->a['kapacita_f'];
        if (($ku + $km + $kf) <= 0)
            return 'u'; //aktivita bez omezení
        if ($m + $f >= $ku + $km + $kf)
            return 'x'; //beznadějně plno
        if ($m >= $ku + $km)
            return 'f'; //muži zabrali všechna univerzální i mužská místa
        if ($f >= $ku + $kf)
            return 'm'; //LIKE WTF? (opak předchozího)
        //else
        return 'u'; //je volno a žádné pohlaví nevyžralo limit míst
    }

    public function getKapacitaUnisex(): int {
        return (int)$this->a['kapacita'];
    }

    public function getKapacitaMuzu(): int {
        return (int)$this->a['kapacita_m'];
    }

    public function getKapacitaZen(): int {
        return (int)$this->a['kapacita_f'];
    }

    /** Jestli volno pro daného uživatele (nebo aspoň pro někoho, pokud null) */
    function volnoPro(Uzivatel $u = null) {
        $v = $this->volno();
        if ($u)
            return $v == 'u' || $v == $u->pohlavi();
        else
            return $v != 'x';
    }

    /**
     * Jestli má uživatel aktivitu vidět (případně jestli má být vidět veřejně,
     * pokud $u == null).
     */
    function viditelnaPro(Uzivatel $u = null) {
        return (
            in_array($this->a['stav'], [Stav::AKTIVOVANA, Stav::PROBEHNUTA, Stav::PUBLIKOVANA, Stav::PRIPRAVENA]) // podle stavu je aktivita viditelná
            && !($this->a['typ'] == Typ::TECHNICKA && $this->a['stav'] == Stav::PROBEHNUTA) || // ale skrýt technické proběhnuté
            $u && $this->prihlasen($u) ||
            $u && $u->organizuje($this)
        );
    }

    /**
     * @return text s informací o extra vybavení pro tuto aktivitu
     */
    function vybaveni() {
        if ($this->a['patri_pod'])
            return dbOneCol('SELECT MAX(vybaveni) FROM akce_seznam WHERE patri_pod = $1',
                [$this->a['patri_pod']]);
        else
            return dbOneCol('SELECT vybaveni FROM akce_seznam WHERE id_akce = $1', [$this->id()]);
    }

    /**
     * Vrátí formulář pro výběr teamu na aktivitu. Pokud není zadán uživatel,
     * vrací nějakou false ekvivalentní hodnotu.
     * @todo ideálně převést na nějaké statické metody týmu nebo samostatnou třídu
     */
    function vyberTeamu(Uzivatel $u = null) {
        if (!$u || $this->a['zamcel'] != $u->id() || !$this->prihlasovatelna()) return null;

        $t = new XTemplate(__DIR__ . '/tym-formular.xtpl');

        // obecné proměnné šablony
        $zbyva = strtotime($this->a['zamcel_cas']) + self::HAJENI * 60 * 60 - time();
        $t->assign([
            'zbyva' => floor($zbyva / 3600) . ' hodin ' . floor($zbyva % 3600 / 60) . ' minut',
            'postname' => self::TEAMKLIC,
            'prihlasenyUzivatelId' => $u->id(),
            'aktivitaId' => $this->id(),
            'cssUrlAutocomplete' => URL_WEBU . '/soubory/blackarrow/_spolecne/auto-complete.css',
            'jsUrlAutocomplete' => URL_WEBU . '/soubory/blackarrow/_spolecne/auto-complete.min.js',
            'jsUrl' => URL_WEBU . '/soubory/blackarrow/tym-formular/tym-formular.js',
        ]);

        // výběr instancí, pokud to aktivita vyžaduje
        if ($this->a['dite']) {

            // načtení "kol" (podle hloubky zanoření v grafu instancí)
            $urovne[] = [$this];
            do {
                $dalsi = [];
                foreach (end($urovne) as $a) {
                    if ($a->a['dite'])
                        $dalsi = array_merge($dalsi, explode(',', $a->a['dite']));
                }
                if ($dalsi)
                    $urovne[] = self::zIds($dalsi);
            } while ($dalsi);
            unset($urovne[0]); // aktuální aktivitu už má přihlášenu - ignorovat

            // vybírací formy dle "kol"
            foreach ($urovne as $i => $uroven) {
                $t->assign('postnameKolo', self::KOLA . '[' . $i . ']');
                foreach ($uroven as $varianta) {
                    $t->assign([
                        'koloId' => $varianta->id(),
                        'nazev' => $varianta->nazev() . ': ' . $varianta->denCas(),
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
            if ($i >= $this->a['team_min'] - 1) // -1 za týmlídra
                $t->parse('formular.misto.odebrat');
            $t->parse('formular.misto');
        }

        // název (povinný pro DrD)
        if ($this->a['typ'] == Typ::DRD) $t->parse('formular.nazevPovinny');
        else                              $t->parse('formular.nazevVolitelny');

        // výpis celého formuláře
        $t->parse('formular');
        return $t->text('formular');
    }

    /**
     * Zpracuje data formuláře pro výběr teamu a vrátí případné chyby jako json.
     * Ukončuje skript.
     */
    static function vyberTeamuZpracuj(Uzivatel $leader = null) {
        if (!$leader || !post(self::TEAMKLIC)) return;

        $a = Aktivita::zId(post(self::TEAMKLIC . 'Aktivita'));
        if ($leader->id() != $a->a['zamcel']) throw new Exception('Nejsi teamleader.');

        // načtení zvolených parametrů z formuláře (spoluhráči, kola, ...)
        $up = post(self::TEAMKLIC);
        $zamceno = 0;
        foreach ($up as $i => $uid) {
            if ($uid == -1 || !$uid)
                unset($up[$i]);
            if ($uid == -1)
                $zamceno++;
        }
        $clenove = Uzivatel::zIds($up);
        $novaKapacita = $a->kapacita() - $zamceno;
        $nazev = post(self::TEAMKLIC . 'Nazev');
        $dalsiKola = array_values(array_map(function ($id) { // array_map kvůli nutnosti zachovat pořadí
            return self::zId($id);
        }, post(self::KOLA) ?: []));

        // přihlášení týmu
        try {
            $a->prihlasTym($clenove, $nazev, $novaKapacita, $dalsiKola);
            $chyby = [];
        } catch (Chyba $ch) {
            $chyby = [$ch->getMessage()];
        }

        echo json_encode(['chyby' => $chyby]);
        exit();
    }

    /**
     * Má aktivita vyplněnou prezenci?
     * (aktivity s 0 lidmi jsou považovány za nevyplněné vždycky)
     */
    function vyplnenaPrezence() {
        return 0 < dbOneCol('SELECT MAX(id_stavu_prihlaseni) FROM akce_prihlaseni WHERE id_akce = ' . $this->id());
    }

    /**
     * Vrátí DateTime objekt začátku aktivity
     * @return DateTimeCz|null
     */
    function zacatek(): ?DateTimeCz {
        if ($this->a['zacatek'] && is_string($this->a['zacatek'])) {
            $this->a['zacatek'] = new DateTimeCz($this->a['zacatek']);
        }
        return $this->a['zacatek'] ?: null;
    }

    /** Je aktivita už proběhlá resp. už uzavřená pro změny? */
    function zamcena(): bool {
        return $this->a['stav'] == Stav::PROBEHNUTA;
    }

    /** Zamče aktivitu pro další změny (k použití před jejím začátkem) */
    function zamci() {
        dbQuery('UPDATE akce_seznam SET stav = 2 WHERE id_akce = ' . $this->id());
        // TODO invalidate $this
    }

    public function pridejDite(int $idDitete) {
        $detiIds = $this->detiIds();
        if (in_array($idDitete, $detiIds, true)) {
            return;
        }
        $detiIds[] = $idDitete;
        $detiIds = array_unique($detiIds);
        $detiString = implode(',', $detiIds);
        $this->a['dite'] = $detiString;
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
     * @todo filtr dle orga
     */
    static function zFiltru($filtr, array $razeni = []): array {
        // sestavení filtrů
        $wheres = [];
        if (!empty($filtr['rok']))
            $wheres[] = 'a.rok = ' . (int)$filtr['rok'];
        if (!empty($filtr['typ']))
            $wheres[] = 'a.typ = ' . (int)$filtr['typ'];
        if (!empty($filtr['organizator']))
            $wheres[] = 'a.id_akce IN (SELECT id_akce FROM akce_organizatori WHERE id_uzivatele = ' . (int)$filtr['organizator'] . ')';
        if (!empty($filtr['jenViditelne']))
            $wheres[] = 'a.stav IN(1,2,4,5) AND NOT (a.typ = 10 AND a.stav = 2)';
        if (!empty($filtr['od']))
            $wheres[] = dbQv($filtr['od']) . ' <= a.zacatek';
        if (!empty($filtr['do']))
            $wheres[] = 'a.zacatek <= ' . dbQv($filtr['do']);
        if (!empty($filtr['bezDalsichKol']))
            $wheres[] = 'NOT (a.typ IN (' . Typ::DRD . ',' . Typ::LKD . ') AND cena = 0)';
        $where = implode(' AND ', $wheres);

        // sestavení řazení
        $order = null;
        $phpRazeni = [];
        $orderBy = [];
        foreach ($razeni as $sloupec) {
            $directionRegExp = '~\s(?<direction>ASC|DESC)\s*$~i';
            preg_match($directionRegExp, $sloupec, $matches);
            $direction = 'ASC';
            if (!empty($matches['direction'])) {
                $direction = strtoupper(trim($matches['direction']));
                $sloupec = preg_replace($directionRegExp, '', $sloupec);
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
        $aktivity = (array)self::zWhere('WHERE ' . $where, null, $order); // přetypování nutné kvůli správné funkci unsetu
        if (!empty($filtr['jenVolne'])) {
            foreach ($aktivity as $id => $a) {
                if ($a->volno() === 'x') unset($aktivity[$id]);
            }
        }

        // řazení v php
        if (!empty($phpRazeni['organizatori'])) { // prozatím podporujeme jen řazení dle orga
            if ($phpRazeni['organizatori'] === 'DESC') {
                usort(
                    $aktivity,
                    static function (Aktivita $a, Aktivita $b) {
                        $jmenoA = $a->organizatori() ? current($a->organizatori())->jmenoNick() : '';
                        $jmenoB = $b->organizatori() ? current($b->organizatori())->jmenoNick() : '';
                        return strcmp($jmenoB, $jmenoA);
                    }
                );
            } else {
                usort(
                    $aktivity,
                    static function (Aktivita $a, Aktivita $b) {
                        $jmenoA = $a->organizatori() ? current($a->organizatori())->jmenoNick() : '';
                        $jmenoB = $b->organizatori() ? current($b->organizatori())->jmenoNick() : '';
                        return strcmp($jmenoA, $jmenoB);
                    }
                );
            }
        }

        return $aktivity;
    }

    /**
     * Pokusí se vyčíst aktivitu z dodaného ID.
     * @return self|null
     */
    static function zId($id, bool $pouzijCache = false): ?Aktivita {
        $id = (int)$id;
        if (!$id) {
            return null;
        }
        if ($pouzijCache) {
            $aktivita = self::$cache[$id] ?? null;
            if ($aktivita) {
                return $aktivita;
            }
        }
        $aktivita = current(self::zWhere('WHERE a.id_akce=' . $id));
        if (!$aktivita) {
            return null;
        }
        if ($pouzijCache) {
            self::$cache[$id] = $aktivita;
        }
        return $aktivita;
    }

    /**
     * Načte aktivitu z pole ID nebo řetězce odděleného čárkami
     * @return Aktivita[]
     */
    static function zIds($ids): array {
        if (empty($ids)) {
            return [];
        }
        if (!is_array($ids)) {
            $ids = explode(',', $ids);
        }
        $ids = array_map('trim', $ids);
        $ids = array_filter($ids, static function ($id) {
            return $id !== '';
        });
        if (empty($ids)) {
            return [];
        }
        return self::zWhere('WHERE a.id_akce IN(' . dbQa($ids) . ')');
    }

    /**
     * Vrátí všechny aktivity, které vede daný uživatel
     */
    static function zOrganizatora(Uzivatel $u) {
        // join hack na akt. uživatele
        return self::zWhere('JOIN akce_organizatori ao ON (ao.id_akce = a.id_akce AND ao.id_uzivatele = ' . $u->id() . ') WHERE a.rok = ' . ROK);
    }

    /**
     * Vrátí pole aktivit které se letos potenciálně zobrazí v programu
     */
    static function zProgramu($order) {
        return self::zWhere(
            'WHERE a.rok = $1 AND a.zacatek AND ( a.stav IN(1,2,3,4,5) OR a.typ = 10 )',
            [ROK],
            'ORDER BY DAY(zacatek), ' . dbQi($order) . ', HOUR(zacatek), nazev_akce'
        );
    }

    /**
     * Vrátí aktivity z rozmezí (aktuálně s začátkem v rozmezí konkrétně)
     * @return Aktivita[]
     * @todo možno přidat flag 'celé v rozmezí'
     */
    static function zRozmezi(DateTimeCz $od, DateTimeCz $do, $flags = 0, $razeni = []): array {
        $aktivity = self::zFiltru(
            [
                'jenViditelne' => (bool)($flags & self::VEREJNE),
                'od' => $od->formatDb(),
                'do' => $do->formatDb(),
            ],
            $razeni
        );
        if ($flags & self::JEN_VOLNE)
            foreach ($aktivity as $i => $a)
                if ($a->volno() == 'x') unset($aktivity[$i]);
        return $aktivity;
    }

    /**
     * @param DateTimeCz $od
     * @param DateTimeCz $do
     * @param int $flags
     * @param array|string[] $razeni
     * @return array|DateTimeCz[]
     */
    static function zacatkyAktivit(DateTimeCz $od, DateTimeCz $do, $flags = 0, $razeni = []): array {
        $aktivity = self::zRozmezi($od, $do, $flags, $razeni);
        /** @var DateTime[][] $zacatky */
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
     * @param string $where obsah where klauzule (bez úvodního klíč. slova WHERE)
     * @param $args volitelné pole argumentů pro dbQueryS()
     * @param $order volitelně celá klauzule ORDER BY včetně klíč. slova
     * @return Aktivita[]
     * @todo třída která obstará reálný iterátor, nejenom obalení pole (nevýhoda
     *  pole je nezměněná nutnost čekat, než se celá odpověď načte a přesype do
     *  paměti
     */
    protected static function zWhere($where, $args = null, $order = null): array {
        $o = dbQueryS("
      SELECT t3.*,
             (SELECT GROUP_CONCAT(sjednocene_tagy.nazev ORDER BY kst.poradi, sjednocene_tagy.nazev)
FROM sjednocene_tagy
         JOIN akce_sjednocene_tagy ON akce_sjednocene_tagy.id_tagu = sjednocene_tagy.id
         JOIN kategorie_sjednocenych_tagu kst on sjednocene_tagy.id_kategorie_tagu = kst.id
             WHERE akce_sjednocene_tagy.id_akce = t3.id_akce
             ) AS tagy
      FROM (
        SELECT t2.*, CONCAT(',',GROUP_CONCAT(p.id_uzivatele,u.pohlavi,p.id_stavu_prihlaseni),',') AS prihlaseni,
               IF(t2.patri_pod, (SELECT MAX(url_akce) FROM akce_seznam WHERE patri_pod = t2.patri_pod), t2.url_akce) as url_temp
        FROM (
          SELECT a.*, al.poradi
          FROM akce_seznam a
          LEFT JOIN akce_lokace al ON (al.id_lokace = a.lokace)
          $where
        ) as t2
        LEFT JOIN akce_prihlaseni p ON (p.id_akce = t2.id_akce)
        LEFT JOIN uzivatele_hodnoty u ON (u.id_uzivatele = p.id_uzivatele)
        GROUP BY t2.id_akce
      ) as t3
      $order
    ", $args);

        $kolekce = []; // pomocný sdílený seznam aktivit pro přednačítání

        while ($r = mysqli_fetch_assoc($o)) {
            $r['url_akce'] = $r['url_temp'];
            $aktivita = new self($r);
            $aktivita->typ = $r['typ'];
            $aktivita->lokace = $r['lokace'];
            $aktivita->stav = $r['stav'];

            $aktivita->kolekce = &$kolekce;
            $aktivita->kolekce[$r['id_akce']] = $aktivita;
        }

        return array_values($kolekce);
    }

    public static function hodinaNejblizsiAktivity(DateTimeInterface $po = null) {
        dbQuery('
        SELECT *
        FROM akce_seznam
        WHERE CASE WHEN ? THEN zacatek > ? ELSE TRUE END
    ');
    }

    public static function aktivujVsePripravene(int $rok) {
        dbQuery('UPDATE akce_seznam SET stav=$1 WHERE stav=$2 AND rok=$3', [Stav::AKTIVOVANA, Stav::PRIPRAVENA, $rok]);
    }

    public static function idExistujiciInstancePodleUrl(string $url, int $rok, int $typId): ?int {
        $idInstance = dbOneCol(<<<SQL
SELECT akce_seznam.patri_pod
FROM akce_seznam
WHERE akce_seznam.url_akce = $1 AND akce_seznam.rok = $2 AND akce_seznam.typ = $3 AND akce_seznam.patri_pod IS NOT NULL
LIMIT 1
SQL
            , [$url, $rok, $typId]
        );
        return $idInstance
            ? (int)$idInstance
            : null;
    }

    public static function idMozneHlavniAktivityPodleUrl(string $url, int $rok, int $typId): ?int {
        $idHlavniAktivity = dbOneCol(<<<SQL
SELECT MIN(akce_seznam.id_akce)
FROM akce_seznam
WHERE akce_seznam.url_akce = $1 AND akce_seznam.rok = $2 AND akce_seznam.typ = $3
SQL
            , [$url, $rok, $typId]
        );
        return $idHlavniAktivity
            ? (int)$idHlavniAktivity
            : null;
    }

    public static function moznaHlavniAktivitaPodleUrl(string $url, int $rok, int $typId): ?\Aktivita {
        $idHlavniAktivity = static::idMozneHlavniAktivityPodleUrl($url, $rok, $typId);
        if (!$idHlavniAktivity) {
            return null;
        }
        return static::zId($idHlavniAktivity);
    }
}
