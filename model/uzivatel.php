<?php

use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Shop\Shop;
use Gamecon\Aktivita\Aktivita;
use Gamecon\Pravo;
use Gamecon\Zidle;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\XTemplate\XTemplate;

/**
 * Třída popisující uživatele a jeho vlastnosti
 * @todo načítání separátního (nepřihlášeného uživatele) např. pro účely schi-
 *   zofrenie v adminovi (nehrozí špatný přístup při nadměrném volání např. při
 *   práci s více uživateli někde jinde?)
 */
class Uzivatel
{
    public const POSAZEN = 'posazen';
    public const SESAZEN = 'sesazen';

    public const UZIVATEL_PRACOVNI = 'uzivatel_pracovni';
    public const UZIVATEL          = 'uzivatel';

    public const FAKE   = 0x01;  // modifikátor "fake uživatel"
    public const SYSTEM = 1;   // id uživatele reprezentujícího systém (např. "operaci provedl systém")

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

    protected $aktivityJakoSledujici; // pole s klíči id aktvit, kde je jako sledující
    protected $u = [];
    protected $klic = '';
    protected $idZidli;         // pole s klíči id židlí uživatele
    protected $finance;
    protected $shop;

    private $kdySeRegistrovalNaLetosniGc;

    public function __construct($uzivatel) {
        if (is_array($uzivatel) && array_keys_exist(['id_uzivatele', 'login_uzivatele', 'pohlavi'], $uzivatel)) {
            $this->u = $uzivatel;
        } else {
            throw new Exception('Špatný vstup konstruktoru uživatele');
        }
    }

    /**
     * @return string adresa uživatele ve formátu Město, Ulice ČP, PSČ, stát
     */
    public function adresa() {
        $adresa = $this->u['mesto_uzivatele'] . ', ' . $this->u['ulice_a_cp_uzivatele'] . ', ' . $this->u['psc_uzivatele'] . ', ' . $this->stat();
        return $adresa;
    }

    public function ubytovanS(string $ubytovanS = null): string {
        if ($ubytovanS !== null) {
            $this->u['ubytovan_s'] = $ubytovanS;
        }
        return $this->u['ubytovan_s'] ?? '';
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
    public function datumNarozeni(): DateTimeCz {
        if ((int)$this->u['datum_narozeni']) //hack, neplatný formát je '0000-00-00'
            return new DateTimeCz($this->u['datum_narozeni']);
        else
            return new DateTimeCz('0001-01-01');
    }

    /**
     * Přidá uživateli židli (posadí uživatele na židli)
     */
    public function dejZidli(int $idZidle, Uzivatel $posadil) {
        if ($this->maZidli($idZidle)) {
            return;
        }

        $novaPrava = dbOneArray('SELECT id_prava FROM r_prava_zidle WHERE id_zidle = $0', [$idZidle]);

        if ($this->maPravo(P_UNIKATNI_ZIDLE) && in_array(P_UNIKATNI_ZIDLE, $novaPrava)) {
            throw new Chyba('Uživatel už má jinou unikátní židli.');
        }

        $result = dbQuery(
            "INSERT IGNORE INTO r_uzivatele_zidle(id_uzivatele, id_zidle, posadil)
            VALUES ($1, $2, $3)",
            [$this->id(), $idZidle, $posadil->id()]
        );
        if (dbNumRows($result) > 0) {
            $this->zalogujZmenuZidle($idZidle, $posadil->id(), self::POSAZEN);
        }

        $this->aktualizujPrava();
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
     * @return \Gamecon\Uzivatel\Finance finance daného uživatele
     */
    public function finance(): \Gamecon\Uzivatel\Finance {
        //pokud chceme finance poprvé, spočteme je a uložíme
        if (!$this->finance) {
            $this->finance = new \Gamecon\Uzivatel\Finance($this, $this->u['zustatek']);
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
     * @todo Možná vyhodit výjimku, pokud už prošel infem, místo pouhého neudělání nic?
     * @todo Při odhlášení z GC pokud jsou zakázané rušení nákupů může být též problém (k zrušení dojde)
     */
    public function gcOdhlas(Uzivatel $odhlasujici): bool {
        if (!$this->gcPrihlasen()) {
            return false;
        }
        if ($this->gcPritomen()) {
            throw new \Gamecon\Exceptions\CanNotKickOutUserFromGamecon(
                'Už jsi prošel infopultem, odhlášení není možné.'
            );
        }
        try {
            // odeslání upozornění, pokud u nás má peníze
            if (($celkemLetosPoslal = $this->finance()->sumaPlateb()) > 0) {
                (new GcMail)
                    ->adresat('info@gamecon.cz')
                    ->predmet('Uživatel ' . $this->jmenoNick() . ' se odhlásil ale platil')
                    ->text(hlaskaMail('odhlasilPlatil', $this->jmenoNick(), $this->id(), ROK, $celkemLetosPoslal))
                    ->odeslat();
            }
            if ($dnyUbytovani = array_keys($this->dejShop()->ubytovani()->veKterychDnechJeUbytovan())) {
                (new GcMail)
                    ->adresat('info@gamecon.cz')
                    ->predmet('Uživatel ' . $this->jmenoNick() . ' se odhlásil a měl ubytování')
                    ->text(hlaskaMail('odhlasilMelUbytovani', $this->jmenoNick(), $this->id(), ROK, implode(', ', $dnyUbytovani)))
                    ->odeslat();
            }
        } catch (\Throwable $throwable) {
            trigger_error($throwable->getMessage() . '; ' . $throwable->getTraceAsString(), E_USER_WARNING);
        }
        foreach ($this->aktivityRyzePrihlasene() as $aktivita) {
            $aktivita->odhlas(
                $this,
                $odhlasujici,
                Aktivita::NEPOSILAT_MAILY_SLEDUJICIM /* nechceme posílat maily sledujícím, že se uvolnilo místo */
            );
        }
        // finální odebrání židle "registrován na GC"
        $this->vemZidli(Zidle::PRIHLASEN_NA_LETOSNI_GC, $odhlasujici);
        // zrušení nákupů (až po použití dejShop a ubytovani)
        dbQuery('DELETE FROM shop_nakupy WHERE rok=' . ROK . ' AND id_uzivatele=' . $this->id());

        return true;
    }

    /**
     * @param int $rok
     * @return Aktivita[]
     */
    public function organizovaneAktivity(int $rok = ROK): array {
        return Aktivita::zFiltru(
            ['rok' => $rok, 'organizator' => $this->id()],
            ['zacatek']
        );
    }

    /**
     * @param int $rok
     * @return Aktivita[]
     */
    public function aktivityRyzePrihlasene(int $rok = ROK): array {
        $ids = dbOneArray(<<<SQL
SELECT akce_prihlaseni.id_akce
FROM akce_prihlaseni
JOIN akce_seznam on akce_prihlaseni.id_akce = akce_seznam.id_akce
WHERE akce_prihlaseni.id_uzivatele = $1
AND akce_prihlaseni.id_stavu_prihlaseni = $2
AND akce_seznam.rok = $3
SQL,
            [$this->id(), StavPrihlaseni::PRIHLASEN, $rok]
        );
        return Aktivita::zIds($ids);
    }

    /**
     * @param int $rok
     * @return Aktivita[]
     */
    public function zapsaneAktivity(int $rok = ROK): array {
        $ids = dbOneArray(<<<SQL
SELECT akce_prihlaseni.id_akce
FROM akce_prihlaseni
JOIN akce_seznam on akce_prihlaseni.id_akce = akce_seznam.id_akce
WHERE akce_prihlaseni.id_uzivatele = $1
AND akce_seznam.rok = $2
SQL,
            [$this->id(), $rok]
        );
        return Aktivita::zIds($ids);
    }

    /** „Odjede“ uživatele z GC */
    public function gcOdjed(Uzivatel $editor) {
        if (!$this->gcPritomen()) {
            throw new Chyba('Uživatel není přítomen na GC');
        }
        $this->dejZidli(Zidle::ODJEL_Z_LETOSNIHO_GC, $editor);
    }

    /** Opustil uživatel GC? */
    public function gcOdjel() {
        if (!$this->gcPritomen()) {
            return false; // ani nedorazil, nemohl odjet
        }
        return $this->maZidli(ZIDLE_ODJEL);
    }

    /** Je uživatel přihlášen na aktuální GC? */
    public function gcPrihlasen() {
        return $this->maPravo(ID_PRAVO_PRIHLASEN);
    }

    /** Příhlásí uživatele na GC */
    public function gcPrihlas(Uzivatel $editor) {
        if ($this->gcPrihlasen()) {
            return;
        }

        $this->dejZidli(Zidle::PRIHLASEN_NA_LETOSNI_GC, $editor);
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
            $q                        = dbQuery('
        SELECT 2000 - (id_zidle DIV 100) AS "rok"
        FROM r_uzivatele_zidle
        WHERE id_zidle < 0 AND id_zidle MOD 100 = -1 AND id_uzivatele = $0
      ', [$this->id()]);
            $roky                     = mysqli_fetch_all($q);
            $roky                     = array_map(function ($e) {
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
    static function jmenoNickZjisti(array $r) {
        if (!empty($r['jmeno_uzivatele']) && !empty($r['prijmeni_uzivatele'])) {
            $celeJmeno = $r['jmeno_uzivatele'] . ' ' . $r['prijmeni_uzivatele'];
            $jeMail    = strpos($r['login_uzivatele'], '@') !== false;
            if ($celeJmeno == $r['login_uzivatele'] || $jeMail) {
                return $celeJmeno;
            }
            return $r['jmeno_uzivatele'] . ' „' . $r['login_uzivatele'] . '“ ' . $r['prijmeni_uzivatele'];
        }
        return $r['login_uzivatele'];
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

    /**
     * @return string[] povinné údaje které chybí
     */
    public function chybejiciUdaje(array $povinneUdaje) {
        $validator = function (string $sloupec) {
            return empty($this->u[$sloupec]);
        };
        return array_filter($povinneUdaje, $validator, ARRAY_FILTER_USE_KEY);
    }

    public function maPravo($pravo): bool {
        return in_array($pravo, $this->prava());
    }

    public function maPravoNaPristupDoPrezence(): bool {
        return $this->maPravo(Pravo::ADMINISTRACE_PREZENCE);
    }

    /**
     * Což taky znamená "Právo na placení až na místě"
     * @return bool
     */
    public function maPravoNerusitObjednavky(): bool {
        return $this->maPravo(Pravo::NERUSIT_AUTOMATICKY_OBJEDNAVKY);
    }

    public function nemaPravoNaBonusZaVedeniAktivit(): bool {
        return $this->maPravo(Pravo::BEZ_SLEVY_ZA_VEDENI_AKTIVIT);
    }

    public function maPravoNaBonusZaVedeniAktivit(): bool {
        return !$this->nemaPravoNaBonusZaVedeniAktivit();
    }

    public function maPravoNaPoradaniAktivit(): bool {
        return $this->maPravo(Pravo::PORADANI_AKTIVIT);
    }

    public function maPravoNaStrankuFinance(): bool {
        return $this->maPravo(Pravo::ADMINISTRACE_FINANCE);
    }

    public function maPravoNaZmenuHistorieAktivit(): bool {
        return $this->maPravo(Pravo::ZMENA_HISTORIE_AKTIVIT);
    }

    public function jeBrigadnik(): bool {
        return $this->maZidli(Zidle::BRIGADNIK);
    }

    public function jeVypravec(): bool {
        return $this->maZidli(Zidle::VYPRAVEC);
    }

    public function jeOrganizator(): bool {
        return Zidle::obsahujiOrganizatora($this->dejIdsZidli());
    }

    public function jePartner(): bool {
        return $this->maZidli(Zidle::PARTNER);
    }

    public function jeInfopultak(): bool {
        return $this->maZidli(Zidle::INFOPULT);
    }

    public function jeSpravceFinanci(): bool {
        return $this->maZidli(Zidle::SPRAVCE_FINANCI_GC);
    }

    public function jeSuperAdmin(): bool {
        if (!defined('SUPERADMINI') || !is_array(SUPERADMINI)) {
            return false;
        }
        return in_array($this->id(), SUPERADMINI, false);
    }

    /**
     * @param DateTimeInterface $od
     * @param DateTimeInterface $do
     * @param Aktivita|null $ignorovanaAktivita
     * @param bool $jenPritomen
     * @return bool jestli se uživatel v daném čase neúčastní / neorganizuje
     *  žádnou aktivitu (případně s výjimkou $ignorovanaAktivita)
     */
    public function maVolno(DateTimeInterface $od, DateTimeInterface $do, Aktivita $ignorovanaAktivita = null, bool $jenPritomen = false) {
        // právo na překrytí aktivit dává volno vždy automaticky
        // TODO zkontrolovat, jestli vlastníci práva dřív měli někdy paralelně i účast nebo jen organizovali a pokud jen organizovali, vyhodit test odsud a vložit do kontroly kdy se ukládá aktivita
        if ($this->maPravo(Pravo::PREKRYVANI_AKTIVIT)) {
            return true;
        }

        if ($this->maCasovouKolizi($this->zapsaneAktivity(), $od, $do, $ignorovanaAktivita, $jenPritomen)) {
            return false;
        }

        if ($this->maCasovouKolizi($this->organizovaneAktivity(), $od, $do, $ignorovanaAktivita, $jenPritomen)) {
            return false;
        }

        return true;
    }

    /**
     * @param Aktivita[] $aktivity
     * @param DateTimeInterface $od
     * @param DateTimeInterface $do
     * @param Aktivita|null $ignorovanaAktivita
     * @param bool $jenPritomen
     * @return bool
     */
    private function maCasovouKolizi(array $aktivity, DateTimeInterface $od, DateTimeInterface $do, ?Aktivita $ignorovanaAktivita, bool $jenPritomen): bool {
        $ignorovanaAktivitaId = $ignorovanaAktivita ? $ignorovanaAktivita->id() : 0;
        foreach ($aktivity as $aktivita) {
            if ($ignorovanaAktivitaId === $aktivita->id()) {
                continue;
            }
            $zacatek = $aktivita->zacatek();
            if (!$zacatek) {
                continue;
            }
            $konec = $aktivita->konec();
            if (!$konec) {
                continue;
            }
            /* koliduje, pokud začíná před koncem jiné aktivity a končí po začátku jiné aktivity */
            if ($zacatek < $do && $konec > $od) {
                return $jenPritomen
                    ? $aktivita->dorazilJakoCokoliv($this) // někde už je v daný čas přítomen
                    : true; // nekde už je na daný čas přihlášen
            }
        }
        return false;
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
        return in_array($idZidle, $this->dejIdsZidli(), true);
    }

    /**
     * @return int[]
     */
    public function dejIdsZidli(): array {
        if (!isset($this->idZidli)) {
            $zidle         = dbOneArray('SELECT id_zidle FROM r_uzivatele_zidle WHERE id_uzivatele = ' . $this->id());
            $this->idZidli = array_map('intval', $zidle);
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
            $p     = dbQuery('SELECT id_prava FROM r_uzivatele_zidle uz
        LEFT JOIN r_prava_zidle pz USING(id_zidle)
        WHERE uz.id_uzivatele=' . $this->id());
            $prava = []; //inicializace nutná, aby nepadala výjimka pro uživatele bez práv
            while ($r = mysqli_fetch_assoc($p))
                $prava[] = (int)$r['id_prava'];
            $this->u['prava'] = $prava;
        }
    }

    public function prava(): array {
        if (!isset($this->u['prava'])) {
            $this->nactiPrava();
        }
        return $this->u['prava'];
    }

    public function potvrzeniZakonnehoZastupceOd(): ?DateTimeImmutable {
        $potvrzeniOdString = $this->u['potvrzeni_zakonneho_zastupce'];

        return $potvrzeniOdString
            ? new \DateTimeImmutable($potvrzeniOdString)
            : null;
    }

    /** Vrátí přezdívku (nickname) uživatele */
    public function login(): string {
        return $this->u['login_uzivatele'];
    }

    /** Odhlásí aktuálně přihlášeného uživatele, pokud není přihlášen, nic
     * @param bool $back rovnou otočit na referrer?
     */
    public function odhlas($back = false) {
        $this->odhlasProTed();
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
    private function odhlasProTed() {
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
        SELECT akce_seznam.id_akce
        FROM akce_organizatori
        JOIN akce_seznam
            ON akce_seznam.id_akce = akce_organizatori.id_akce AND akce_seznam.rok = $2
        WHERE akce_organizatori.id_uzivatele = $1
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
        $id   = $this->id();
        $klic = $this->klic;
        // máme obnovit starou proměnnou pro id uživatele (otáčíme aktuálně přihlášeného uživatele)?
        $sesssionObnovit = (isset($_SESSION['id_uzivatele']) && $_SESSION['id_uzivatele'] == $this->id());
        if ($klic === self::UZIVATEL) { // pokud je klíč default, zničíme celou session
            $this->odhlasProTed(); // ponech případnou cookie pro trvalé přihášení
        } else { // pokud je speciální, pouze přemažeme položku v session
            self::odhlasKlic($klic);
        }
        $u       = Uzivatel::prihlasId($id, $klic);
        $this->u = $u->u;
        if ($sesssionObnovit) {
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
            return $poznamka;
        }
        return $this->u['poznamka'];
    }

    public function balicekHtml() {
        if (!$this->gcPrihlasen()) {
            return '';
        }
        $shop                = $this->dejShop();
        $objednalNejakeJidlo = $shop->objednalNejakeJidlo();
        if (!$shop->koupilNejakouVec()) {
            return $objednalNejakeJidlo
                ? "<span class=\"hinted\">jen stravenky<span class=\"hint\">{$shop->objednneJidloPrehledHtml()}</span></span>"
                : '';
        }
        $velikostBalicku = $this->u['infopult_poznamka'] === 'velký balíček ' . ROK
            ? 'velký balíček'
            : 'balíček';
        $nakupy          = [];
        $nakupy[]        = $shop->koupeneVeciPrehledHtml();
        if ($objednalNejakeJidlo) {
            $nakupy[] = $shop->objednneJidloPrehledHtml();
        }
        $nakupyHtml = implode('<hr>', $nakupy);
        return '<span class="hinted">' . htmlentities($velikostBalicku) . ' ' . $this->id() . '<span class="hint">' . $nakupyHtml . '</span></span>';
    }

    /**
     * Přihlásí uživatele s loginem $login k stránce
     * @param string $klic klíč do $_SESSION kde poneseme hodnoty uživatele
     * @param string $login login nebo primární e-mail uživatele
     * @param string $heslo heslo uživatele
     * @return mixed objekt s uživatelem nebo null
     */
    public static function prihlas($login, $heslo, $klic = self::UZIVATEL) {
        if (!$login || !$heslo) {
            return null;
        }

        $uzivatelData = dbOneLine(
            'SELECT * FROM uzivatele_hodnoty
            WHERE login_uzivatele = $0 OR email1_uzivatele = $0
            ORDER BY email1_uzivatele = $0 DESC -- e-mail má prioritu
            LIMIT 1',
            [$login]
        );
        if (!$uzivatelData) {
            return null;
        }
        // master password hack pro vývojovou větev
        $jeMaster = defined('UNIVERZALNI_HESLO') && $heslo == UNIVERZALNI_HESLO;
        // kontrola hesla
        if (!($jeMaster || password_verify($heslo, $uzivatelData['heslo_md5']))) {
            return null;
        }
        // kontrola zastaralých algoritmů hesel a případná aktualizace hashe
        $jeMd5 = strlen($uzivatelData['heslo_md5']) == 32 && preg_match('@^[0-9a-f]+$@', $uzivatelData['heslo_md5']);
        if ((password_needs_rehash($uzivatelData['heslo_md5'], PASSWORD_DEFAULT) || $jeMd5) && !$jeMaster) {
            $novyHash                  = password_hash($heslo, PASSWORD_DEFAULT);
            $uzivatelData['heslo_md5'] = $novyHash;
            dbQuery('UPDATE uzivatele_hodnoty SET heslo_md5 = $0 WHERE id_uzivatele = $1', [$novyHash, $uzivatelData['id_uzivatele']]);
        }
        // přihlášení uživatele
        // TODO refactorovat do jedné fce volané z dílčích prihlas* metod
        $idUzivatele = (int)$uzivatelData['id_uzivatele'];
        if (!session_id() && PHP_SAPI !== 'cli') {
            session_start();
        }
        $uzivatelData['id_uzivatele']    = $idUzivatele;
        $_SESSION[$klic]['id_uzivatele'] = $idUzivatele;
        // načtení uživatelských práv
        $p     = dbQuery(
            'SELECT id_prava
                FROM r_uzivatele_zidle uz
                    LEFT JOIN r_prava_zidle pz USING(id_zidle)
                WHERE uz.id_uzivatele=' . $idUzivatele
        );
        $prava = []; // inicializace nutná, aby nepadala výjimka pro uživatele bez práv
        while ($r = mysqli_fetch_assoc($p)) {
            $prava[] = (int)$r['id_prava'];
        }
        $uzivatelData['prava'] = $prava;

        return new Uzivatel($uzivatelData);
    }

    /**
     * Vytvoří v session na indexu $klic dalšího uživatele pro práci
     * @return null|Uzivatel nebo null
     */
    public static function prihlasId($idUzivatele, $klic = self::UZIVATEL): ?Uzivatel {
        $idUzivatele  = (int)$idUzivatele;
        $uzivatelData = dbOneLine('SELECT * FROM uzivatele_hodnoty WHERE id_uzivatele=$0', [$idUzivatele]);
        if (!$uzivatelData) {
            return null;
        }
        if (!session_id()) {
            session_start();
        }
        $_SESSION[$klic]['id_uzivatele'] = $idUzivatele;
        //načtení uživatelských práv
        $p     = dbQuery(
            'SELECT id_prava FROM r_uzivatele_zidle uz LEFT JOIN r_prava_zidle pz USING(id_zidle) WHERE uz.id_uzivatele=' . $idUzivatele
        );
        $prava = []; //inicializace nutná, aby nepadala výjimka pro uživatele bez práv
        while ($r = mysqli_fetch_assoc($p)) {
            $prava[] = (int)$r['id_prava'];
        }
        $uzivatelData['prava'] = $prava;
        $uzivatelData          = new Uzivatel($uzivatelData);
        $uzivatelData->klic    = $klic;

        return $uzivatelData;
    }

    /** Alias prihlas() pro trvalé přihlášení */
    public static function prihlasTrvale($login, $heslo, $klic = self::UZIVATEL) {
        $u    = Uzivatel::prihlas($login, $heslo, $klic);
        $rand = randHex(20);
        if ($u) {
            dbQuery(
                'UPDATE uzivatele_hodnoty
                SET random=$0
                WHERE id_uzivatele=' . $u->id(),
                [$rand]
            );
            setcookie('gcTrvalePrihlaseni', $rand, time() + 3600 * 24 * 365, '/');
        }
        return $u;
    }

    /**
     * @return bool true, pokud je uživatel přihlášen jako sledující aktivity (ve watchlistu).
     */
    public function prihlasenJakoSledujici(Aktivita $a) {
        if (!isset($this->aktivityJakoSledujici)) {
            $this->aktivityJakoSledujici = dbOneIndex("
        SELECT id_akce
        FROM akce_prihlaseni_spec
        WHERE id_uzivatele = $0 AND id_stavu_prihlaseni = $1
      ", [$this->id(), StavPrihlaseni::SLEDUJICI]);
        }
        return isset($this->aktivityJakoSledujici[$a->id()]);
    }

    public function dorazilJakoNahradnik(Aktivita $aktivita) {
        return $aktivita->dorazilJakoNahradnik($this);
    }

    /**
     * Vrátí timestamp prvního bloku kdy uživatel má aktivitu
     */
    public function prvniBlok() {
        return dbOneCol(
            'SELECT MIN(a.zacatek)
                FROM akce_seznam a
                    JOIN akce_prihlaseni p USING(id_akce)
                WHERE p.id_uzivatele = ' . $this->id() . ' AND a.rok = ' . ROK
        );
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
    private static function registrujUprav($tab, Uzivatel $u = null) {
        $dbTab                  = $tab;
        $chyby                  = [];
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
            if (empty($login)) {
                return 'vyber si prosím přezdívku';
            }

            $u2 = Uzivatel::zNicku($login) ?? Uzivatel::zMailu($login);
            if ($u2 && !$u) {
                return 'přezdívka už je zabraná. Pokud je tvoje, přihlaš se nebo si resetuj heslo';
            }
            if ($u2 && $u && $u2->id() != $u->id()) {
                return 'přezdívka už je zabraná. Vyber si prosím jinou';
            }
            return '';
        };

        $validaceMailu = function ($mail) use ($u) {
            if (!preg_match('/^[a-z0-9_\-.]+@[a-z0-9_\-.]+\.[a-z]+$/', $mail)) {
                return 'zadej prosím platný e-mail';
            }

            $u2 = Uzivatel::zNicku($mail) ?? Uzivatel::zMailu($mail);
            if ($u2 && !$u) {
                return 'e-mail už máš zaregistrovaný. Přihlaš se nebo si resetuj heslo';
            }
            if ($u2 && $u && $u2->id() != $u->id()) {
                return 'e-mail už je zabraný. Pokud je tvůj, resetuj si heslo';
            }
            return '';
        };

        $validaceHesla = function ($heslo) use ($dbTab) {
            if (empty($heslo)) return 'vyplň prosím heslo';

            if (
                $heslo != ($dbTab['heslo'] ?? null) ||
                $heslo != ($dbTab['heslo_kontrola'] ?? null)
            ) {
                return 'hesla se neshodují';
            }
            return '';
        };

        $validace = [
            'jmeno_uzivatele'      => ['.+', 'jméno nesmí být prázdné'],
            'prijmeni_uzivatele'   => ['.+', 'příjmení nesmí být prázdné'],
            'login_uzivatele'      => $validaceLoginu,
            'email1_uzivatele'     => $validaceMailu,
            'pohlavi'              => ['^(m|f)$', 'vyber prosím pohlaví'],
            'ulice_a_cp_uzivatele' => ['.+ [\d\/a-z]+$', 'vyplň prosím ulici, např. Česká 27'],
            'mesto_uzivatele'      => ['.+', 'vyplň prosím město'],
            'psc_uzivatele'        => ['^[\d ]+$', 'vyplň prosím PSČ, např. 602 00'],
            'stat_uzivatele'       => ['^(1|2|-1)$', 'vyber prosím stát'],
            'telefon_uzivatele'    => ['^[\d \+]+$', 'vyplň prosím telefon, např. +420 789 123 456'],
            'datum_narozeni'       => ['\d+', 'vyber prosím datum narození'], // TODO
            'heslo'                => $validaceHesla,
            'heslo_kontrola'       => $validaceHesla,
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
                $regex      = $validator[0];
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
            $ch->globalniChyba($u
                ?
                'Úprava se nepodařila, oprav prosím zvýrazněné položky.'
                :
                'Registrace se nepodařila. Oprav prosím zvýrazněné položky.'
            );
            throw $ch;
        }

        // doplnění dopočítaných polí
        if (isset($dbTab['heslo'])) {
            $dbTab['heslo_md5'] = password_hash($dbTab['heslo'], PASSWORD_DEFAULT);
        }

        if (!$u) {
            $dbTab['random']      = randHex(20);
            $dbTab['registrovan'] = (new DateTimeCz)->formatDb();
        }

        // odstranění polí, co nebudou v DB
        unset($dbTab['heslo']);
        unset($dbTab['heslo_kontrola']);

        // uložení
        if ($u) {
            dbUpdate('uzivatele_hodnoty', $dbTab, ['id_uzivatele' => $u->id()]);
            $u->otoc();
            $idUzivatele  = $u->id();
            $urlUzivatele = self::vytvorUrl($u->u);
        } else {
            dbInsert('uzivatele_hodnoty', $dbTab);
            $idUzivatele           = dbInsertId();
            $dbTab['id_uzivatele'] = $idUzivatele;
            $urlUzivatele          = self::vytvorUrl($dbTab);
        }
        if ($urlUzivatele !== null) {
            dbInsertUpdate('uzivatele_url', ['id_uzivatele' => $idUzivatele, 'url' => $urlUzivatele]);
        }

        return $idUzivatele;
    }

    /**
     * Rychloregistruje uživatele s omezeným počtem údajů při registraci na místě.
     * @return int id nově vytvořeného uživatele (možno vytvořit objekt uživatele
     *  později jen pokud má smysl - výkonnostní důvody)
     * @todo možno evidovat, že uživatel byl regnut na místě
     * @todo poslat mail s něčím jiným jak std hláškou
     */
    static function rychloreg($tab, $opt = []) {
        if (!isset($tab['login_uzivatele']) || !isset($tab['email1_uzivatele'])) {
            throw new Exception('špatný formát $tab (je to pole?)');
        }
        $opt = opt($opt, [
            'informovat' => true,
        ]);
        if (empty($tab['stat_uzivatele'])) $tab['stat_uzivatele'] = 1;
        $tab['random']      = $rand = randHex(20);
        $tab['registrovan'] = date("Y-m-d H:i:s");
        try {
            dbInsert('uzivatele_hodnoty', $tab);
        } catch (DbDuplicateEntryException $e) {
            if ($e->key() == 'email1_uzivatele') {
                throw new DuplicitniEmailException;
            }
            if ($e->key() == 'login_uzivatele') {
                throw new DuplicitniLoginException;
            }
            throw $e;
        }
        $uid = dbInsertId();
        //poslání mailu
        if ($opt['informovat']) {
            $tab['id_uzivatele'] = $uid;
            $u                   = new Uzivatel($tab); //pozor, spekulativní, nekompletní! využito kvůli std rozhraní hlaskaMail
            $mail                = new GcMail(hlaskaMail('rychloregMail', $u, $tab['email1_uzivatele'], $rand));
            $mail->adresat($tab['email1_uzivatele']);
            $mail->predmet('Registrace na GameCon.cz');
            if (!$mail->odeslat()) {
                throw new Exception('Chyba: Email s novým heslem NEBYL odeslán, uživatel má pravděpodobně nastavený neplatný email');
            }
        }
        return $uid;
    }

    /**
     * Smaže uživatele $u a jeho historii připojí k tomuto uživateli. Sloupečky
     * v poli $zmeny případně aktualizuje podle hodnot smazaného uživatele.
     */
    public function sluc(Uzivatel $u, $zmeny = []) {
        $zmeny             = array_intersect_key($u->u, array_flip($zmeny));
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
        $ka     = $this->koncovkaDlePohlavi('ka');
        $status = [];
        if ($this->maPravo(Pravo::TITUL_ORGANIZATOR)) {
            $status [] = '<span style="color:red">Organizátor' . $ka . '</span>';
        }
        if ($this->maZidli(Zidle::VYPRAVEC)) {
            $status[] = '<span style="color:blue">Vypravěč' . $ka . '</span>';
        }
        if ($this->jePartner()) {
            $status[] = '<span style="color:darkslateblue">Partner' . $ka . '</span>';
        }
        if ($this->maZidli(Zidle::INFOPULT)) {
            $status[] = '<span style="color:orange">Infopult</span>';
        }
        if ($this->maZidli(Zidle::HERMAN)) {
            $status[] = '<span style="color:orange">Herman</span>';
        }
        if ($this->maZidli(Zidle::BRIGADNIK)) {
            $status[] = '<span style="color:yellowgreen">Brigádník</span>';
        }
        if ($this->maZidli(ZIDLE_ZAZEMI)) {
            $status[] = "Zázemí";
        }
        if ($this->maZidli(Zidle::DOBROVOLNIK_SENIOR)) {
            $status[] = "Dobrovolník senior";
        }
        if (count($status) > 0) {
            return implode(', ', $status);
        }
        return 'Účastník';
    }

    public function telefon(bool $html = false): string {
        $telefon = trim((string)$this->u['telefon_uzivatele']);
        if ($telefon === '') {
            return '';
        }
        // zahodíme českou předvolbu a mezery
        $telefon = preg_replace('~(^[+]?\s*420|\s)~', '', $telefon);

        $predvolba = '';
        if (preg_match('~^(?<predvolba>[+]?\d{3})\d{9}~', $telefon, $matches)) {
            $predvolba = $matches['predvolba'];
            $telefon   = preg_replace('~^' . preg_quote($predvolba, '~') . '~', '', $telefon);
        }

        if (strlen($telefon) === 9) {
            $telefon = chunk_split($telefon, 3, ' '); // na každé třetí místo vložíme mezeru
        }

        if ($html) {
            $cssClassSPredvolbou = $predvolba === ''
                ? ''
                : 's-predvolbou';
            $htmPredvolba        = $predvolba === ''
                ? ''
                : "<span class='predvolba'>$predvolba</span> ";
            return "<span class='telefon $cssClassSPredvolbou'>$htmPredvolba$telefon</span>";
        }

        return $predvolba !== ''
            ? "$predvolba $telefon"
            : $telefon;
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
     * Vrátí url cestu k stránce uživatele (bez domény).
     */
    public function url(bool $vcetneId = false): ?string {
        if (!$this->u['jmeno_uzivatele']) {
            return null; // nevracet url, asi vypravěčská skupina nebo podobně
        }
        if (!empty($this->u['url'])) {
            return $vcetneId
                ? $this->id() . '-' . $this->u['url']
                : $this->u['url'];
        }
        return self::vytvorUrl($this->u);
    }

    private static function vytvorUrl(array $uzivatelData): ?string {
        $jmenoNick = self::jmenoNickZjisti($uzivatelData);
        $url       = slugify($jmenoNick);

        return Url::povolena($url)
            ? $url
            : null;
    }

    public function vek(): ?int {
        if ($this->u['datum_narozeni'] == '0000-00-00' || $this->u['datum_narozeni'] == '1970-01-01') {
            return null;
        }
        $narozeni = new DateTime($this->u['datum_narozeni']);
        return $narozeni->diff(new DateTime(DEN_PRVNI_DATE))->y;
    }

    /**
     * Vrátí věk uživatele k zadanému datu. Pokud nemá uživatel datum narození, vrací se null.
     *
     * @param DateTimeCz $datum
     * @return ?int
     */
    public function vekKDatu(DateTimeCz $datum): ?int {
        if ($this->u['datum_narozeni'] == '0000-00-00') {
            return null;
        }
        return date_diff($this->datumNarozeni(), $datum)->y;
    }

    /**
     * Odstraní uživatele z židle a aktualizuje jeho práva.
     */
    public function vemZidli(int $idZidle, Uzivatel $editor) {
        $result = dbQuery('DELETE FROM r_uzivatele_zidle WHERE id_uzivatele=' . $this->id() . ' AND id_zidle=' . $idZidle);
        if (dbNumRows($result) > 0) {
            $this->zalogujZmenuZidle($idZidle, $editor->id(), self::SESAZEN);
        }
        $this->aktualizujPrava();
    }

    private function zalogujZmenuZidle(int $idZidle, int $idEditora, string $zmena) {
        dbQuery(<<<SQL
INSERT INTO r_uzivatele_zidle_log(id_uzivatele, id_zidle, id_zmenil, zmena, kdy)
VALUES ($0, $1, $2, $3, NOW())
SQL,
            [$this->id(), $idZidle, $idEditora, $zmena]
        );
    }

    //getters, setters

    public function id(): ?int {
        return isset($this->u['id_uzivatele'])
            ? (int)$this->u['id_uzivatele']
            : null;
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

    /** Vrátí kód státu ve formátu ISO 3166-1 alpha-2 https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2 */
    public function stat(): ?string {
        return \Gamecon\Stat::dejKodStatuPodleId($this->u['stat_uzivatele'] ? (int)$this->u['stat_uzivatele'] : null);
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
    static function zHledani(string $dotaz, $opt = [], int $limit = 20, int $minimumZnaku = 3) {
        $opt = opt(
            $opt,
            [
                'mail'                       => false,
                'jenPrihlaseniAPritomniNaGc' => false,
                'kromeIdUzivatelu'           => [],
                'jenSeZidlemi'               => null,
                'min'                        => $minimumZnaku,
            ]
        );
        if (!is_numeric($dotaz) && mb_strlen($dotaz) < $opt['min']) {
            return [];
        }
        $q                   = dbQv($dotaz);
        $l                   = dbQv($dotaz . '%'); // pro LIKE dotazy
        $kromeIdUzivatelu    = $opt['kromeIdUzivatelu'];
        $kromeIdUzivateluSql = dbQv($kromeIdUzivatelu);
        $pouzeIdZidli        = [];
        if ($opt['jenSeZidlemi']) {
            $pouzeIdZidli = $opt['jenSeZidlemi'];
        }
        if ($opt['jenPrihlaseniAPritomniNaGc']) {
            $pouzeIdZidli = array_merge($pouzeIdZidli, [Zidle::PRIHLASEN_NA_LETOSNI_GC, Zidle::PRITOMEN_NA_LETOSNIM_GC]);
        }
        $pouzeIdZidliSql = dbQv($pouzeIdZidli);

        return self::zWhere("
      WHERE TRUE
      " . ($kromeIdUzivatelu ? " AND u.id_uzivatele NOT IN ($kromeIdUzivateluSql)" : '') . "
      " . ($pouzeIdZidli ? " AND p.id_zidle IN ($pouzeIdZidliSql) " : '') . "
      AND (
          u.id_uzivatele = $q
          " . ((string)(int)$dotaz !== (string)$dotaz // nehledáme ID
                ? ("
                  OR login_uzivatele LIKE $l
                  OR jmeno_uzivatele LIKE $l
                  OR prijmeni_uzivatele LIKE $l
                  " . ($opt['mail'] ? " OR email1_uzivatele LIKE $l " : "") . "
                  OR CONCAT(jmeno_uzivatele,' ',prijmeni_uzivatele) LIKE $l
                  ")
                : ''
            ) . "
      )
    ", null, 'LIMIT ' . $limit);
    }

    /**
     * @param int $id
     * @return Uzivatel|null
     */
    static function zId($id): ?Uzivatel {
        $o = self::zIds((int)$id);
        return $o ? $o[0] : null;
    }

    public static function zIdUrcite($id): self {
        $uzivatel = static::zId($id);
        if ($uzivatel !== null) {
            return $uzivatel;
        }
        throw new \Gamecon\Exceptions\UzivatelNenalezen('Neznámé ID uživatele ' . $id);
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
    static function zMailu(?string $mail): ?Uzivatel {
        if (!$mail) {
            return null;
        }
        $uzivatel = Uzivatel::zWhere('WHERE email1_uzivatele = $1', [$mail]);
        return isset($uzivatel[0])
            ? $uzivatel[0]
            : null;
    }

    static function zNicku(string $nick): ?Uzivatel {
        if (!$nick) {
            return null;
        }
        $uzivatelWrapped = Uzivatel::zWhere('WHERE login_uzivatele = $1', [$nick]);
        return reset($uzivatelWrapped) ?: null;
    }

    /**
     * Vytvoří a vrátí nového uživatele z zadaného pole odpovídajícího db struktuře
     */
    static function zPole($pole, $mod = 0) {
        if ($mod & self::FAKE) {
            $pole['email1_uzivatele'] = $pole['login_uzivatele'] . '@FAKE';
            $pole['nechce_maily']     = null;
            $pole['mrtvy_mail']       = 1;
            dbInsert('uzivatele_hodnoty', $pole);
            return self::zId(dbInsertId());
        }
        throw new Exception('nepodporováno');
    }

    /**
     * Vrátí pole uživatelů přihlášených na letošní GC
     * @return Uzivatel[]
     */
    public static function zPrihlasenych() {
        return self::zWhere('
      WHERE u.id_uzivatele IN(
        SELECT id_uzivatele
        FROM r_uzivatele_zidle
        WHERE id_zidle = ' . ZIDLE_PRIHLASEN . '
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
    public static function zSession($klic = self::UZIVATEL) {
        if (!session_id()) {
            if (headers_sent($file, $line)) {
                throw new \RuntimeException("Headers have been already sent in file '$file' on line $line, can not start session");
            }
            session_start();
        }
        if (isset($_SESSION[$klic])) {
            $u = null;
            if (!empty($_SESSION[$klic]['id_uzivatele'])) {
                $u = Uzivatel::zId($_SESSION[$klic]['id_uzivatele']);
            }
            if ($u) {
                $u->klic = $klic;
                return $u;
            }
        }
        if (isset($_COOKIE['gcTrvalePrihlaseni']) && $klic === self::UZIVATEL) {
            $id = dbOneCol(
                "SELECT id_uzivatele FROM uzivatele_hodnoty WHERE random!='' AND random=$0",
                [$_COOKIE['gcTrvalePrihlaseni']]
            );
            if (!$id) {
                return null;
            }
            $rand = randHex(20);
            //změna tokenu do budoucna proti hádání
            dbQuery(
                "UPDATE uzivatele_hodnoty
                SET random=$0
                WHERE id_uzivatele=$id",
                [$rand]
            );
            setcookie('gcTrvalePrihlaseni', $rand, time() + 3600 * 24 * 365, '/');
            return Uzivatel::prihlasId($id, $klic);
        }
        return null;
    }

    /**
     * Vrátí uživatele s loginem odpovídajícím dané url
     */
    static function zUrl(): ?Uzivatel {
        $aktualniUrl = Url::zAktualni()->cela();
        $idUzivatele = (int)$aktualniUrl;
        if ($idUzivatele) {
            return self::zId($idUzivatele);
        }
        $urlUzivatele = preg_replace('~^[^[:alnum:]]*\d*-?~', '', $aktualniUrl);
        $u            = self::nactiUzivatele("WHERE uzivatele_url.url = " . dbQv($urlUzivatele));
        return count($u) !== 1
            ? null
            : $u[0];
    }

    /**
     * Načte uživatele podle zadané where klauzle
     * @todo asi lazy loading práv
     * @todo zrefaktorovat nactiUzivatele na toto
     */
    protected static function zWhere($where, $param = null, $extra = null) {
        $o         = dbQuery('
      SELECT
        u.*,
        (SELECT url FROM uzivatele_url WHERE uzivatele_url.id_uzivatele = u.id_uzivatele ORDER BY id_url_uzivatele DESC LIMIT 1) AS url,
        GROUP_CONCAT(DISTINCT p.id_prava) as prava
      FROM uzivatele_hodnoty u
      LEFT JOIN r_uzivatele_zidle z ON(z.id_uzivatele = u.id_uzivatele)
      LEFT JOIN r_prava_zidle p ON(p.id_zidle = z.id_zidle)
      ' . $where . '
      GROUP BY u.id_uzivatele
    ' . $extra, $param);
        $uzivatele = [];
        while ($r = mysqli_fetch_assoc($o)) {
            $u             = new static($r);
            $u->u['prava'] = explode(',', $u->u['prava'] ?? '');
            $uzivatele[]   = $u;
        }
        return $uzivatele;
    }

    /** Vrátí pole uživatelů sedících na židli s daným ID */
    public static function zZidle($id) {
        return self::nactiUzivatele( // WHERE nelze, protože by se omezily načítané práva uživatele
            'JOIN r_uzivatele_zidle z2 ON (z2.id_zidle = ' . dbQv($id) . ' AND z2.id_uzivatele = u.id_uzivatele)'
        );
    }

    /**
     * @param int $idZidle
     * @return int[]
     */
    public static function idsZeZidle(int $idZidle): array {
        $uzivateleIds = dbArrayCol(<<<SQL
SELECT uzivatele_hodnoty.id_uzivatele
FROM uzivatele_hodnoty
JOIN r_uzivatele_zidle on uzivatele_hodnoty.id_uzivatele = r_uzivatele_zidle.id_uzivatele
WHERE id_zidle = $1
SQL,
            $idZidle
        );

        return array_map('intval', $uzivateleIds);
    }

    ///////////////////////////////// Protected //////////////////////////////////

    /**
     * Aktualizuje práva uživatele z databáze (protože se provedla nějaká změna)
     */
    protected function aktualizujPrava() {
        $p     = dbQuery(
            'SELECT id_prava
                FROM r_uzivatele_zidle uz
                    LEFT JOIN r_prava_zidle pz USING(id_zidle)
                WHERE uz.id_uzivatele=' . $this->id()
        );
        $prava = []; // inicializace nutná, aby nepadala výjimka pro uživatele bez práv
        while ($r = mysqli_fetch_assoc($p)) {
            $prava[] = (int)$r['id_prava'];
        }
        $this->u['prava'] = $prava;
    }

    /**
     * Načte uživatele včetně práv z DB podle zadané where klauzule. Tabulka se
     * aliasuje jako u.*
     * @param string $where
     * @return Uzivatel[]
     */
    protected static function nactiUzivatele(string $where): array {
        $o         = dbQuery('SELECT
        u.*,
        (SELECT url FROM uzivatele_url WHERE uzivatele_url.id_uzivatele = u.id_uzivatele ORDER BY id_url_uzivatele DESC LIMIT 1) AS url,
        -- u.login_uzivatele,
        -- z.id_zidle,
        -- p.id_prava,
        GROUP_CONCAT(DISTINCT p.id_prava) as prava
      FROM uzivatele_hodnoty u
      LEFT JOIN r_uzivatele_zidle z ON(z.id_uzivatele=u.id_uzivatele)
      LEFT JOIN r_prava_zidle p ON(p.id_zidle=z.id_zidle)
      LEFT JOIN uzivatele_url ON u.id_uzivatele = uzivatele_url.id_uzivatele
      ' . $where . '
      GROUP BY u.id_uzivatele');
        $uzivatele = [];
        while ($r = mysqli_fetch_assoc($o)) {
            $u             = new self($r);
            $u->u['prava'] = explode(',', $u->u['prava'] ?? '');
            $uzivatele[]   = $u;
        }
        return $uzivatele;
    }

    public function dejShop(): Shop {
        if ($this->shop === null) {
            $this->shop = new Shop($this, null, SystemoveNastaveni::vytvorZGlobalnich());
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
        $this->u['potvrzeni_proti_covid19_pridano_kdy'] = $kdy ? $kdy->format(DateTimeCz::FORMAT_DB) : null;
    }

    private function ulozPotvrzeniProtiCoviduOverenoKdy(?\DateTimeInterface $kdy) {
        dbUpdate('uzivatele_hodnoty', [
            'potvrzeni_proti_covid19_overeno_kdy' => $kdy,
        ], [
            'id_uzivatele' => $this->id(),
        ]);
        $this->u['potvrzeni_proti_covid19_overeno_kdy'] = $kdy ? $kdy->format(DateTimeCz::FORMAT_DB) : null;
    }

    public function urlNaPotvrzeniProtiCoviduProAdmin(): string {
        // admin/scripts/zvlastni/infopult/potvrzeni-proti-covidu.php
        return URL_ADMIN . '/infopult/potvrzeni-proti-covidu?id=' . $this->id();
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

    public function uvodniAdminUrl(string $zakladniAdminUrl = URL_ADMIN): string {
        if ($this->maPravo(Pravo::ADMINISTRACE_INFOPULT)) {
            return $this->infopultAdminUrl($zakladniAdminUrl);
        }
        if ($this->maPravo(Pravo::ADMINISTRACE_MOJE_AKTIVITY)) {
            return $this->mojeAktivityAdminUrl($zakladniAdminUrl);
        }
        return $zakladniAdminUrl;
    }

    public function infopultAdminUrl(string $zakladniAdminUrl = URL_ADMIN): string {
        // vrátí "infopult" - máme to schválně přes cestu ke skriptu, protože jeho název udává výslednou URL a nechceme mít neplatnou URL, kdyby někdo ten skrip přejmenoval.
        return $zakladniAdminUrl . '/' . basename(__DIR__ . '/../admin/scripts/modules/infopult.php', '.php');
    }

    /**
     * Může vrátit i URL na web mimo admin, pokud jediná admin stránka, na kterou má uživatel právo, je nechtěná moje-aktivity.
     * @param string $zakladniAdminUrl
     * @param string $zakladniWebUrl
     * @return string[] nazev => název, url => URL
     */
    public function mimoMojeAktivityUvodniAdminLink(string $zakladniAdminUrl = URL_ADMIN, string $zakladniWebUrl = URL_WEBU): array {
        // URL máme schválně přes cestu ke skriptu, protože jeho název udává výslednou URL a nechceme mít neplatnou URL, kdyby někdo ten skrip přejmenoval.
        if ($this->maPravo(Pravo::ADMINISTRACE_INFOPULT)) {
            /** 'uvod' viz například @link http://admin.beta.gamecon.cz/moje-aktivity/infopult */
            $adminUvodUrl = basename(__DIR__ . '/../admin/scripts/modules/infopult.php', '.php');
            return ['url' => $zakladniAdminUrl . '/' . $adminUvodUrl, 'nazev' => 'do Adminu'];
        }
        if ($this->jeOrganizator()) {
            /** 'uvod' viz například @link http://admin.beta.gamecon.cz/moje-aktivity/uzivatel */
            $adminUvodUrl = basename(__DIR__ . '/../admin/scripts/modules/uzivatel.php', '.php');
            return ['url' => $zakladniAdminUrl . '/' . $adminUvodUrl, 'nazev' => 'do Adminu'];
        }
        $webProgramUrl = basename(__DIR__ . '/../web/moduly/program.php', '.php');
        return ['url' => $zakladniWebUrl . '/' . $webProgramUrl, 'nazev' => 'na Program'];
    }

    public function mojeAktivityAdminUrl(string $zakladniAdminUrl = URL_ADMIN): string {
        // vrátí "moje-aktivity" - máme to schválně přes cestu ke skriptu, protože jeho název udává výslednou URL a nechceme mít neplatnou URL, kdyby někdo ten skrip přejmenoval.
        return $zakladniAdminUrl . '/' . basename(__DIR__ . '/../admin/scripts/modules/moje-aktivity/moje-aktivity.php', '.php');
    }

    public function kdySeRegistrovalNaLetosniGc(): ?DateTimeImmutable {
        if (!$this->gcPrihlasen()) {
            return null;
        }
        if (!$this->kdySeRegistrovalNaLetosniGc) {
            $hodnota                           = dbOneCol(<<<SQL
SELECT posazen FROM r_uzivatele_zidle WHERE id_uzivatele = $0 AND id_zidle = $1
SQL,
                [$this->id(), ID_PRAVO_PRIHLASEN]
            );
            $this->kdySeRegistrovalNaLetosniGc = $hodnota
                ? new DateTimeImmutable($hodnota)
                : null;
        }
        return $this->kdySeRegistrovalNaLetosniGc;
    }
}

class DuplicitniEmailException extends Exception
{
}

class DuplicitniLoginException extends Exception
{
}
