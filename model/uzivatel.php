<?php

use \Gamecon\Cas\DateTimeCz;

/**
 * Třída popisující uživatele a jeho vlastnosti
 * @todo načítání separátního (nepřihlášeného uživatele) např. pro účely schi-
 *   zofrenie v adminovi (nehrozí špatný přístup při nadměrném volání např. při
 *   práci s více uživateli někde jinde?)
 */
class Uzivatel
{

    /**
     * @return Uzivatel[]
     */
    public static function vsichni(): array {
        $ids = dbOneArray(<<<SQL
SELECT DISTINCT uzivatele_hodnoty.id_uzivatele
FROM uzivatele_hodnoty
SQL
        );
        return static::zIds($ids);
    }

    /**
     * @return Uzivatel[]
     */
    public static function poradateleAktivit(): array {
        $ids = dbOneArray(<<<SQL
SELECT DISTINCT uzivatele_hodnoty.id_uzivatele
FROM uzivatele_hodnoty
JOIN r_uzivatele_zidle ON uzivatele_hodnoty.id_uzivatele = r_uzivatele_zidle.id_uzivatele
JOIN r_prava_zidle ON r_uzivatele_zidle.id_zidle = r_prava_zidle.id_zidle
WHERE r_prava_zidle.id_prava = $1
SQL
            , [\Gamecon\Pravo::PORADANI_AKTIVIT]
        );
        return static::zIds($ids);
    }

    protected $aktivityJakoNahradnik; // pole s klíči id aktvit, kde je jako náhradník
    protected $u = [];
    protected $klic = '';
    protected $idZidli;         // pole s klíči id židlí uživatele
    protected $finance;
    protected $idPrav;
    protected $shop;

    public const FAKE = 0x01;  // modifikátor "fake uživatel"
    public const SYSTEM = 1;   // id uživatele reprezentujícího systém (např. "operaci provedl systém")

    /** Vytvoří uživatele z různých možných vstupů */
    public function __construct($uzivatel) {
        if (is_array($uzivatel)
            && array_keys_exist(['id_uzivatele', 'login_uzivatele', 'pohlavi',], $uzivatel)
        ) { //asi čteme vstup z databáze
            $this->u = $uzivatel;
        } /* //zvážit, možná neimplementovat
    if((int)$uzivatel!=0)
    {
    }
    */
        else {
            throw new Exception('Špatný vstup konstruktoru uživatele');
        }
    }

    public function jePoradatelAktivit(): bool {
        return \Gamecon\Pravo::obsahujePravoPoradatAktivity($this->dejIdPrav());
    }

    private function dejIdPrav(): array {
        if ($this->idPrav === null) {
            $this->idPrav = dbOneArray(<<<SQL
SELECT r_prava_zidle.id_prava
FROM r_uzivatele_zidle
JOIN r_prava_zidle ON r_uzivatele_zidle.id_zidle = r_prava_zidle.id_zidle
WHERE r_uzivatele_zidle.id_uzivatele = $1
SQL
                ,
                [$this->id()]
            );
        }
        return $this->idPrav;
    }

    public function jeVypravec(): bool {
        return \Gamecon\Zidle::obsahujiVypravece($this->dejIdZidli());
    }

    public function jeOrganizator(): bool {
        return \Gamecon\Zidle::obsahujiOrganizatora($this->dejIdZidli());
    }

    /**
     * @return string adresa uživatele ve formátu Město, Ulice ČP, PSČ, stát
     */
    public function adresa() {
        $adresa = $this->u['mesto_uzivatele'] . ', ' . $this->u['ulice_a_cp_uzivatele'] . ', ' . $this->u['psc_uzivatele'] . ', ' . $this->stat();
        return $adresa;
    }

    /**
     * Vrátí aboslutní adresu avataru včetně http. Pokud avatar neexistuje, vrací
     * default avatar. Pomocí adresy je docíleno, aby se při nezměně obrázku dalo
     * cacheovat.
     */
    public function avatar() {
        $soubor = WWW . '/soubory/systemove/avatary/' . $this->id() . '.jpg';
        if (is_file($soubor))
            return Nahled::zSouboru($soubor)->pasuj(null, 100);
        else
            return self::avatarDefault();
    }

    /**
     * Vrátí defaultní avatar
     */
    static function avatarDefault() {
        return URL_WEBU . '/soubory/systemove/avatary/default.png';
    }

    /**
     * Načte a uloží avatar uživatele poslaný pomoci POST. Pokud se obrázek ne-
     * poslal, nestane se nic a vrátí false.
     * @param string $name název post proměnné, ve které je obrázek, např. html input
     * <input type="file" name="obrazek"> má $name='obrazek'. U formu je potřeba
     * nastavit <form method="post" enctype="multipart/form-data"> enctype aby to
     * fungovalo
     * @return bool true pokud se obrázek nahrál a uložil, false jinak
     */
    public function avatarNactiPost($name) {
        try {
            $o = Obrazek::zSouboru($_FILES[$name]['tmp_name']);
        } catch (Exception $e) {
            return false; // nenačten obrázek => starý styl vracení false
        }
        $o->fitCrop(2048, 2048);
        $o->uloz(WWW . '/soubory/systemove/avatary/' . $this->id() . '.jpg');
        return true;
    }

    /** Smaže avatar uživatele. (jen uživatelská část webu) */
    public function avatarSmaz() {
        if (is_file('./soubory/systemove/avatary/' . $this->id() . '.jpg'))
            return unlink('./soubory/systemove/avatary/' . $this->id() . '.jpg');
        else
            return true; //obrázek není -> jakoby se smazal v pohodě
    }

    /**
     * Vrátí / nastaví číslo občanského průkazu.
     */
    public function cisloOp($op = null) {
        if ($op) {
            dbQuery('
        UPDATE uzivatele_hodnoty
        SET op=$1
        WHERE id_uzivatele=' . $this->u['id_uzivatele'],
                [Sifrovatko::zasifruj($op)]
            );
            return $op;
        }

        if ($this->u['op']) {
            return Sifrovatko::desifruj($this->u['op']);
        } else {
            return '';
        }
    }

    /**
     * Vrátí datum narození uživatele jako DateTime
     */
    public function datumNarozeni() {
        if ((int)$this->u['datum_narozeni']) //hack, neplatný formát je '0000-00-00'
            return new DateTimeCz($this->u['datum_narozeni']);
        else
            return new DateTimeCz('0001-01-01');
    }

    /**
     * Přidá uživateli židli (posadí uživatele na židli)
     */
    public function dejZidli(int $idZidle, int $posadil = null) {
        if ($this->maZidli($idZidle)) {
            return;
        }

        $novaPrava = dbOneArray('SELECT id_prava FROM r_prava_zidle WHERE id_zidle = $0', [$idZidle]);

        if ($this->maPravo(P_UNIKATNI_ZIDLE) && in_array(P_UNIKATNI_ZIDLE, $novaPrava)) {
            throw new Chyba('Uživatel už má jinou unikátní židli.');
        }

        foreach ($novaPrava as $pravo) {
            if (!$this->maPravo($pravo)) {
                $this->u['prava'][] = (int)$pravo;
            }
        }
        if ($this->klic) {
            $_SESSION[$this->klic]['prava'] = $this->u['prava'];
        }

        dbQuery(
            "INSERT IGNORE INTO r_uzivatele_zidle(id_uzivatele, id_zidle, posadil)
            VALUES ($1, $2, $3)",
            [$this->id(), $idZidle, $posadil]
        );
    }

    /** Vrátí profil uživatele pro DrD */
    public function drdProfil() {
        return $this->medailonek() ? $this->medailonek()->drd() : null;
    }

    /**
     * @return array pole "titulů" u organizátora DrD
     */
    public function drdTituly() {
        $tituly = ['Pán Jeskyně', 'vypravěč'];
        if ($this->maPravo(P_TITUL_ORG)) $tituly[] = 'organizátor GC';
        return $tituly;
    }

    /**
     * @return Finance finance daného uživatele
     */
    public function finance(): Finance {
        //pokud chceme finance poprvé, spočteme je a uložíme
        if (!$this->finance) {
            $this->finance = new Finance($this, $this->u['zustatek']);
        }
        return $this->finance;
    }

    /** Vrátí objekt Náhled s fotkou uživatele nebo null */
    public function fotka(): ?Nahled {
        $soubor = WWW . '/soubory/systemove/fotky/' . $this->id() . '.jpg';
        if (is_file($soubor)) {
            return Nahled::zSouboru($soubor);
        }
        return null;
    }

    /** Vrátí objekt Náhled s fotkou uživatele nebo výchozí fotku */
    public function fotkaAuto(): ?Nahled {
        $f = $this->fotka();
        if ($f) {
            return $f;
        }
        if ($this->pohlavi() === 'f') {
            return Nahled::zSouboru(WWW . '/soubory/styl/fotka-holka.jpg');
        }
        return Nahled::zSouboru(WWW . '/soubory/styl/fotka-kluk.jpg');
    }

    /**
     * Odhlásí uživatele z aktuálního ročníku GameConu, včetně všech předmětů a
     * aktivit.
     * @todo Vyřešit, jak naložit s nedostaveními se na aktivity a podobně (např.
     * při počítání zůstatků a různých jiných administrativních úkolech to toho
     * uživatele může přeskakovat či ignorovat, atd…). Jmenovité problémy:
     * - platby (pokud ho vynecháme při přepočtu zůstatku, přijde o love)
     * @todo Možná vyhodit výjimku, pokud už prošel infem, místo pouhého neudělání
     * nic?
     * @todo Při odhlášení z GC pokud jsou zakázané rušení nákupů může být též
     * problém (k zrušení dojde)
     */
    public function gcOdhlas(): bool {
        if (!$this->gcPrihlasen()) {
            return false;
        }
        if ($this->gcPritomen()) {
            throw new \Gamecon\Exceptions\CanNotKickOutUserFromGamecon(
                'Už jsi prošel infopultem, odhlášení není možné.'
            );
        }
        // smazání přihlášení na aktivity, na které je jen přihlášen (ne je už hrál, jako náhradník apod.)
        dbQuery('DELETE p.* FROM akce_prihlaseni p JOIN akce_seznam a
      WHERE a.rok=' . ROK . ' AND p.id_stavu_prihlaseni=0 AND p.id_uzivatele=' . $this->id());
        // zrušení nákupů
        dbQuery('DELETE FROM shop_nakupy WHERE rok=' . ROK . ' AND id_uzivatele=' . $this->id());
        // finální odebrání židle "registrován na GC"
        $this->vemZidli(Z_PRIHLASEN);
        // odeslání upozornění, pokud u nás má peníze
        if (mysqli_num_rows(dbQuery('SELECT 1 FROM platby WHERE rok=' . ROK . ' AND id_uzivatele=' . $this->id())) > 0) {
            (new GcMail)
                ->adresat('info@gamecon.cz')
                ->predmet('Uživatel ' . $this->jmenoNick() . ' se odhlásil ale platil')
                ->text(hlaskaMail('odhlasilPlatil', $this->jmenoNick(), $this->id(), ROK))
                ->odeslat();
        }
        return true;
    }

    /** „Odjede“ uživatele z GC */
    public function gcOdjed() {
        if (!$this->gcPritomen()) throw new Exception('Uživatel není přítomen na GC');
        $this->dejZidli(Z_ODJEL);
    }

    /** Opustil uživatel GC? */
    public function gcOdjel() {
        if (!$this->gcPritomen()) {
            return false; // ani nedorazil, nemohl odjet
        }
        return $this->maZidli(Z_ODJEL);
    }

    /** Je uživatel přihlášen na aktuální GC? */
    public function gcPrihlasen() {
        return $this->maPravo(ID_PRAVO_PRIHLASEN);
    }

    /** Příhlásí uživatele na GC */
    public function gcPrihlas() {
        if ($this->gcPrihlasen()) return;

        $this->dejZidli(Z_PRIHLASEN);
    }

    /** Prošel uživatel infopultem, dostal materiály a je nebo byl přítomen na aktuálím
     *  GC? */
    public function gcPritomen() {
        return $this->maPravo(ID_PRAVO_PRITOMEN);
    }

    /**
     * Nastaví nové heslo (pouze setter)
     */
    public function heslo($noveHeslo) {
        $novyHash = password_hash($noveHeslo, PASSWORD_DEFAULT);
        dbQuery('UPDATE uzivatele_hodnoty SET heslo_md5 = $1 WHERE id_uzivatele = $2', [$novyHash, $this->id()]);
    }

    /**
     * @return int[] roky, kdy byl přihlášen na GC
     */
    public function historiePrihlaseni() {
        if (!isset($this->historiePrihlaseni)) {
            $q = dbQuery('
        SELECT 2000 - (id_zidle DIV 100) AS "rok"
        FROM r_uzivatele_zidle
        WHERE id_zidle < 0 AND id_zidle MOD 100 = -1 AND id_uzivatele = $0
      ', [$this->id()]);
            $roky = mysqli_fetch_all($q);
            $roky = array_map(function ($e) {
                return (int)$e[0];
            }, $roky);
            $this->historiePrihlaseni = $roky;
        }
        return $this->historiePrihlaseni;
    }

    /** Jméno a příjmení uživatele v běžném (zákonném) tvaru */
    public function jmeno() {
        return trim($this->u['jmeno_uzivatele'] . ' ' . $this->u['prijmeni_uzivatele']);
    }

    /** Vrátí řetězec s jménem i nickemu uživatele jak se zobrazí např. u
     *  organizátorů aktivit */
    public function jmenoNick() {
        return self::jmenoNickZjisti($this->u);
    }

    public function nick(): string {
        return strpos($this->u['login_uzivatele'], '@') === false
            ? $this->u['login_uzivatele']
            : '';
    }

    public function nickNeboKrestniJmeno(): string {
        return $this->nick() ?: $this->krestniJmeno() ?: $this->jmeno();
    }

    public function krestniJmeno(): string {
        return trim($this->u['jmeno_uzivatele'] ?: '');
    }

    /**
     * Určuje jméno a nick uživatele z pole odpovídajícího strukturou databázovému
     * řádku z tabulky uzivatel_hodnoty. Pokud vyžadovaná pole chybí, zjistí
     * alespoň co se dá.
     * Slouží pro třídy, které si načítání uživatelské identifikace implementují
     * samy, aby nemusely zbytečně načítat celého uživatele. Pokud je to
     * výkonnostně ok, raději se tomu vyhnout a uživatele načíst.
     */
    static function jmenoNickZjisti($r) {
        if ($r['jmeno_uzivatele'] && $r['prijmeni_uzivatele']) {
            $celeJmeno = $r['jmeno_uzivatele'] . ' ' . $r['prijmeni_uzivatele'];
            $jeMail = strpos($r['login_uzivatele'], '@') !== false;
            if ($celeJmeno == $r['login_uzivatele'] || $jeMail)
                return $celeJmeno;
            else
                return $r['jmeno_uzivatele'] . ' „' . $r['login_uzivatele'] . '“ ' . $r['prijmeni_uzivatele'];
        } else {
            return $r['login_uzivatele'];
        }
    }

    /**
     * Vrátí koncovku "a" pro holky (resp. "" pro kluky)
     * @deprecated use \Uzivatel::koncovkaDlePohlavi instead
     */
    public function koncA(): string {
        return ($this->pohlavi() === 'f')
            ? 'a'
            : '';
    }

    /** Vrátí koncovku "a" pro holky (resp. "" pro kluky) */
    public function koncovkaDlePohlavi(string $koncovkaProZeny = 'a'): string {
        return ($this->pohlavi() === 'f')
            ? $koncovkaProZeny
            : '';
    }

    /** Vrátí primární mailovou adresu uživatele */
    public function mail() {
        return $this->u['email1_uzivatele'];
    }

    public function maPravo($pravo) {
        if (!isset($this->u['prava'])) {
            $this->nactiPrava();
        }
        return in_array($pravo, $this->u['prava']);
    }

    /**
     * @return bool jestli se uživatel v daném čase neúčastní / neorganizuje
     *  žádnou aktivitu (případně s výjimkou $ignorovanaAktivita)
     */
    public function maVolno(DateTimeInterface $od, DateTimeInterface $do, Aktivita $ignorovanaAktivita = null) {
        // právo na překrytí aktivit dává volno vždy automaticky
        // TODO zkontrolovat, jestli vlastníci práva dřív měli někdy paralelně i účast nebo jen organizovali a pokud jen organizovali, vyhodit test odsud a vložit do kontroly kdy se ukládá aktivita
        if ($this->maPravo(\Gamecon\Pravo::PREKRYVANI_AKTIVIT))
            return true;

        $ignorovanaAktivitaId = $ignorovanaAktivita ? $ignorovanaAktivita->id() : 0;

        // TODO převést dotaz na lazy loading aktivit uživatele a kontrolu lokálně bez použití databáze (viz $this->organizuje())
        $o = dbQuery('
      SELECT a.id_akce
      FROM (
        SELECT a.id_akce, a.zacatek, a.konec
        FROM akce_prihlaseni p
        JOIN akce_seznam a ON a.id_akce = p.id_akce
        WHERE p.id_uzivatele = $0 AND a.rok = $4
        UNION
        SELECT a.id_akce, a.zacatek, a.konec
        FROM akce_seznam a
        JOIN akce_organizatori ao ON ao.id_akce = a.id_akce
        WHERE ao.id_uzivatele = $0 AND a.rok = $4
      ) a
      WHERE
        NOT (zacatek >= $2 OR konec <= $1) AND -- zacne az pak nebo skonci pred
        id_akce != $3
    ', [
            $this->id(), $od, $do, $ignorovanaAktivitaId, ROK,
        ]);

        return dbNumRows($o) == 0;
    }

    /**
     * Sedí uživatel na dané židli?
     * NEslouží k čekování vlastností uživatele, které obecně řeší práva resp.
     * Uzivatel::maPravo(), skutečně výhradně k správě židlí jako takových.
     * @todo při načítání práv udělat pole místo načítání z DB
     */
    public function maZidli($zidle): bool {
        $idZidle = (int)$zidle;
        if (!$idZidle) {
            return false;
        }
        return in_array($idZidle, $this->dejIdZidli(), true);
    }

    /**
     * @return int[]
     */
    public function dejIdZidli(): array {
        if (!isset($this->idZidli)) {
            $zidle = dbOneArray('SELECT id_zidle FROM r_uzivatele_zidle WHERE id_uzivatele = ' . $this->id());
            $this->idZidli = array_map(static function ($idZidle) {
                return (int)$idZidle;
            }, $zidle);
        }
        return $this->idZidli;
    }

    protected function medailonek() {
        if (!isset($this->medailonek)) {
            $this->medailonek[] = Medailonek::zId($this->id()); // pole kvůli korektní detekci null
        }
        return $this->medailonek[0];
    }

    /**
     * Jestli je jeho mail mrtvý
     * @todo pokud bude výkonově ok, možno zrefaktorovat na třídu mail která bude
     * mít tento atribut
     */
    public function mrtvyMail() {
        return $this->u['mrtvy_mail'];
    }

    /**
     * Ručně načte práva - neoptimalizovaná varianta, přijatelné pouze pro prasečí
     * řešení, kde si to můžeme dovolit (=reporty)
     */
    public function nactiPrava() {
        if (!isset($this->u['prava'])) {
            //načtení uživatelských práv
            $p = dbQuery('SELECT id_prava FROM r_uzivatele_zidle uz
        LEFT JOIN r_prava_zidle pz USING(id_zidle)
        WHERE uz.id_uzivatele=' . $this->id());
            $prava = []; //inicializace nutná, aby nepadala výjimka pro uživatele bez práv
            while ($r = mysqli_fetch_assoc($p))
                $prava[] = (int)$r['id_prava'];
            $this->u['prava'] = $prava;
        }
    }

    /** Vrátí přezdívku (nickname) uživatele */
    public function login(): string {
        return $this->u['login_uzivatele'];
    }

    /** Odhlásí aktuálně přihlášeného uživatele, pokud není přihlášen, nic
     * @param bool $back rovnou otočit na referrer?
     */
    static function odhlas($back = false) {
        self::odhlasProTed();
        if (isset($_COOKIE['gcTrvalePrihlaseni'])) {
            setcookie('gcTrvalePrihlaseni', '', 0, '/');
        }
        if ($back) {
            back();
        }
    }

    /**
     * Odhlásí aktuálně přihlášeného uživatele
     */
    static function odhlasProTed() {
        if (!session_id()) {
            session_start();
        }
        session_destroy();
    }

    /** Odpojí od session uživatele na indexu $klic */
    static function odhlasKlic($klic) {
        if (!session_id()) {
            session_start();
        }
        unset($_SESSION[$klic]);
    }

    /**
     * Odebere uživatele z příjemců pravidelných mail(er)ů
     */
    public function odhlasZMaileru() {
        $id = $this->id();
        dbQueryS('UPDATE uzivatele_hodnoty SET nechce_maily = NOW() WHERE id_uzivatele = $1', [$id]);
    }

    /**
     * @return bool Jestli uživatel organizuje danou aktivitu nebo ne.
     */
    public function organizuje(Aktivita $a) {
        if (!isset($this->organizovaneAktivityIds)) {
            $this->organizovaneAktivityIds = dbOneIndex('
        SELECT a.id_akce
        FROM akce_organizatori ao
        JOIN akce_seznam a ON a.id_akce = ao.id_akce AND a.rok = $2
        WHERE ao.id_uzivatele = $1
      ', [$this->id(), ROK]);
        }
        return isset($this->organizovaneAktivityIds[$a->id()]);
    }

    /** Vrátí medailonek vypravěče */
    public function oSobe() {
        return $this->medailonek() ? $this->medailonek()->oSobe() : null;
    }

    /**
     * Otočí (znovunačte, přihlásí a odhlásí, ...) uživatele
     */
    public function otoc() {
        if (PHP_SAPI === 'cli') {
            $this->u = self::zId($this->id())->u;
            return;
        }

        if (!$this->klic) {
            throw new Exception('Neznámý klíč uživatele v session');
        }
        $id = $this->id();
        $klic = $this->klic;
        //máme obnovit starou proměnnou pro id uživatele (otáčíme aktuálně přihlášeného uživatele)?
        $sesObnovit = (isset($_SESSION['id_uzivatele']) && $_SESSION['id_uzivatele'] == $this->id());
        if ($klic === 'uzivatel') {//pokud je klíč default, zničíme celou session
            self::odhlasProTed(); // ponech případnou cookie pro trvalé přihášení
        } else { //pokud je speciální, pouze přemažeme položku v session
            self::odhlasKlic($klic);
        }
        $u = Uzivatel::prihlasId($id, $klic);
        $this->u = $u->u;
        if ($sesObnovit) {
            $_SESSION['id_uzivatele'] = $this->id();
        }
    }

    /**
     * Vrátí timestamp začátku posledního bloku kdy uživatel má aktivitu
     */
    public function posledniBlok() {
        $cas = dbOneCol('
      SELECT MAX(a.zacatek)
      FROM akce_seznam a
      JOIN akce_prihlaseni p USING(id_akce)
      WHERE p.id_uzivatele = ' . $this->id() . ' AND a.rok = ' . ROK . '
    ');
        return $cas;
    }

    /** Vrátí / nastaví poznámku uživatele */
    public function poznamka($poznamka = null) {
        if (isset($poznamka)) {
            dbQueryS('UPDATE uzivatele_hodnoty SET poznamka = $1 WHERE id_uzivatele = $2', [$poznamka, $this->id()]);
            $this->otoc();
        } else {
            return $this->u['poznamka'];
        }
    }

    /** Vrátí formátovanou (html) poznámku uživatele **/
    public function poznamkaHtml() {
        return markdown($this->u['poznamka']);
    }

    /**
     * Přihlásí uživatele s loginem $login k stránce
     * @param string $klic klíč do $_SESSION kde poneseme hodnoty uživatele
     * @param $login login nebo primární e-mail uživatele
     * @param $heslo heslo uživatele
     * @return mixed objekt s uživatelem nebo null
     */
    public static function prihlas($login, $heslo, $klic = 'uzivatel') {
        if (!$login || !$heslo) {
            return null;
        }

        $u = dbOneLineS('
      SELECT * FROM uzivatele_hodnoty
      WHERE login_uzivatele = $0 OR email1_uzivatele = $0
      ORDER BY email1_uzivatele = $0 DESC -- e-mail má prioritu
      LIMIT 1
    ', [$login]);
        if (!$u) return null;
        // master password hack pro vývojovou větev
        $jeMaster = defined('UNIVERZALNI_HESLO') && $heslo == UNIVERZALNI_HESLO;
        // kontrola hesla
        if (!(password_verify($heslo, $u['heslo_md5']) || md5($heslo) === $u['heslo_md5'] || $jeMaster)) return null;
        // kontrola zastaralých algoritmů hesel a případná aktualizace hashe
        $jeMd5 = strlen($u['heslo_md5']) == 32 && preg_match('@^[0-9a-f]+$@', $u['heslo_md5']);
        if ((password_needs_rehash($u['heslo_md5'], PASSWORD_DEFAULT) || $jeMd5) && !$jeMaster) {
            $novyHash = password_hash($heslo, PASSWORD_DEFAULT);
            $u['heslo_md5'] = $novyHash;
            dbQuery('UPDATE uzivatele_hodnoty SET heslo_md5 = $0 WHERE id_uzivatele = $1', [$novyHash, $u['id_uzivatele']]);
        }
        // přihlášení uživatele
        // TODO refactorovat do jedné fce volané z dílčích prihlas* metod
        $id = $u['id_uzivatele'];
        if (!session_id() && PHP_SAPI != 'cli') session_start();
        $_SESSION[$klic] = $u;
        $_SESSION[$klic]['id_uzivatele'] = (int)$u['id_uzivatele'];
        // načtení uživatelských práv
        $p = dbQuery('SELECT id_prava FROM r_uzivatele_zidle uz
      LEFT JOIN r_prava_zidle pz USING(id_zidle)
      WHERE uz.id_uzivatele=' . $id);
        $prava = []; // inicializace nutná, aby nepadala výjimka pro uživatele bez práv
        while ($r = mysqli_fetch_assoc($p))
            $prava[] = (int)$r['id_prava'];
        $_SESSION[$klic]['prava'] = $prava;
        return new Uzivatel($_SESSION[$klic]);
    }

    /**
     * Vytvoří v session na indexu $klic dalšího uživatele pro práci
     * @return null|Uzivatel nebo null
     */
    public static function prihlasId($id, $klic = 'uzivatel'): ?Uzivatel {
        $u = dbOneLineS('SELECT * FROM uzivatele_hodnoty WHERE id_uzivatele=$0',
            [$id]);
        if ($u) {
            if (!session_id())
                session_start();
            $_SESSION[$klic] = $u;
            $_SESSION[$klic]['id_uzivatele'] = (int)$u['id_uzivatele'];
            //načtení uživatelských práv
            $p = dbQuery('SELECT id_prava FROM r_uzivatele_zidle uz
        LEFT JOIN r_prava_zidle pz USING(id_zidle)
        WHERE uz.id_uzivatele=' . $id);
            $prava = []; //inicializace nutná, aby nepadala výjimka pro uživatele bez práv
            while ($r = mysqli_fetch_assoc($p))
                $prava[] = (int)$r['id_prava'];
            $_SESSION[$klic]['prava'] = $prava;
            $u = new Uzivatel($_SESSION[$klic]);
            $u->klic = $klic;
            return $u;
        } else
            return null;
    }

    /** Alias prihlas() pro trvalé přihlášení */
    public static function prihlasTrvale($login, $heslo, $klic = 'uzivatel') {
        $u = Uzivatel::prihlas($login, $heslo, $klic);
        if ($u) {
            dbQuery('
        UPDATE uzivatele_hodnoty
        SET random="' . ($rand = randHex(20)) . '"
        WHERE id_uzivatele=' . $u->id());
            setcookie('gcTrvalePrihlaseni', $rand, time() + 3600 * 24 * 365, '/');
        }
        return $u;
    }

    /**
     * @return bool true, pokud je uživatel přihlášen jako náhradník na aktivitu (ve watchlistu).
     */
    public function prihlasenJakoNahradnikNa(Aktivita $a) {
        if (!isset($this->aktivityJakoNahradnik)) {
            $this->aktivityJakoNahradnik = dbOneIndex("
        SELECT id_akce
        FROM akce_prihlaseni_spec
        WHERE id_uzivatele = $0 AND id_stavu_prihlaseni = $1
      ", [$this->id(), Aktivita::NAHRADNIK]);
        }
        return isset($this->aktivityJakoNahradnik[$a->id()]);
    }

    /**
     * Vrátí timestamp prvního bloku kdy uživatel má aktivitu
     */
    public function prvniBlok() {
        $cas = dbOneCol('
      SELECT MIN(a.zacatek)
      FROM akce_seznam a
      JOIN akce_prihlaseni p USING(id_akce)
      WHERE p.id_uzivatele = ' . $this->id() . ' AND a.rok = ' . ROK . '
    ');
        return $cas;
    }

    /**
     * Zaregistruje uživatele podle asoc.pole $tab, které by mělo odpovídat
     * struktuře tabulky uzivatele_hodnoty.
     *
     * Extra položky: heslo a heslo_kontrola (metoda si je sama převede na hash).
     *
     * @return int id nově vytvořeného uživatele
     */
    static function registruj($tab) {
        return self::registrujUprav($tab);
    }

    /**
     * Zregistruje nového uživatele nebo upraví stávajícího $u, pokud je zadán.
     */
    private static function registrujUprav($tab, $u = null) {
        $dbTab = $tab;
        $chyby = [];
        $preskocitChybejiciPole = (bool)$u;

        // opravy
        $dbTab = array_map(function ($e) {
            return preg_replace('/\s+/', ' ', trim($e));
        }, $dbTab);

        if (isset($dbTab['email1_uzivatele'])) {
            $dbTab['email1_uzivatele'] = mb_strtolower($dbTab['email1_uzivatele']);
        }

        // TODO fallback prázdná přezdívka -> mail?

        // validátory
        $validaceLoginu = function ($login) use ($u) {
            if (empty($login)) return 'vyber si prosím přezdívku';

            $u2 = Uzivatel::zNicku($login) ?? Uzivatel::zMailu($login);
            if ($u2 && !$u) {
                return 'přezdívka už je zabraná. Pokud je tvoje, přihlaš se nebo si resetuj heslo';
            }
            if ($u2 && $u && $u2->id() != $u->id()) {
                return 'přezdívka už je zabraná. Vyber si prosím jinou';
            }
        };

        $validaceMailu = function ($mail) use ($u) {
            if (!preg_match('/^[a-z0-9_\-\.]+@[a-z0-9_\-\.]+\.[a-z]+$/', $mail)) {
                return 'zadej prosím platný e-mail';
            }

            $u2 = Uzivatel::zNicku($mail) ?? Uzivatel::zMailu($mail);
            if ($u2 && !$u) {
                return 'e-mail už máš zaregistrovaný. Přihlaš se nebo si resetuj heslo';
            }
            if ($u2 && $u && $u2->id() != $u->id()) {
                return 'e-mail už je zabraný. Pokud je tvůj, resetuj si heslo';
            }
        };

        $validaceHesla = function ($heslo) use ($dbTab) {
            if (empty($heslo)) return 'vyplň prosím heslo';

            if (
                $heslo != ($dbTab['heslo'] ?? null) ||
                $heslo != ($dbTab['heslo_kontrola'] ?? null)
            ) {
                return 'hesla se neshodují';
            }
        };

        $validace = [
            'jmeno_uzivatele' => ['.+', 'jméno nesmí být prázdné'],
            'prijmeni_uzivatele' => ['.+', 'příjmení nesmí být prázdné'],
            'login_uzivatele' => $validaceLoginu,
            'email1_uzivatele' => $validaceMailu,
            'pohlavi' => ['^(m|f)$', 'vyber prosím pohlaví'],
            'ulice_a_cp_uzivatele' => ['.+ [\d\/a-z]+$', 'vyplň prosím ulici, např. Česká 27'],
            'mesto_uzivatele' => ['.+', 'vyplň prosím město'],
            'psc_uzivatele' => ['^[\d ]+$', 'vyplň prosím PSČ, např. 602 00'],
            'stat_uzivatele' => ['^(1|2|-1)$', 'vyber prosím stát'],
            'telefon_uzivatele' => ['^[\d \+]+$', 'vyplň prosím telefon, např. +420 789 123 456'],
            'datum_narozeni' => ['\d+', 'vyber prosím datum narození'], // TODO
            'heslo' => $validaceHesla,
            'heslo_kontrola' => $validaceHesla,
        ];

        // provedení validací
        $navic = array_diff(array_keys($dbTab), array_keys($validace));
        if ($navic) {
            throw new Exception('Data obsahují nepovolené hodnoty');
        }

        foreach ($validace as $klic => $validator) {
            $hodnota = $dbTab[$klic] ?? null;

            if ($hodnota === null && $preskocitChybejiciPole) {
                continue;
            }

            if (is_array($validator)) {
                $regex = $validator[0];
                $popisChyby = $validator[1];
                if (!preg_match("/$regex/", $hodnota)) {
                    $chyby[$klic] = $popisChyby;
                }
            } else {
                $chyba = $validator($hodnota);
                if ($chyba) {
                    $chyby[$klic] = $chyba;
                }
            }
        }

        if ($chyby) {
            $ch = Chyby::zPole($chyby);
            $ch->globalniChyba($u ?
                'Úprava se nepodařila, oprav prosím zvýrazněné položky.' :
                'Registrace se nepodařila. Oprav prosím zvýrazněné položky.'
            );
            throw $ch;
        }

        // doplnění dopočítaných polí
        if (isset($dbTab['heslo'])) {
            $dbTab['heslo_md5'] = password_hash($dbTab['heslo'], PASSWORD_DEFAULT);
        }

        if (!$u) {
            $dbTab['random'] = randHex(20);
            $dbTab['registrovan'] = (new DateTimeCz)->formatDb();
        }

        // odstranění polí, co nebudou v DB
        unset($dbTab['heslo']);
        unset($dbTab['heslo_kontrola']);

        // uložení
        if ($u) {
            dbUpdate('uzivatele_hodnoty', $dbTab, ['id_uzivatele' => $u->id()]);
            $u->otoc();
            return $u->id();
        } else {
            dbInsert('uzivatele_hodnoty', $dbTab);
            $id = dbInsertId();
            return $id;
        }
    }

    /**
     * Rychloregistruje uživatele s omezeným počtem údajů při registraci na místě.
     * @return id nově vytvořeného uživatele (možno vytvořit objekt uživatele
     *  později jen pokud má smysl - výkonnostní důvody)
     * @todo možno evidovat, že uživatel byl regnut na místě
     * @todo poslat mail s něčím jiným jak std hláškou
     */
    static function rychloreg($tab, $opt = []) {
        if (!isset($tab['login_uzivatele']) || !isset($tab['email1_uzivatele']))
            throw new Exception('špatný formát $tab (je to pole?)');
        $opt = opt($opt, [
            'informovat' => true,
        ]);
        if (empty($tab['stat_uzivatele'])) $tab['stat_uzivatele'] = 1;
        $tab['random'] = $rand = randHex(20);
        $tab['registrovan'] = date("Y-m-d H:i:s");
        try {
            dbInsert('uzivatele_hodnoty', $tab);
        } catch (DbDuplicateEntryException $e) {
            if ($e->key() == 'email1_uzivatele') throw new DuplicitniEmailException;
            if ($e->key() == 'login_uzivatele') throw new DuplicitniLoginException;
            throw $e;
        }
        $uid = dbInsertId();
        //poslání mailu
        if ($opt['informovat']) {
            $tab['id_uzivatele'] = $uid;
            $u = new Uzivatel($tab); //pozor, spekulativní, nekompletní! využito kvůli std rozhraní hlaskaMail
            $mail = new GcMail(hlaskaMail('rychloregMail', $u, $tab['email1_uzivatele'], $rand));
            $mail->adresat($tab['email1_uzivatele']);
            $mail->predmet('Registrace na GameCon.cz');
            if (!$mail->odeslat())
                throw new Exception('Chyba: Email s novým heslem NEBYL odeslán, uživatel má pravděpodobně nastavený neplatný email');
        }
        return $uid;
    }

    /**
     * Smaže uživatele $u a jeho historii připojí k tomuto uživateli. Sloupečky
     * v poli $zmeny případně aktualizuje podle hodnot smazaného uživatele.
     */
    public function sluc(Uzivatel $u, $zmeny = []) {
        $zmeny = array_intersect_key($u->u, array_flip($zmeny));
        $zmeny['zustatek'] = $this->u['zustatek'] + $u->u['zustatek'];

        $slucovani = new UzivatelSlucovani;
        $slucovani->sluc($u, $this, $zmeny);

        // TODO přenačíst aktuálního uživatele
    }

    public function status() {
        return trim(strip_tags($this->statusHtml()));
    }

    /** Vrátí html formátovaný „status“ uživatele (pro interní informaci) */
    public function statusHtml() {
        $ka = $this->koncovkaDlePohlavi('ka');
        $status = [];
        if ($this->maPravo(P_TITUL_ORG)) {
            $status [] = '<span style="color:red">Organizátor' . $ka . '</span>';
        }
        if ($this->maZidli(Z_ORG_AKCI)) {
            $status[] = '<span style="color:blue">Vypravěč' . $ka . '</span>';
        }
        if ($this->maZidli(Z_PARTNER)) {
            $status[] = '<span style="color:darkslateblue">Partner' . $ka . '</span>';
        }
        if ($this->maZidli(Z_INFO)) {
            $status[] = '<span style="color:orange">Infopult</span>';
        }
        if ($this->maZidli(Z_ZAZEMI)) {
            $status[] = "Zázemí";
        }
        if (count($status) > 0) {
            return implode(', ', $status);
        }
        return 'Účastník';
    }

    /**
     * Vrátí telefon uživatele v blíže neurčeném formátu
     * @todo specifikovat formát čísla
     */
    public function telefon() {
        return $this->u['telefon_uzivatele'];
    }

    /**
     * Upraví hodnoty uživatele podle asoc.pole $tab, které by mělo odpovídat
     * struktuře tabulky uzivatele_hodnoty.
     *
     * Položky, které nebudou zadány, se nebudou měnit.
     *
     * Extra položky: heslo a heslo_kontrola (metoda si je sama převede na hash).
     */
    public function uprav($tab) {
        $tab = array_filter($tab);
        return self::registrujUprav($tab, $this);
    }

    /**
     * @return Vrátí url cestu k stránce uživatele (bez domény).
     */
    public function url() {
        $url = mb_strtolower($this->u['login_uzivatele']);
        if (!$this->u['jmeno_uzivatele'])
            return null; // nevracet url, asi vypravěčská skupina nebo podobně
        elseif (!Url::povolena($url))
            return null;
        else
            return $url;
    }

    public function vek() {
        if ($this->u['datum_narozeni'] == '0000-00-00' || $this->u['datum_narozeni'] == '1970-01-01') return null;
        $narozeni = new DateTime($this->u['datum_narozeni']);
        $vek = $narozeni->diff(new DateTime(DEN_PRVNI_DATE));
        return $vek->y;
    }

    /**
     * Vrátí věk uživatele k zadanému datu. Pokud nemá uživatel datum narození, vrací se null.
     *
     * @param DateTimeCz $datum
     * @return ?int
     */
    public function vekKDatu(DateTimeCz $datum) {
        if ($this->u['datum_narozeni'] == '0000-00-00') {
            return null;
        }
        return date_diff($this->datumNarozeni(), $datum)->y;
    }

    /**
     * Odstraní uživatele z židle a aktualizuje jeho práva.
     */
    public function vemZidli($zidle) {
        dbQuery('DELETE FROM r_uzivatele_zidle WHERE id_uzivatele=' . $this->id() . ' AND id_zidle=' . (int)$zidle);
        $this->aktualizujPrava();
    }

    //getters, setters

    public function id() {
        return isset($this->u['id_uzivatele']) ? $this->u['id_uzivatele'] : null;
    }

    /**
     * Vrátí pohlaví ve tvaru 'm' nebo 'f'
     */
    public function pohlavi() {
        return $this->u['pohlavi'];
    }

    public function prezdivka() {
        return $this->u['login_uzivatele'];
    }

    /** ISO 3166-1 alpha-2 */
    public function stat() {
        if ($this->u['stat_uzivatele'] == 1)
            return 'CZ';
        elseif ($this->u['stat_uzivatele'] == 2)
            return 'SK';
        elseif ($this->u['stat_uzivatele'] == -1)
            return null;
        else
            throw new Exception('Neznámé id státu v databázi.');
    }

    /**
     * surová data z DB
     */
    public function rawDb() {
        return $this->u;
    }

    /**
     * Na základě řetězce $dotaz zkusí najít všechny uživatele, kteří odpovídají
     * jménem, nickem, apod.
     */
    static function zHledani($dotaz, $opt = []) {
        $opt = opt($opt, [
            'mail' => false,
            'min' => 3, // minimum znaků
        ]);
        if (strlen($dotaz) < $opt['min']) {
            return [];
        }
        $q = dbQv($dotaz);
        $l = dbQv($dotaz . '%'); // pro LIKE dotazy
        return self::zWhere("
      WHERE u.id_uzivatele = $q
      OR login_uzivatele LIKE $l
      OR jmeno_uzivatele LIKE $l
      OR prijmeni_uzivatele LIKE $l
      " . ($opt['mail'] ? " OR email1_uzivatele LIKE $l " : "") . "
      OR CONCAT(jmeno_uzivatele,' ',prijmeni_uzivatele) LIKE $l
    ", null, 'LIMIT 20');
    }

    /**
     * @param int $id
     * @return Uzivatel|null
     */
    static function zId($id): ?Uzivatel {
        $o = self::zIds((int)$id);
        return $o ? $o[0] : null;
    }

    /**
     * Vrátí pole uživatelů podle zadaných ID. Lze použít pole nebo string s čísly
     * oddělenými čárkami.
     * @param string|int[] $ids
     * @return Uzivatel[]
     */
    static function zIds($ids): array {
        if (empty($ids)) {
            return [];
        }
        if (is_array($ids)) {
            return self::nactiUzivatele('WHERE u.id_uzivatele IN(' . dbQv($ids) . ')');
        }
        if (preg_match('@[0-9,]+@', $ids)) {
            return self::nactiUzivatele('WHERE u.id_uzivatele IN(' . $ids . ')');
        }
        throw new Exception('neplatný formát množiny id: ' . var_export($ids, true));
    }

    /**
     * Vrátí uživatele dle zadaného mailu.
     */
    static function zMailu($mail) {
        if (!$mail) return null;
        $uzivatel = Uzivatel::zWhere('WHERE email1_uzivatele = $1', [$mail]);
        return isset($uzivatel[0]) ? $uzivatel[0] : null;
    }

    static function zNicku($nick) {
        if (!$nick) return null;
        $uzivatel = Uzivatel::zWhere('WHERE login_uzivatele = $1', [$nick]);
        return isset($uzivatel[0]) ? $uzivatel[0] : null;
    }

    /**
     * Vytvoří a vrátí nového uživatele z zadaného pole odpovídajícího db struktuře
     */
    static function zPole($pole, $mod = 0) {
        if ($mod & self::FAKE) {
            $pole['email1_uzivatele'] = $pole['login_uzivatele'] . '@FAKE';
            $pole['nechce_maily'] = null;
            $pole['mrtvy_mail'] = 1;
            dbInsert('uzivatele_hodnoty', $pole);
            return self::zId(dbInsertId());
        } else {
            throw new Exception('nepodporováno');
        }
    }

    /**
     * Vrátí pole uživatelů přihlášených na letošní GC
     */
    static function zPrihlasenych() {
        return self::zWhere('
      WHERE u.id_uzivatele IN(
        SELECT id_uzivatele
        FROM r_uzivatele_zidle
        WHERE id_zidle = ' . Z_PRIHLASEN . '
      )
    ');
    }

    /**
     * Pokusí se načíst uživatele podle aktivní session případně z perzistentního
     * přihlášení.
     * @param string $klic klíč do $_SESSION kde očekáváme hodnoty uživatele
     * @return Uzivatel|null objekt uživatele nebo null
     * @todo nenačítat znovu jednou načteného, cacheovat
     */
    public static function zSession($klic = 'uzivatel') {
        if (!session_id()) {
            if (headers_sent($file, $line)) {
                throw new \RuntimeException("Headers have been already sent in file '$file' on line $line, can not start session");
            }
            session_start();
        }
        if (isset($_SESSION[$klic])) {
            $u = new Uzivatel($_SESSION[$klic]);
            $u->klic = $klic;
            return $u;
        }
        if (isset($_COOKIE['gcTrvalePrihlaseni']) && $klic === 'uzivatel') {
            $id = dbOneLineS('
        SELECT id_uzivatele
        FROM uzivatele_hodnoty
        WHERE random!="" AND random=$0',
                [$_COOKIE['gcTrvalePrihlaseni']]);
            $id = $id ? $id['id_uzivatele'] : null;
            //die(dbLastQ());
            if (!$id) {
                return null;
            }
            //změna tokenu do budoucna proti hádání
            dbQuery('
        UPDATE uzivatele_hodnoty
        SET random="' . ($rand = randHex(20)) . '"
        WHERE id_uzivatele=' . $id);
            setcookie('gcTrvalePrihlaseni', $rand, time() + 3600 * 24 * 365, '/');
            return Uzivatel::prihlasId($id, $klic);
        }
        return null;
    }

    /**
     * Vrátí uživatele s loginem odpovídajícím dané url
     */
    static function zUrl() {
        $url = Url::zAktualni()->cela();
        $u = self::nactiUzivatele('WHERE u.login_uzivatele = ' . dbQv($url));
        if (count($u) !== 1) return null;
        return $u[0];
    }

    /**
     * Načte uživatele podle zadané where klauzle
     * @todo asi lazy loading práv
     * @todo zrefaktorovat nactiUzivatele na toto
     */
    protected static function zWhere($where, $param = null, $extra = null) {
        $o = dbQueryS('
      SELECT
        u.*,
        GROUP_CONCAT(DISTINCT p.id_prava) as prava
      FROM uzivatele_hodnoty u
      LEFT JOIN r_uzivatele_zidle z ON(z.id_uzivatele = u.id_uzivatele)
      LEFT JOIN r_prava_zidle p ON(p.id_zidle = z.id_zidle)
      ' . $where . '
      GROUP BY u.id_uzivatele
    ' . $extra, $param);
        $uzivatele = [];
        while ($r = mysqli_fetch_assoc($o)) {
            $u = new static($r);
            $u->u['prava'] = explode(',', $u->u['prava']);
            $uzivatele[] = $u;
        }
        return $uzivatele;
    }

    /** Vrátí pole uživatelů sedících na židli s daným ID */
    public static function zZidle($id) {
        return self::nactiUzivatele( // WHERE nelze, protože by se omezily načítané práva uživatele
            'JOIN r_uzivatele_zidle z2 ON (z2.id_zidle = ' . dbQv($id) . ' AND z2.id_uzivatele = u.id_uzivatele)'
        );
    }

    ///////////////////////////////// Protected //////////////////////////////////

    /**
     * Aktualizuje práva uživatele z databáze (protože se provedla nějaká změna)
     */
    protected function aktualizujPrava() {
        $p = dbQuery('SELECT id_prava FROM r_uzivatele_zidle uz
      LEFT JOIN r_prava_zidle pz USING(id_zidle)
      WHERE uz.id_uzivatele=' . $this->id());
        $prava = []; //inicializace nutná, aby nepadala výjimka pro uživatele bez práv
        while ($r = mysqli_fetch_assoc($p))
            $prava[] = (int)$r['id_prava'];
        $_SESSION[$this->klic]['prava'] = $prava;
        $this->u['prava'] = $prava;
    }

    /**
     * Načte uživatele včetně práv z DB podle zadané where klauzule. Tabulka se
     * aliasuje jako u.*
     * @param string $where
     * @return Uzivatel[]
     */
    protected static function nactiUzivatele(string $where): array {
        $o = dbQuery('SELECT
        u.*,
        -- u.login_uzivatele,
        -- z.id_zidle,
        -- p.id_prava,
        GROUP_CONCAT(DISTINCT p.id_prava) as prava
      FROM uzivatele_hodnoty u
      LEFT JOIN r_uzivatele_zidle z ON(z.id_uzivatele=u.id_uzivatele)
      LEFT JOIN r_prava_zidle p ON(p.id_zidle=z.id_zidle)
      ' . $where . '
      GROUP BY u.id_uzivatele');
        $uzivatele = [];
        while ($r = mysqli_fetch_assoc($o)) {
            $u = new self($r);
            $u->u['prava'] = explode(',', $u->u['prava']);
            $uzivatele[] = $u;
        }
        return $uzivatele;
    }

    public function isSuperAdmin(): bool {
        return in_array($this->id(), SUPERADMINI, false);
    }

    public function dejShop(): Shop {
        if ($this->shop === null) {
            $this->shop = new Shop($this);
        }
        return $this->shop;
    }

    public function maNahranyDokladProtiCoviduProRok(int $rok): bool {
        $potvrzeniProtiCovid19PridanoKdy = $this->potvrzeniProtiCoviduPridanoKdy();
        return $potvrzeniProtiCovid19PridanoKdy
            && $potvrzeniProtiCovid19PridanoKdy->format('Y') === (string)$rok;
    }

    public function maOverenePotvrzeniProtiCoviduProRok(int $rok, bool $musiMitNahranyDokument = false): bool {
        if ($musiMitNahranyDokument && !$this->maNahranyDokladProtiCoviduProRok($rok)) {
            return false;
        }
        $potvrzeniProtiCovid19OverenoKdy = $this->potvrzeniProtiCoviduOverenoKdy();
        return $potvrzeniProtiCovid19OverenoKdy
            && $potvrzeniProtiCovid19OverenoKdy->format('Y') === (string)$rok;
    }

    public function covidFreePotvrzeniHtml(int $rok): string {
        $x = new XTemplate(__DIR__ . '/uzivatel-covid-potvrzeni.xtpl');
        $x->assign('a', $this->koncovkaDlePohlavi());
        if ($this->maNahranyDokladProtiCoviduProRok($rok)) {
            if ($this->maOverenePotvrzeniProtiCoviduProRok($rok, true)) {
                $x->assign(
                    'datumOvereniPotvrzeniProtiCovid',
                    (new DateTimeCz($this->potvrzeniProtiCoviduOverenoKdy()->format(DATE_ATOM)))->rozdilDne(new DateTimeImmutable())
                );
                $x->parse('covid.nahrano.overeno');
            } else {
                $x->assign('urlNaSmazaniPotvrzeni', $this->urlNaSmazaniPotrvrzeniVlastnikem());
                $x->parse('covid.nahrano.smazat');
            }
            $x->assign('urlNaPotvrzeniProtiCoviduProVlastnika', $this->urlNaPotvrzeniProtiCoviduProVlastnika());
            $x->assign(
                'datumNahraniPotvrzeniProtiCovid',
                (new DateTimeCz($this->potvrzeniProtiCoviduPridanoKdy()->format(DATE_ATOM)))->relativni()
            );
            $x->parse('covid.nahrano');
        } else {
            if ($this->maOverenePotvrzeniProtiCoviduProRok($rok, true)) {
                $x->assign(
                    'datumOvereniPotvrzeniProtiCovid',
                    (new DateTimeCz($this->potvrzeniProtiCoviduOverenoKdy()->format(DATE_ATOM)))->relativni()
                );
                $x->parse('covid.overenoBezDokladu');
            }
            $x->parse('covid.nahrat');
        }
        $x->parse('covid');
        return $x->text('covid');
    }

    public function zpracujPotvrzeniProtiCovidu(): bool {
        if (!isset($_FILES['potvrzeniProtiCovidu']) || empty($_FILES['potvrzeniProtiCovidu']['tmp_name'])) {
            return false;
        }
        $f = @fopen($_FILES['potvrzeniProtiCovidu']['tmp_name'], 'rb');
        if (!$f) {
            throw new Chyba("Soubor '{$_FILES['potvrzeniProtiCovidu']['name']}' se nepodařilo načíst");
        }
        $imagick = new Imagick();
        $imagick->setResolution(120, 120);

        $imageRead = false;
        try {
            $imageRead = $imagick->readImageFile($f);
        } catch (\Throwable $throwable) {
            trigger_error($throwable->getMessage(), E_USER_WARNING);
        }
        if (!$imageRead) {
            throw new Chyba("Soubor '{$_FILES['potvrzeniProtiCovidu']['name']}' se nepodařilo přečíst. Je to obrázek nebo PDF?");
        }

        try {
            $imagick->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
            $imagick->setImageCompressionQuality(100);
        } catch (\Throwable $throwable) {
            trigger_error($throwable->getMessage(), E_USER_WARNING);
        }
        $imagick->writeImage(WWW . '/soubory/systemove/potvrzeni/covid-19-' . $this->id() . '.png');

        $ted = new DateTimeImmutable();
        $this->ulozPotvrzeniProtiCoviduPridanyKdy($ted);

        return true;
    }

    private function ulozPotvrzeniProtiCoviduPridanyKdy(?\DateTimeInterface $kdy) {
        dbUpdate('uzivatele_hodnoty', [
            'potvrzeni_proti_covid19_pridano_kdy' => $kdy,
        ], [
            'id_uzivatele' => $this->id(),
        ]);
        $this->u['potvrzeni_proti_covid19_pridano_kdy'] = $kdy ? $kdy->format('Y-m-d H:i:s') : null;
        if ($this->klic) {
            $_SESSION[$this->klic]['potvrzeni_proti_covid19_pridano_kdy'] = $this->u['potvrzeni_proti_covid19_pridano_kdy'];
        }
    }

    private function ulozPotvrzeniProtiCoviduOverenoKdy(?\DateTimeInterface $kdy) {
        dbUpdate('uzivatele_hodnoty', [
            'potvrzeni_proti_covid19_overeno_kdy' => $kdy,
        ], [
            'id_uzivatele' => $this->id(),
        ]);
        $this->u['potvrzeni_proti_covid19_overeno_kdy'] = $kdy ? $kdy->format('Y-m-d H:i:s') : null;
        if ($this->klic) {
            $_SESSION[$this->klic]['potvrzeni_proti_covid19_overeno_kdy'] = $this->u['potvrzeni_proti_covid19_pridano_kdy'];
        }
    }

    public function urlNaPotvrzeniProtiCoviduProAdmin(): string {
        // admin/scripts/zvlastni/uvod/potvrzeni-proti-covidu.php
        return URL_ADMIN . '/uvod/potvrzeni-proti-covidu?id=' . $this->id();
    }

    public function urlNaPotvrzeniProtiCoviduProVlastnika(): string {
        // admin/scripts/zvlastni/uvod/potvrzeni-proti-covidu.php
        return URL_WEBU . '/prihlaska/potvrzeni-proti-covidu?id=' . $this->id();
    }

    public function urlNaSmazaniPotrvrzeniVlastnikem(): string {
        // admin/scripts/zvlastni/uvod/potvrzeni-proti-covidu.php
        return URL_WEBU . '/prihlaska/potvrzeni-proti-covidu?id=' . $this->id() . '&smazat=1';
    }

    public function cestaKSouboruSPotvrzenimProtiCovidu(): string {
        return WWW . '/soubory/systemove/potvrzeni/covid-19-' . $this->id() . '.png';
    }

    public function smazPotvrzeniProtiCovidu() {
        if (is_file($this->cestaKSouboruSPotvrzenimProtiCovidu())) {
            unlink($this->cestaKSouboruSPotvrzenimProtiCovidu());
        }
        $this->ulozPotvrzeniProtiCoviduPridanyKdy(null);
        $this->ulozPotvrzeniProtiCoviduOverenoKdy(null);
    }

    public function potvrzeniProtiCoviduPridanoKdy(): ?\DateTimeInterface {
        $potvrzeniProtiCovid19PridanoKdy = $this->u['potvrzeni_proti_covid19_pridano_kdy'] ?? null;
        return $potvrzeniProtiCovid19PridanoKdy
            ? new DateTimeImmutable($potvrzeniProtiCovid19PridanoKdy)
            : null;
    }

    public function potvrzeniProtiCoviduOverenoKdy(): ?\DateTimeInterface {
        $potvrzeniProtiCovid19OverenoKdy = $this->u['potvrzeni_proti_covid19_overeno_kdy'] ?? null;
        return $potvrzeniProtiCovid19OverenoKdy
            ? new DateTimeImmutable($potvrzeniProtiCovid19OverenoKdy)
            : null;
    }

    public function uvodniAdminUrl(string $zakladniAdminUrl): string {
        if ($this->maPravo(\Gamecon\Pravo::ADMINISTRACE_PANEL_MOJE_AKTIVITY)) {
            return $zakladniAdminUrl . '/' . basename(__DIR__ . '/../admin/scripts/modules/muj-prehled.php', '.php');
        }
        return $zakladniAdminUrl;
    }
}

class DuplicitniEmailException extends Exception
{
}

class DuplicitniLoginException extends Exception
{
}
