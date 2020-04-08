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

  protected
    $aktivityJakoNahradnik, // pole s klíči id aktvit, kde je jako náhradník
    $u = [],
    $klic = '',
    $idZidli,         // pole s klíči id židlí uživatele
    $finance = null;

  const
    FAKE = 0x01,  // modifikátor "fake uživatel"
    SYSTEM = 1;   // id uživatele reprezentujícího systém (např. "operaci provedl systém")

  /** Vytvoří uživatele z různých možných vstupů */
  function __construct($uzivatel) {
    if (is_array($uzivatel) && array_keys_exist([
        'id_uzivatele', 'login_uzivatele', 'pohlavi',
      ], $uzivatel)) { //asi čteme vstup z databáze
      $this->u = $uzivatel;
      return;
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
    return (bool)dbOneCol(<<<SQL
SELECT 1
FROM r_uzivatele_zidle
JOIN r_prava_zidle ON r_uzivatele_zidle.id_zidle = r_prava_zidle.id_zidle
WHERE r_uzivatele_zidle.id_uzivatele = $1
AND r_prava_zidle.id_prava = $2
SQL
      , [$this->id(), \Gamecon\Pravo::PORADANI_AKTIVIT]
    );
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
  function adresa() {
    $adresa = $this->u['mesto_uzivatele'] . ', ' . $this->u['ulice_a_cp_uzivatele'] . ', ' . $this->u['psc_uzivatele'] . ', ' . $this->stat();
    return $adresa;
  }

  /**
   * Vrátí aboslutní adresu avataru včetně http. Pokud avatar neexistuje, vrací
   * default avatar. Pomocí adresy je docíleno, aby se při nezměně obrázku dalo
   * cacheovat.
   */
  function avatar() {
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
  function avatarNactiPost($name) {
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
  function avatarSmaz() {
    if (is_file('./soubory/systemove/avatary/' . $this->id() . '.jpg'))
      return unlink('./soubory/systemove/avatary/' . $this->id() . '.jpg');
    else
      return true; //obrázek není -> jakoby se smazal v pohodě
  }

  /**
   * Vrátí / nastaví číslo občanského průkazu.
   */
  function cisloOp($op = null) {
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
  function dejZidli(int $idZidle) {
    if ($this->maZidli($idZidle)) return;

    $novaPrava = dbOneArray('SELECT id_prava FROM r_prava_zidle WHERE id_zidle = $0', [$idZidle]);

    if ($this->maPravo(P_UNIKATNI_ZIDLE) && in_array(P_UNIKATNI_ZIDLE, $novaPrava)) {
      throw new Chyba('Uživatel už má jinou unikátní židli.');
    }

    foreach ($novaPrava as $pravo) {
      if (!$this->maPravo($pravo)) {
        $this->u['prava'][] = (int)$pravo;
        if ($this->klic)
          $_SESSION[$this->klic]['prava'][] = (int)$pravo;
      }
    }

    dbQuery('INSERT IGNORE INTO r_uzivatele_zidle(id_uzivatele,id_zidle)
      VALUES (' . $this->id() . ',' . $idZidle . ')');
  }

  /** Vrátí profil uživatele pro DrD */
  function drdProfil() {
    return $this->medailonek() ? $this->medailonek()->drd() : null;
  }

  /**
   * @return array pole "titulů" u organizátora DrD
   */
  function drdTituly() {
    $tituly = ['Pán Jeskyně', 'vypravěč'];
    if ($this->maPravo(P_TITUL_ORG)) $tituly[] = 'organizátor GC';
    return $tituly;
  }

  /**
   * @return Finance finance daného uživatele
   */
  function finance() {
    //pokud chceme finance poprvé, spočteme je a uložíme
    if (!$this->finance)
      $this->finance = new Finance($this, $this->u['zustatek']);
    return $this->finance;
  }

  /** Vrátí objekt Náhled s fotkou uživatele nebo null */
  function fotka() {
    $soubor = WWW . '/soubory/systemove/fotky/' . $this->id() . '.jpg';
    if (is_file($soubor))
      return Nahled::zSouboru($soubor);
    else
      return null;
  }

  /** Vrátí objekt Náhled s fotkou uživatele nebo výchozí fotku */
  function fotkaAuto() {
    $f = $this->fotka();
    if ($f) return $f;
    elseif ($this->pohlavi() == 'f') return Nahled::zSouboru(WWW . '/soubory/styl/fotka-holka.jpg');
    else                              return Nahled::zSouboru(WWW . '/soubory/styl/fotka-kluk.jpg');
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
  function gcOdhlas() {
    if ($this->gcPritomen()) throw new Exception('Už jsi prošel infopultem, odhlášení není možné.');
    if (!$this->gcPrihlasen()) throw new Exception('Nejsi přihlášen na GameCon.');
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
  }

  /** „Odjede“ uživatele z GC */
  function gcOdjed() {
    if (!$this->gcPritomen()) throw new Exception('Uživatel není přítomen na GC');
    $this->dejZidli(Z_ODJEL);
  }

  /** Opustil uživatel GC? */
  function gcOdjel() {
    if (!$this->gcPritomen()) return false; // ani nedorazil, nemohl odjet
    return $this->maZidli(Z_ODJEL);
  }

  /** Je uživatel přihlášen na aktuální GC? */
  function gcPrihlasen() {
    return $this->maPravo(ID_PRAVO_PRIHLASEN);
  }

  /** Příhlásí uživatele na GC. True pokud je (nebo už byl) přihlášen. */
  function gcPrihlas() {
    if ($this->gcPrihlasen())
      return true;
    else if ($this->dejZidli(Z_PRIHLASEN))
      return true;
    return false;
  }

  /** Prošel uživatel infopultem, dostal materiály a je nebo byl přítomen na aktuálím
   *  GC? */
  function gcPritomen() {
    return $this->maPravo(ID_PRAVO_PRITOMEN);
  }

  /**
   * Nastaví nové heslo (pouze setter)
   */
  function heslo($noveHeslo) {
    $novyHash = password_hash($noveHeslo, PASSWORD_DEFAULT);
    dbQuery('UPDATE uzivatele_hodnoty SET heslo_md5 = $1 WHERE id_uzivatele = $2', [$novyHash, $this->id()]);
  }

  /**
   * @return int[] roky, kdy byl přihlášen na GC
   */
  function historiePrihlaseni() {
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
  function jmeno() {
    return trim($this->u['jmeno_uzivatele'] . ' ' . $this->u['prijmeni_uzivatele']);
  }

  /** Vrátí řetězec s jménem i nickemu uživatele jak se zobrazí např. u
   *  organizátorů aktivit */
  function jmenoNick() {
    return self::jmenoNickZjisti($this->u);
  }

  public function nick(): string {
    return strpos($this->u['login_uzivatele'], '@') === false
      ? $this->u['login_uzivatele']
      : '';
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

  /** Vrátí koncovku "a" pro holky (resp. "" pro kluky) */
  function koncA() {
    if ($this->pohlavi() == 'f') return 'a';
    return '';
  }

  /** Vrátí primární mailovou adresu uživatele */
  function mail() {
    return $this->u['email1_uzivatele'];
  }

  function maPravo($pravo) {
    if (!isset($this->u['prava'])) {
      $this->nactiPrava();
    }
    return in_array($pravo, $this->u['prava']);
  }

  /**
   * @return bool jestli se uživatel v daném čase neúčastní / neorganizuje
   *  žádnou aktivitu (případně s výjimkou $ignorovanaAktivita)
   */
  function maVolno(DateTimeInterface $od, DateTimeInterface $do, Aktivita $ignorovanaAktivita = null) {
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
  function maZidli($zidle): bool {
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
  function mrtvyMail() {
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
  function login(): string {
    return $this->u['login_uzivatele'];
  }

  /** Odhlásí aktuálně přihlášeného uživatele, pokud není přihlášen, nic
   * @param bool $back rovnou otočit na referrer?
   */
  static function odhlas($back = false) {
    self::odhlasProTed();
    if (isset($_COOKIE['gcTrvalePrihlaseni']))
      setcookie('gcTrvalePrihlaseni', '', 0, '/');
    if ($back) back();
  }

  /**
   * Odhlásí aktuálně přihlášeného uživatele
   */
  static function odhlasProTed() {
    if (!session_id())
      session_start();
    session_destroy();
  }

  /** Odpojí od session uživatele na indexu $klic */
  static function odhlasKlic($klic) {
    if (!session_id())
      session_start();
    unset($_SESSION[$klic]);
  }

  /**
   * Odebere uživatele z příjemců pravidelných mail(er)ů
   */
  function odhlasZMaileru() {
    $id = $this->id();
    dbQueryS('UPDATE uzivatele_hodnoty SET nechce_maily = NOW() WHERE id_uzivatele = $1', [$id]);
  }

  /**
   * @return bool Jestli uživatel organizuje danou aktivitu nebo ne.
   */
  function organizuje(Aktivita $a) {
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
  function oSobe() {
    return $this->medailonek() ? $this->medailonek()->oSobe() : null;
  }

  /**
   * Otočí (znovunačte, přihlásí a odhlásí, ...) uživatele
   */
  public function otoc() {
    if (!$this->klic) Throw new Exception('Neznámý klíč uživatele v session');
    $id = $this->id();
    $klic = $this->klic;
    //máme obnovit starou proměnnou pro id uživatele (otáčíme aktuálně přihlášeného uživatele)?
    $sesObnovit = (isset($_SESSION['id_uzivatele']) && $_SESSION['id_uzivatele'] == $this->id());
    if ($klic == 'uzivatel') //pokud je klíč default, zničíme celou session
      self::odhlasProTed(); // ponech případnou cookie pro trvalé přihášení
    else //pokud je speciální, pouze přemažeme položku v session
      self::odhlasKlic($klic);
    $u = Uzivatel::prihlasId($id, $klic);
    $this->u = $u->u;
    if ($sesObnovit) $_SESSION['id_uzivatele'] = $this->id();
  }

  /**
   * Vrátí timestamp začátku posledního bloku kdy uživatel má aktivitu
   */
  function posledniBlok() {
    $cas = dbOneCol('
      SELECT MAX(a.zacatek)
      FROM akce_seznam a
      JOIN akce_prihlaseni p USING(id_akce)
      WHERE p.id_uzivatele = ' . $this->id() . ' AND a.rok = ' . ROK . '
    ');
    return $cas;
  }

  /** Vrátí / nastaví poznámku uživatele */
  function poznamka($poznamka = null) {
    if (isset($poznamka)) {
      dbQueryS('UPDATE uzivatele_hodnoty SET poznamka = $1 WHERE id_uzivatele = $2', [$poznamka, $this->id()]);
      $this->otoc();
    } else {
      return $this->u['poznamka'];
    }
  }

  /** Vrátí formátovanou (html) poznámku uživatele **/
  function poznamkaHtml() {
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
    if (!session_id()) session_start();
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
  function prihlasenJakoNahradnikNa(Aktivita $a) {
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
  function prvniBlok() {
    $cas = dbOneCol('
      SELECT MIN(a.zacatek)
      FROM akce_seznam a
      JOIN akce_prihlaseni p USING(id_akce)
      WHERE p.id_uzivatele = ' . $this->id() . ' AND a.rok = ' . ROK . '
    ');
    return $cas;
  }

  /**
   * Zaregistruje uživatele podle asoc.pole $tab, které by mělo odpovídat stru-
   * ktuře tabulky uzivatele_hodnoty.
   * @return id nově vytvořeného uživatele
   * @todo (jen) pokud bude potřeba další parametry typu "automaticky aktivovat
   * a neposílat aktivační mail" a podobné válce.
   */
  static function registruj($tab) {
    if (!isset($tab['login_uzivatele']) || !isset($tab['email1_uzivatele']))
      throw new Exception('špatný formát $tab (je to pole?)');
    $tab['random'] = $rand = randHex(20);
    dbInsert('uzivatele_hodnoty', array_merge($tab, ['registrovan' => date("Y-m-d H:i:s")]));
    $uid = dbInsertId();
    return $uid;
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
  function sluc(Uzivatel $u, $zmeny = []) {
    $zmeny = array_intersect_key($u->u, array_flip($zmeny));
    $zmeny['zustatek'] = $this->u['zustatek'] + $u->u['zustatek'];

    $slucovani = new UzivatelSlucovani;
    $slucovani->sluc($u, $this, $zmeny);

    // TODO přenačíst aktuálního uživatele
  }

  function status() {
    return trim(strip_tags($this->statusHtml()));
  }

  /** Vrátí html formátovaný „status“ uživatele (pro interní informaci) */
  function statusHtml() {
    $ka = $this->pohlavi() == 'f' ? 'ka' : '';
    $out = '';
    if ($this->maPravo(P_TITUL_ORG)) $out .= '<span style="color:red">Organizátor' . $ka . '</span>, ';
    if ($this->maZidli(Z_ORG_AKCI)) $out .= '<span style="color:blue">Vypravěč' . $ka . '</span>, ';
    if ($this->maZidli(Z_PARTNER)) $out .= "Partner$ka, ";
    if ($this->maZidli(Z_INFO)) $out .= "Infopult, ";
    if ($this->maZidli(Z_ZAZEMI)) $out .= "Zázemí, ";
    if (!$out) $out = 'Účastník, ';
    $out[strlen($out) - 2] = ' ';
    return $out;
  }

  /**
   * Vrátí telefon uživatele v blíže neurčeném formátu
   * @todo specifikovat formát čísla
   */
  function telefon() {
    return $this->u['telefon_uzivatele'];
  }

  /**
   * @return Vrátí url cestu k stránce uživatele (bez domény).
   */
  function url() {
    $url = mb_strtolower($this->u['login_uzivatele']);
    if (!$this->u['jmeno_uzivatele'])
      return null; // nevracet url, asi vypravěčská skupina nebo podobně
    elseif (!Url::povolena($url))
      return 'aktivity?vypravec=' . $this->id();
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

  function id() {
    return isset($this->u['id_uzivatele']) ? $this->u['id_uzivatele'] : null;
  }

  /**
   * Vrátí pohlaví ve tvaru 'm' nebo 'f'
   */
  function pohlavi() {
    return $this->u['pohlavi'];
  }

  function prezdivka() {
    return $this->u['login_uzivatele'];
  }

  /** ISO 3166-1 alpha-2 */
  function stat() {
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
  function rawDb() {
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
    if (strlen($dotaz) < $opt['min']) return [];
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
    $uzivatel = Uzivatel::zWhere('WHERE email1_uzivatele = $1', [$mail]);
    return isset($uzivatel[0]) ? $uzivatel[0] : null;
  }

  static function zNicku($nick) {
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
    } elseif (isset($_COOKIE['gcTrvalePrihlaseni']) && $klic == 'uzivatel') {
      $id = dbOneLineS('
        SELECT id_uzivatele
        FROM uzivatele_hodnoty
        WHERE random!="" AND random=$0',
        [$_COOKIE['gcTrvalePrihlaseni']]);
      $id = $id ? $id['id_uzivatele'] : null;
      //die(dbLastQ());
      if (!$id) return null;
      //změna tokenu do budoucna proti hádání
      dbQuery('
        UPDATE uzivatele_hodnoty
        SET random="' . ($rand = randHex(20)) . '"
        WHERE id_uzivatele=' . $id);
      setcookie('gcTrvalePrihlaseni', $rand, time() + 3600 * 24 * 365, '/');
      return Uzivatel::prihlasId($id, $klic);
    } else {
      return null;
    }
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

}

class DuplicitniEmailException extends Exception
{
}

class DuplicitniLoginException extends Exception
{
}
