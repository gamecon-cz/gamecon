<?php

use Gamecon\Aktivita\Aktivita;
use Gamecon\Aktivita\FiltrAktivity;
use Gamecon\Aktivita\StavPrihlaseni;
use Gamecon\Cache\DataSourcesCollector;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Kanaly\GcMail;
use Gamecon\Logger\Zaznamnik;
use Gamecon\Pravo;
use Gamecon\Role\Role;
use Gamecon\Shop\Shop;
use Gamecon\Stat;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Exceptions\DuplicitniEmail;
use Gamecon\Uzivatel\Exceptions\DuplicitniLogin;
use Gamecon\Uzivatel\Finance;
use Gamecon\Uzivatel\Medailonek;
use Gamecon\Uzivatel\Pohlavi;
use Gamecon\Uzivatel\SqlStruktura\UzivateleHodnotySqlStruktura as Sql;
use Gamecon\Role\RolePodleRocniku;
use Gamecon\Uzivatel\SqlStruktura\PravaRoleSqlStruktura;
use Gamecon\Uzivatel\SqlStruktura\PlatneRoleUzivateluSqlStruktura;
use Gamecon\Uzivatel\UserRepository;
use Gamecon\Uzivatel\UserController;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura;
use Gamecon\Uzivatel\UzivatelSlucovani;

/**
 * Třída popisující uživatele a jeho vlastnosti
 * @method array<Uzivatel> zIds(array | string $ids, bool $zCache = false)
 * @todo načítání separátního (nepřihlášeného uživatele) např. pro účely schi-
 *   zofrenie v adminovi (nehrozí špatný přístup při nadměrném volání např. při
 *   práci s více uživateli někde jinde?)
 */
class Uzivatel extends DbObject
{
    protected static         $tabulka      = Sql::UZIVATELE_HODNOTY_TABULKA;
    protected static ?string $aliasTabulky = 'u';
    protected static         $pk           = Sql::ID_UZIVATELE;
    private static array     $objekty      = [];

    public const POSAZEN = 'posazen';
    public const SESAZEN = 'sesazen';

    public const UZIVATEL_PRACOVNI = 'uzivatel_pracovni';
    public const UZIVATEL          = 'uzivatel';

    public const FAKE         = 0x01;  // modifikátor "fake uživatel"
    public const SYSTEM       = 1;     // id uživatele reprezentujícího systém (např. "operaci provedl systém")
    public const SYSTEM_LOGIN = 'SYSTEM';

    public const TYPY_DOKLADU     = [
        self::TYP_DOKLADU_OP,
        self::TYP_DOKLADU_PAS,
        self::TYP_DOKLADU_JINY,
    ];
    public const TYP_DOKLADU_OP   = 'op';
    public const TYP_DOKLADU_PAS  = 'pas';
    public const TYP_DOKLADU_JINY = 'jiny';

    /** @var array<int, array<int, int|string>> */
    private array  $organizovaneAktivityIds = [];
    private ?array $historiePrihlaseni      = null;

    public static function povinneUdajeProRegistraci(bool $vcetneProUbytovani = false): array
    {
        $povinneUdaje = [
            Sql::JMENO_UZIVATELE    => 'Jméno',
            Sql::PRIJMENI_UZIVATELE => 'Příjmení',
            Sql::TELEFON_UZIVATELE  => 'Telefon',
            Sql::EMAIL1_UZIVATELE   => 'E-mail',
            Sql::LOGIN_UZIVATELE    => 'Přezdívka',
        ];
        if ($vcetneProUbytovani) {
            $povinneUdaje = [
                ...$povinneUdaje,
                ...[
                    Sql::DATUM_NAROZENI         => 'Datum narození',
                    Sql::ULICE_A_CP_UZIVATELE   => 'Ulice a číslo popisné',
                    Sql::MESTO_UZIVATELE        => 'Město',
                    Sql::PSC_UZIVATELE          => 'PSČ',
                    Sql::TYP_DOKLADU_TOTOZNOSTI => 'Typ dokladu totožnosti',
                    Sql::OP                     => 'Číslo dokladu totožnosti',
                    Sql::STATNI_OBCANSTVI       => 'Státní občanství',
                ],
            ];
        }

        return $povinneUdaje;
    }

    /**
     * @return Uzivatel[]
     */
    public static function vsichni(): array
    {
        $ids = dbOneArray(<<<SQL
SELECT DISTINCT uzivatele_hodnoty.id_uzivatele
FROM uzivatele_hodnoty
SQL,
        );

        return static::zIds($ids);
    }

    /**
     * @return Uzivatel[]
     */
    public static function poradateleAktivit(): array
    {
        $ids = dbOneArray(<<<SQL
SELECT DISTINCT uzivatele_hodnoty.id_uzivatele
FROM uzivatele_hodnoty
JOIN platne_role_uzivatelu ON uzivatele_hodnoty.id_uzivatele = platne_role_uzivatelu.id_uzivatele
JOIN prava_role ON platne_role_uzivatelu.id_role = prava_role.id_role
WHERE prava_role.id_prava = $1
SQL
            , [Pravo::PORADANI_AKTIVIT],
        );

        return static::zIds($ids);
    }

    protected ?array                    $aktivityIdsJakoSledujici = null; // pole s klíči id aktvit, kde je jako sledující
    protected ?array                    $prihlaseneAktivityIds    = null; // pole s klíči id aktvit, kde je jako sledující
    protected                           $klic                     = '';
    protected                           $idsRoli;         // pole s klíči id židlí uživatele
    protected Medailonek | false | null $medailonek               = null;
    protected                           $finance;
    protected                           $shop;

    private                    $kdySeRegistrovalNaLetosniGc;
    private SystemoveNastaveni $systemoveNastaveni;

    public function __construct(
        array              $uzivatel,
        SystemoveNastaveni $systemoveNastaveni = null,
    ) {
        if (array_keys_exist(['id_uzivatele'], $uzivatel)) {
            parent::__construct($uzivatel);
            $this->systemoveNastaveni = $systemoveNastaveni ?? SystemoveNastaveni::zGlobals();
        } else {
            throw new Exception('Špatný vstup konstruktoru uživatele');
        }
    }

    /**
     * @return string adresa uživatele ve formátu Město, Ulice ČP, PSČ, stát
     */
    public function adresa()
    {
        $adresa = $this->r['mesto_uzivatele'] . ', ' . $this->r['ulice_a_cp_uzivatele'] . ', ' . $this->r['psc_uzivatele'] . ', ' . $this->stat();

        return $adresa;
    }

    public function ubytovanS(string $ubytovanS = null): string
    {
        if ($ubytovanS !== null) {
            $this->r['ubytovan_s'] = $ubytovanS;
        }

        return $this->r['ubytovan_s'] ?? '';
    }

    /**
     * Vrátí aboslutní adresu avataru včetně http. Pokud avatar neexistuje, vrací
     * default avatar. Pomocí adresy je docíleno, aby se při nezměně obrázku dalo
     * cacheovat.
     */
    public function avatar()
    {
        $soubor = WWW . '/soubory/systemove/avatary/' . $this->id() . '.jpg';
        if (is_file($soubor))
            return Nahled::zeSouboru($soubor)->pasuj(null, 100);
        else
            return self::avatarDefault();
    }

    /**
     * Vrátí defaultní avatar
     */
    public static function avatarDefault()
    {
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
    public function avatarNactiPost($name)
    {
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
    public function avatarSmaz()
    {
        if (is_file('./soubory/systemove/avatary/' . $this->id() . '.jpg'))
            return unlink('./soubory/systemove/avatary/' . $this->id() . '.jpg');
        else
            return true; //obrázek není -> jakoby se smazal v pohodě
    }

    /**
     * Vrátí / nastaví číslo občanského průkazu.
     */
    public function cisloOp(string $op = null)
    {
        if ($op !== null) {
            dbQuery('
        UPDATE uzivatele_hodnoty
        SET op=$1
        WHERE id_uzivatele=' . $this->r['id_uzivatele'],
                [$op !== ''
                     ? Sifrovatko::zasifruj($op)
                     : ''],
            );

            return $op;
        }

        if (!empty($this->r['op'])) {
            return Sifrovatko::desifruj($this->r['op']);
        } else {
            return '';
        }
    }

    /**
     * Vrátí / nastaví číslo občanského průkazu.
     */
    public function typDokladu(string $typDokladu = null): string
    {
        if ($typDokladu !== null) {
            dbQuery('
        UPDATE uzivatele_hodnoty
        SET typ_dokladu_totoznosti=$0
        WHERE id_uzivatele=' . $this->r['id_uzivatele'],
                [0 => $typDokladu],
            );

            return $typDokladu;
        }

        return $this->r[Sql::TYP_DOKLADU_TOTOZNOSTI] ?? '';
    }

    /**
     * Vrátí datum narození uživatele jako DateTime
     */
    public function datumNarozeni(): DateTimeCz
    {
        if ((int)$this->r['datum_narozeni']) //hack, neplatný formát je '0000-00-00'
            return new DateTimeCz($this->r['datum_narozeni']);
        else
            return new DateTimeCz('0001-01-01');
    }

    /**
     * Přidá uživateli roli (posadí uživatele na roli)
     */
    public function pridejRoli(
        int      $idRole,
        Uzivatel $posadil,
    ): bool {
        if ($this->maRoli($idRole)) {
            return false;
        }

        $novaPrava = dbOneArray('SELECT id_prava FROM prava_role WHERE id_role = $0', [$idRole]);

        if ($this->maPravo(Pravo::UNIKATNI_ROLE) && in_array(Pravo::UNIKATNI_ROLE, $novaPrava)) {
            throw new Chyba('Uživatel už má jinou unikátní roli.');
        }

        try {
            $result          = dbQuery(
                "INSERT INTO uzivatele_role(id_uzivatele, id_role, posadil)
            VALUES ($1, $2, $3)",
                [$this->id(), $idRole, $posadil->id()],
            );
            $roleNovePridana = dbAffectedOrNumRows($result) > 0;
            if ($roleNovePridana) {
                $this->zalogujZmenuRole($idRole, $posadil->id(), self::POSAZEN);
            }
        } catch (DbDuplicateEntryException $dbDuplicateEntryException) {
            // roli už má, všechno OK (nechceme INSERT IGNORE protože to by zamlčelo i neexistující roli)
        }

        $this->aktualizujPrava();

        return $roleNovePridana;
    }

    /** Vrátí profil uživatele pro DrD */
    public function drdProfil()
    {
        return $this->medailonek()
            ? $this->medailonek()->drd()
            : null;
    }

    /**
     * @return array pole "titulů" u organizátora DrD
     */
    public function drdTituly()
    {
        $tituly = ['Pán Jeskyně', 'vypravěč'];
        if ($this->maPravo(Pravo::TITUL_ORGANIZATOR)) $tituly[] = 'organizátor GC';

        return $tituly;
    }

    /**
     * @return Finance finance daného uživatele
     */
    public function finance(): Finance
    {
        //pokud chceme finance poprvé, spočteme je a uložíme
        if (!$this->finance) {
            $this->finance = new Finance(
                $this,
                (float)$this->r['zustatek'],
                $this->systemoveNastaveni,
            );
        }

        return $this->finance;
    }

    /** Vrátí objekt Náhled s fotkou uživatele nebo null */
    public function fotka(): ?Nahled
    {
        foreach (glob(WWW . '/soubory/systemove/fotky/' . $this->id() . '.*') as $soubor) {
            if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $soubor)) {
                return Nahled::zeSouboru($soubor);
            }
        }

        return null;
    }

    /** Vrátí objekt Náhled s fotkou uživatele nebo výchozí fotku */
    public function fotkaAuto(): ?Nahled
    {
        $f = $this->fotka();
        if ($f) {
            return $f;
        }
        if ($this->pohlavi() === Pohlavi::ZENA_KOD) {
            return Nahled::zeSouboru(WWW . '/soubory/styl/fotka-holka.jpg');
        }

        return Nahled::zeSouboru(WWW . '/soubory/styl/fotka-kluk.jpg');
    }

    /**
     * Odhlásí uživatele z aktuálního ročníku GameConu, včetně všech předmětů a
     * aktivit.
     * @todo Vyřešit, jak naložit s nedostaveními se na aktivity a podobně (např.
     * při počítání zůstatků a různých jiných administrativních úkolech to toho
     * uživatele může přeskakovat či ignorovat, atd…). Jmenovité problémy:
     * - platby (pokud ho vynecháme při přepočtu zůstatku, přijde o love)
     * @todo Při odhlášení z GC pokud jsou zakázané rušení nákupů může být též problém (k zrušení dojde)
     */
    public function odhlasZGc(
        string    $zdrojOdhlaseni,
        Uzivatel  $odhlasujici,
        Zaznamnik $zaznamnik = null,
        bool      $odeslatMailPokudSeNeodhlasilSam = true,
    ): bool {
        if (!$this->gcPrihlasen()) {
            return false;
        }

        $hlaskyVeTretiOsobe = $this->id() !== $odhlasujici->id();
        if ($this->gcPritomen()) {
            throw new Chyba($hlaskyVeTretiOsobe
                ? "Účastník '{$odhlasujici->jmenoNick()}' už prošel infopultem, odhlášení není možné."
                : 'Už jsi prošel infopultem, odhlášení není možné.');
        }

        foreach ($this->aktivityRyzePrihlasene() as $aktivita) {
            $aktivita->odhlas(
                $this,
                $odhlasujici,
                $zdrojOdhlaseni,
                Aktivita::NEPOSILAT_MAILY_SLEDUJICIM, /* nechceme posílat maily sledujícím, že se uvolnilo místo */
            );
        }

        // finální odebrání role "registrován na GC"
        $this->odeberRoli(Role::PRIHLASEN_NA_LETOSNI_GC, $odhlasujici);
        // zrušení nákupů (až po použití dejShop a ubytovani)
        $this->shop()->zrusVsechnyLetosniObjedavky($zdrojOdhlaseni);

        try {
            $this->informujOOdhlaseni($odhlasujici, $zaznamnik, $odeslatMailPokudSeNeodhlasilSam);
        } catch (\Throwable $throwable) {
            trigger_error($throwable->getMessage() . '; ' . $throwable->getTraceAsString(), E_USER_WARNING);
        }

        $this->otoc();

        return true;
    }

    private function informujOOdhlaseni(
        Uzivatel   $odhlasujici,
        ?Zaznamnik $zaznamnik,
        bool       $odeslatMailPokudSeNeodhlasilSam,
    ) {
        // odeslání upozornění, pokud u nás má peníze
        if (($celkemLetosPoslal = $this->finance()->sumaPlateb()) > 0) {
            $mailOdhlasilAlePlatil = $this->mailOdhlasilAlePlatil($celkemLetosPoslal, $odhlasujici);
            if ($zaznamnik) {
                $zaznamnik->uchovejZEmailu($mailOdhlasilAlePlatil);
            } else {
                $mailOdhlasilAlePlatil->odeslat();
            }
        }
        if ($dnyUbytovani = array_keys($this->shop()->ubytovani()->veKterychDnechJeUbytovan())) {
            $mailMelUbytovani = $this->mailZeBylOdhlasenAMelUbytovani($dnyUbytovani, $odhlasujici);
            if ($zaznamnik) {
                $zaznamnik->uchovejZEmailu($mailMelUbytovani);
            } else {
                if ($this->systemoveNastaveni->poslatMailZeBylOdhlasenAMelUbytovani()) {
                    $mailMelUbytovani->odeslat();
                }
            }
        }
        if ($odeslatMailPokudSeNeodhlasilSam && $this->id() !== $odhlasujici->id()) {
            $mailZeBylOdhlasen = $this->mailZeBylOdhlasen();
            // tento mail necheme zastavovat v záznamníku
            $mailZeBylOdhlasen->odeslat();
        }
    }

    private function mailOdhlasilAlePlatil(
        float    $celkemLetosPoslal,
        Uzivatel $odhlasujici,
    ): GcMail {
        $odhlasen = $this->id() === $odhlasujici->id()
            ? ' se odhlásil'
            : 'byl odhlášen';

        return (new GcMail($this->systemoveNastaveni))
            ->adresat('info@gamecon.cz')
            ->predmet("Uživatel '{$this->jmenoNick()}' ({$this->id()}) $odhlasen ale platil")
            ->text(
                hlaskaMail(
                    'odhlasilPlatil',
                    $this->jmenoNick(),
                    $this->id(),
                    $odhlasen,
                    $this->systemoveNastaveni->rocnik(),
                    $celkemLetosPoslal,
                ),
            );
    }

    private function mailZeBylOdhlasenAMelUbytovani(
        array    $dnyUbytovani,
        Uzivatel $odhlasujici,
    ): GcMail {
        $odhlasen = $this->id() === $odhlasujici->id()
            ? ' se odhlásil'
            : 'byl odhlášen';

        return (new GcMail($this->systemoveNastaveni))
            ->adresat('info@gamecon.cz')
            ->predmet("Uživatel $odhlasen a měl ubytování")
            ->text(
                hlaskaMail(
                    'odhlasilMelUbytovani',
                    $this->jmenoNick(),
                    $this->id(),
                    $odhlasen,
                    $this->systemoveNastaveni->rocnik(),
                    implode(', ', $dnyUbytovani),
                ),
            );
    }

    private function mailZeBylOdhlasen(): GcMail
    {
        $rok                             = $this->systemoveNastaveni->rocnik();
        $nejblizsiHromadneOdhlasovaniKdy = DateTimeGamecon::nejblizsiVlnaKdy(
            $this->systemoveNastaveni,
            $this->systemoveNastaveni->ted(),
        );
        $uvod                            = 'Právě jsme tě odhlásili z letošního Gameconu.';
        $oddelovac                       = str_repeat('═', mb_strlen($uvod));
        set_time_limit(30); // pro jistotu
        $a = $this->koncovkaDlePohlavi('a');

        return (new GcMail($this->systemoveNastaveni))
            ->adresat($this->mail())
            ->predmet("Byl{$a} jsi odhlášen{$a} z Gameconu {$rok}")
            ->text(<<<TEXT
                $uvod

                $oddelovac

                Pokud jsi platbu zapomněl{$a} poslat, přihlaš se zpět v další vlně aktivit, která bude {$nejblizsiHromadneOdhlasovaniKdy->formatCasStandard()} a platbu ohlídej.
                TEXT,
            );
    }

    /**
     * @param int|null $rok
     * @return Aktivita[]
     */
    public function organizovaneAktivity(int $rok = null): array
    {
        $rok ??= $this->systemoveNastaveni->rocnik();

        return Aktivita::zFiltru(
            systemoveNastaveni: $this->systemoveNastaveni,
            filtr: [FiltrAktivity::ROK => $rok, FiltrAktivity::ORGANIZATOR => $this->id()],
            razeni: [AkceSeznamSqlStruktura::ZACATEK],
        );
    }

    /**
     * @param int|null $rok
     * @return Aktivita[]
     */
    public function aktivityRyzePrihlasene(int $rok = null): array
    {
        $rok ??= $this->systemoveNastaveni->rocnik();
        $ids = dbOneArray(<<<SQL
SELECT akce_prihlaseni.id_akce
FROM akce_prihlaseni
JOIN akce_seznam ON akce_prihlaseni.id_akce = akce_seznam.id_akce
WHERE akce_prihlaseni.id_uzivatele = $1
AND akce_prihlaseni.id_stavu_prihlaseni = $2
AND akce_seznam.rok = $3
SQL,
            [$this->id(), StavPrihlaseni::PRIHLASEN, $rok],
        );

        return Aktivita::zIds($ids, $this->systemoveNastaveni);
    }

    /**
     * @param int|null $rok
     * @return Aktivita[]
     */
    public function zapsaneAktivity(int $rok = null): array
    {
        $rok ??= $this->systemoveNastaveni->rocnik();
        $ids = dbOneArray(<<<SQL
SELECT akce_prihlaseni.id_akce
FROM akce_prihlaseni
JOIN akce_seznam ON akce_prihlaseni.id_akce = akce_seznam.id_akce
WHERE akce_prihlaseni.id_uzivatele = $1
AND akce_seznam.rok = $2
SQL,
            [$this->id(), $rok],
        );

        return Aktivita::zIds($ids);
    }

    /** „Odjede“ uživatele z GC */
    public function gcOdjed(Uzivatel $editor)
    {
        if (!$this->gcPritomen()) {
            throw new Chyba('Uživatel není přítomen na GC');
        }
        $this->pridejRoli(Role::ODJEL_Z_LETOSNIHO_GC, $editor);
    }

    /** Opustil uživatel GC? */
    public function gcOdjel(int $rocnik = null): bool
    {
        if ($rocnik === null || $rocnik === $this->systemoveNastaveni->rocnik()) {
            if (!$this->gcPritomen()) {
                return false; // ani nedorazil, nemohl odjet
            }

            return $this->maRoli(Role::ODJEL_Z_LETOSNIHO_GC);
        }

        return $this->maRoli(Role::odjelZRocniku($rocnik));
    }

    /** Je uživatel přihlášen na aktuální GC? */
    public function gcPrihlasen(?DataSourcesCollector $dataSourcesCollector = null): bool
    {
        self::gcPrihlasenDSC($dataSourcesCollector);

        return $this->maRoli(Role::PRIHLASEN_NA_LETOSNI_GC);
    }

    public static function gcPrihlasenDSC(?DataSourcesCollector $dataSourcesCollector)
    {
        $dataSourcesCollector?->addDataSource(Sql::UZIVATELE_HODNOTY_TABULKA);
    }

    /** Příhlásí uživatele na GC */
    public function gcPrihlas(Uzivatel $editor)
    {
        // TODO: kontrola probíhá už v pridejRoli, oddělat všechny tyhle kontroly
        if ($this->gcPrihlasen()) {
            return;
        }

        $this->pridejRoli(Role::PRIHLASEN_NA_LETOSNI_GC, $editor);
    }

    /** Prošel uživatel infopultem, dostal materiály a je nebo byl přítomen na aktuálím
     *  GC? */
    public function gcPritomen(int $rocnik = null): bool
    {
        return $this->maRoli(Role::pritomenNaRocniku($rocnik ?? $this->systemoveNastaveni->rocnik()));
    }

    public function maZkontrolovaneUdaje(int $rocnik = null): bool
    {
        return $this->maRoli(Role::zkontrolovaneUdaje($rocnik ?? $this->systemoveNastaveni->rocnik()));
    }

    public function nastavZkontrolovaneUdaje(
        Uzivatel $editor,
        bool     $udajeZkontrolovane = true,
    ): bool {
        if ($udajeZkontrolovane) {
            return $this->pridejRoli(Role::ZKONTROLOVANE_UDAJE_NA_LETOSNIM_GC, $editor);
        }

        return $this->odeberRoli(Role::ZKONTROLOVANE_UDAJE_NA_LETOSNIM_GC, $editor);
    }

    /**
     * Nastaví nové heslo (pouze setter)
     */
    public function heslo(string $noveHeslo)
    {
        if (PASSWORD_DEFAULT === PASSWORD_BCRYPT && strlen($noveHeslo) > 72) {
            /**
             * https://www.php.net/manual/en/function.password-hash.php#refsect1-function.password-hash-parameters
             */
            throw new Chyba("Heslo nemůže být kvůli technikým omezením delší než 72 znaků");
        }
        $novyHash = password_hash($noveHeslo, PASSWORD_DEFAULT);
        dbQuery('UPDATE uzivatele_hodnoty SET heslo_md5 = $1 WHERE id_uzivatele = $2', [$novyHash, $this->id()]);
    }

    /**
     * @return int[] roky, kdy byl přihlášen na GC
     */
    public function historiePrihlaseni(): array
    {
        if (!isset($this->historiePrihlaseni)) {
            $ucast                    = Role::TYP_UCAST;
            $prihlasen                = Role::VYZNAM_PRIHLASEN;
            $q                        = dbQuery(<<<SQL
SELECT role.rocnik_role
FROM uzivatele_role
JOIN role_seznam AS role
    ON uzivatele_role.id_role = role.id_role
WHERE uzivatele_role.id_uzivatele = $0
    AND role.typ_role = '$ucast'
    AND role.vyznam_role = '$prihlasen'
SQL,
                [$this->id()]);
            $rokyWrapped              = mysqli_fetch_all($q);
            $roky                     = array_map(fn(
                $e,
            ) => (int)$e[0], $rokyWrapped);
            $this->historiePrihlaseni = $roky;
        }

        return $this->historiePrihlaseni;
    }

    /** Jméno a příjmení uživatele v běžném (zákonném) tvaru */
    public function jmeno()
    {
        return trim($this->r['jmeno_uzivatele'] . ' ' . $this->r['prijmeni_uzivatele']);
    }

    /** Vrátí řetězec s jménem i nickemu uživatele jak se zobrazí např. u
     *  organizátorů aktivit */
    public function jmenoNick()
    {
        return self::jmenoNickZjisti($this->r);
    }

    /**
     * Novější formát zápisu jména a příjmení uživatele ve tvaru Jméno Příjmení (Nick).
     * Funkce uvažuje možnou absenci nicku.
     */
    public function jmenoVolitelnyNick()
    {
        if ($this->nick()) {
            return $this->jmeno() . ' (' . $this->nick() . ')';
        } else {
            return $this->jmeno();
        }
    }

    public function nick(): string
    {
        return strpos($this->r['login_uzivatele'], '@') === false
            ? $this->r['login_uzivatele']
            : '';
    }

    public function nickNeboKrestniJmeno(): string
    {
        return $this->nick()
            ?: $this->krestniJmeno()
                ?: $this->jmeno();
    }

    public function krestniJmeno(): string
    {
        return trim($this->r['jmeno_uzivatele']
            ?: '');
    }

    /**
     * Určuje jméno a nick uživatele z pole odpovídajícího strukturou databázovému
     * řádku z tabulky uzivatel_hodnoty. Pokud vyžadovaná pole chybí, zjistí
     * alespoň co se dá.
     * Slouží pro třídy, které si načítání uživatelské identifikace implementují
     * samy, aby nemusely zbytečně načítat celého uživatele. Pokud je to
     * výkonnostně ok, raději se tomu vyhnout a uživatele načíst.
     */
    public static function jmenoNickZjisti(array $r)
    {
        if (!empty($r['jmeno_uzivatele']) && !empty($r['prijmeni_uzivatele'])) {
            $celeJmeno = $r['jmeno_uzivatele'] . ' ' . $r['prijmeni_uzivatele'];
            $jeMail    = str_contains($r['login_uzivatele'], '@');
            if ($celeJmeno == $r['login_uzivatele'] || $jeMail) {
                return $celeJmeno;
            }

            return $r['jmeno_uzivatele'] . ' „' . $r['login_uzivatele'] . '“ ' . $r['prijmeni_uzivatele'];
        }

        return $r['login_uzivatele'];
    }

    /**
     * @param string $jmenoNick
     * @return array{jmeno: string, nick: string|null, prijmeni: string}
     */
    public static function jmenoNickRozloz(string $jmenoNick): array
    {
        if (preg_match('~^(?<jmeno>[^„]*)„(?<nick>[^“]+)“(?<prijmeni>.*)$~u', $jmenoNick, $matches)) {
            return [
                'jmeno'    => trim($matches['jmeno']),
                'nick'     => trim($matches['nick']),
                'prijmeni' => trim($matches['prijmeni']),
            ];
        }

        $parts    = explode(' ', $jmenoNick);
        $prijmeni = trim(array_pop($parts) ?? '');
        $jmeno    = implode(' ', array_map('trim', $parts));
        if ($jmeno !== '' && $prijmeni !== '') {
            return [
                'jmeno'    => $jmeno,
                'nick'     => null,
                'prijmeni' => $prijmeni,
            ];
        }

        return [
            'jmeno'    => null,
            'nick'     => $prijmeni, // pokud nemáme jméno a příjmení, tak to považujeme za sólo nick
            'prijmeni' => null,
        ];
    }

    /**
     * Vrátí koncovku "a" pro holky (resp. "" pro kluky)
     * @deprecated use \Uzivatel::koncovkaDlePohlavi instead
     */
    public function koncA(): string
    {
        return ($this->pohlavi() === Pohlavi::ZENA_KOD)
            ? 'a'
            : '';
    }

    /** Vrátí koncovku "a" pro holky (resp. "" pro kluky) */
    public function koncovkaDlePohlavi(string $koncovkaProZeny = 'a'): string
    {
        return Pohlavi::koncovkaDlePohlavi($this->pohlavi(), $koncovkaProZeny);
    }

    /** Vrátí primární mailovou adresu uživatele */
    public function mail()
    {
        return $this->r['email1_uzivatele'];
    }

    /**
     * @return string[] povinné údaje které chybí
     */
    public function chybejiciUdaje(array $povinneUdaje): array
    {
        $validator = fn(
            string $sloupec,
        ) => (trim((string)$this->r[$sloupec] ?? '')) === '';

        return array_filter($povinneUdaje, $validator, ARRAY_FILTER_USE_KEY);
    }

    public function maPravo(
        $pravo,
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): bool {
        return in_array($pravo, $this->prava($dataSourcesCollector));
    }

    public static function maPravoDSC(
        ?DataSourcesCollector $dataSourcesCollector,
    ): void {
        self::pravaDSC($dataSourcesCollector);
    }

    public function maPravoNaPrirazeniRole(int $idRole): bool
    {
        $role = Role::zId($idRole, true);
        if (!$role) {
            return false;
        }

        return $this->maPravoNaZmenuKategorieRole($role->kategorieRole());
    }

    public function maPravoNaZmenuKategorieRole(int $kategorieRole): bool
    {
        return match ($kategorieRole) {
            Role::KATEGORIE_OMEZENA => $this->maRoli(Role::CLEN_RADY),
            Role::KATEGORIE_BEZNA   => true,
            default                 => throw new \Gamecon\Role\Exceptions\NeznamaKategorieRole(
                "Kategorie $kategorieRole je neznámá",
            )
        };
    }

    public function maPravoNaZmenuPrav(): bool
    {
        return $this->maPravo(Pravo::ZMENA_PRAV);
    }

    public function maPravoNaPristupDoPrezence(): bool
    {
        return $this->maPravo(Pravo::ADMINISTRACE_PREZENCE);
    }

    public function maPravoNaKostkuZdarma(): bool
    {
        return $this->maPravo(Pravo::KOSTKA_ZDARMA);
    }

    public function maPravoNaPlackuZdarma(): bool
    {
        return $this->maPravo(Pravo::PLACKA_ZDARMA);
    }

    public function maPravoNaUbytovaniZdarma(): bool
    {
        return $this->maPravo(Pravo::UBYTOVANI_ZDARMA);
    }

    public function maPravoNaJidloZdarma(): bool
    {
        return $this->maPravo(Pravo::JIDLO_ZDARMA);
    }

    /**
     * Což taky znamená "Právo na placení až na místě"
     * @return bool
     */
    public function maPravoNerusitObjednavky(): bool
    {
        return $this->maPravo(Pravo::NERUSIT_AUTOMATICKY_OBJEDNAVKY);
    }

    public function nemaPravoNaBonusZaVedeniAktivit(): bool
    {
        return $this->maPravo(Pravo::BEZ_SLEVY_ZA_VEDENI_AKTIVIT);
    }

    public function maPravoNaBonusZaVedeniAktivit(): bool
    {
        return !$this->nemaPravoNaBonusZaVedeniAktivit();
    }

    public function maPravoNaPoradaniAktivit(): bool
    {
        return $this->maPravo(Pravo::PORADANI_AKTIVIT);
    }

    public function maPravoNaStrankuFinance(): bool
    {
        return $this->maPravo(Pravo::ADMINISTRACE_FINANCE);
    }

    public function maPravoNaZmenuHistorieAktivit(): bool
    {
        return $this->maPravo(Pravo::ZMENA_HISTORIE_AKTIVIT);
    }

    public function maPravoNaPrihlasovaniNaDosudNeotevrene(): bool
    {
        return $this->maPravo(Pravo::PRIHLASOVANI_NA_DOSUD_NEOTEVRENE);
    }

    public function jeBrigadnik(): bool
    {
        return $this->maRoli(Role::LETOSNI_BRIGADNIK);
    }

    public function jeZazemi(): bool
    {
        return $this->maRoli(Role::LETOSNI_ZAZEMI);
    }

    public function jeVypravec(): bool
    {
        return $this->maRoli(Role::LETOSNI_VYPRAVEC);
    }

    public function jeVypravecskaSkupina(): bool
    {
        return $this->maRoli(Role::VYPRAVECSKA_SKUPINA);
    }

    public function jeOrganizator(): bool
    {
        return Role::obsahujiOrganizatora($this->dejIdsRoli());
    }

    public function jePartner(): bool
    {
        return $this->maRoli(Role::LETOSNI_PARTNER);
    }

    public function jeInfopultak(): bool
    {
        return $this->maRoli(Role::LETOSNI_INFOPULT);
    }

    public function jeHerman(): bool
    {
        return $this->maRoli(Role::LETOSNI_HERMAN);
    }

    public function jeSpravceFinanci(): bool
    {
        return $this->maRoli(Role::CFO);
    }

    public function jeCestnyOrg(): bool
    {
        return $this->maRoli(Role::CESTNY_ORGANIZATOR);
    }

    public function jeSuperAdmin(): bool
    {
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
     * @return Aktivita|null jestli se uživatel v daném čase neúčastní / neorganizuje
     *  žádnou aktivitu (případně s výjimkou $ignorovanaAktivita)
     */
    public function maKoliziSJinouAktivitou(
        DateTimeInterface $od,
        DateTimeInterface $do,
        Aktivita          $ignorovanaAktivita = null,
        bool              $jenPritomen = false,
    ): ?Aktivita {
        // právo na překrytí aktivit dává volno vždy automaticky
        // TODO zkontrolovat, jestli vlastníci práva dřív měli někdy paralelně i účast nebo jen organizovali a pokud jen organizovali, vyhodit test odsud a vložit do kontroly kdy se ukládá aktivita
        if ($this->maPravo(Pravo::PREKRYVANI_AKTIVIT)) {
            return null;
        }

        if ($kolizniAktivita = $this->dejKolizniAktivitu($this->zapsaneAktivity(), $od, $do, $ignorovanaAktivita, $jenPritomen)) {
            return $kolizniAktivita;
        }

        if ($kolizniAktivita = $this->dejKolizniAktivitu($this->organizovaneAktivity(), $od, $do, $ignorovanaAktivita, $jenPritomen)) {
            return $kolizniAktivita;
        }

        return null;
    }

    /**
     * @param Aktivita[] $aktivity
     */
    private function dejKolizniAktivitu(
        array             $aktivity,
        DateTimeInterface $od,
        DateTimeInterface $do,
        ?Aktivita         $ignorovanaAktivita,
        bool              $jenPritomen,
    ): ?Aktivita {
        $ignorovanaAktivitaId = $ignorovanaAktivita
            ? $ignorovanaAktivita->id()
            : false;
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
                if ($jenPritomen) {
                    return $aktivita->dorazilJakoCokoliv($this)
                        ? $aktivita
                        : null;
                }

                return $aktivita;
            }
        }

        return null;
    }

    /**
     * Sedí uživatel na dané roli?
     * NEslouží k čekování vlastností uživatele, které obecně řeší práva resp.
     * Uzivatel::maPravo(), skutečně výhradně k správě židlí jako takových.
     * @todo při načítání práv udělat pole místo načítání z DB
     */
    public function maRoli($role): bool
    {
        $idRole = (int)$role;
        if (!$idRole) {
            return false;
        }

        return in_array($idRole, $this->dejIdsRoli(), true);
    }

    public function maRoliClenRady(): bool
    {
        return $this->maRoli(Role::CLEN_RADY);
    }

    public function maRoliSefProgramu(): bool
    {
        return $this->maRoli(Role::SEF_PROGRAMU);
    }

    public function jeSefInfopultu(): bool
    {
        return $this->maRoli(Role::SEF_INFOPULTU);
    }

    /**
     * @return int[]
     */
    public function dejIdsRoli(): array
    {
        if (!isset($this->idsRoli)) {
            $role          = dbOneArray('SELECT id_role FROM platne_role_uzivatelu WHERE id_uzivatele = ' . $this->id());
            $this->idsRoli = array_map('intval', $role);
        }

        return $this->idsRoli;
    }

    protected function medailonek(): ?Medailonek
    {
        if (!isset($this->medailonek)) {
            $this->medailonek = Medailonek::zId($this->id()) ?? false;
        }

        return $this->medailonek
            ?: null;
    }

    /**
     * Jestli je jeho mail mrtvý
     * @todo pokud bude výkonově ok, možno zrefaktorovat na třídu mail která bude
     * mít tento atribut
     */
    public function mrtvyMail()
    {
        return $this->r['mrtvy_mail'];
    }

    /**
     * Ručně načte práva - neoptimalizovaná varianta, přijatelné pouze pro prasečí
     * řešení, kde si to můžeme dovolit (=reporty)
     */
    public function nactiPrava(?DataSourcesCollector $dataSourcesCollector = null): void
    {
        self::nactiPravaDSC($dataSourcesCollector);

        if (isset($this->r['prava'])) {
            return;
        }
        //načtení uživatelských práv
        $p     = dbQuery(<<<SQL
                SELECT prava_role.id_prava
                FROM platne_role_uzivatelu
                LEFT JOIN prava_role USING(id_role)
                WHERE platne_role_uzivatelu.id_uzivatele=$0
                SQL,
            [0 => $this->id()],
        );
        $prava = []; //inicializace nutná, aby nepadala výjimka pro uživatele bez práv
        while ($r = mysqli_fetch_assoc($p)) {
            $prava[] = (int)$r['id_prava'];
        }
        $this->r['prava'] = $prava;
    }

    public static function nactiPravaDSC(?DataSourcesCollector $dataSourcesCollector): void
    {
        $dataSourcesCollector?->addDataSources([
            PravaRoleSqlStruktura::PRAVA_ROLE_TABULKA,
            PlatneRoleUzivateluSqlStruktura::PLATNE_ROLE_UZIVATELU_TABULKA,
        ]);
    }

    public function prava(?DataSourcesCollector $dataSourcesCollector = null): array
    {
        self::pravaDSC($dataSourcesCollector);

        if (!isset($this->r['prava'])) {
            $this->nactiPrava($dataSourcesCollector);
        } elseif (is_string($this->r['prava'])) {
            $this->r['prava'] = array_map('intval', explode(',', $this->r['prava']));
        }

        return $this->r['prava'];
    }

    public static function pravaDSC(?DataSourcesCollector $dataSourcesCollector): void
    {
        self::nactiPravaDSC($dataSourcesCollector);
    }

    public function potvrzeniZakonnehoZastupceOd(): ?DateTimeImmutable
    {
        $potvrzeniOdString = $this->r[Sql::POTVRZENI_ZAKONNEHO_ZASTUPCE];

        return $potvrzeniOdString
            ? new \DateTimeImmutable($potvrzeniOdString)
            : null;
    }

    public function potvrzeniZakonnehoZastupceSouborOd(): ?DateTimeImmutable
    {
        $potvrzeniOdString = $this->r[Sql::POTVRZENI_ZAKONNEHO_ZASTUPCE_SOUBOR];

        return $potvrzeniOdString
            ? new \DateTimeImmutable($potvrzeniOdString)
            : null;
    }

    /** Vrátí přezdívku (nickname) uživatele */
    public function login(): string
    {
        return $this->r['login_uzivatele'];
    }

    /** Odhlásí aktuálně přihlášeného uživatele, pokud není přihlášen, nic
     * @param bool $naUvodniStranku
     */
    public function odhlas(bool $naUvodniStranku = true)
    {
        $a = $this->koncovkaDlePohlavi();
        $this->odhlasProTed();
        if (isset($_COOKIE['gcTrvalePrihlaseni'])) {
            setcookie('gcTrvalePrihlaseni', '', 0, '/');
        }
        oznameni("Byl$a jsi odhlášen$a", false);
        if ($naUvodniStranku) {
            back(URL_WEBU);
        }
    }

    /**
     * Odhlásí aktuálně přihlášeného uživatele
     */
    private function odhlasProTed(): void
    {
        if (!session_id()) {
            session_start();
        }
        session_destroy();
    }

    /** Odpojí od session uživatele na indexu $klic */
    public static function odhlasKlic(string $klic): void
    {
        if (!session_id()) {
            session_start();
        }
        unset($_SESSION[$klic]);
    }

    /**
     * Odebere uživatele z příjemců pravidelných mail(er)ů
     */
    public function odhlasZMaileru()
    {
        $id = $this->id();
        dbQueryS('UPDATE uzivatele_hodnoty SET nechce_maily = NOW() WHERE id_uzivatele = $1', [$id]);
    }

    /**
     * @return bool Jestli uživatel organizuje danou aktivitu nebo ne.
     */
    public function organizuje(Aktivita $a): bool
    {
        if (!isset($this->organizovaneAktivityIds[$a->rok()])) {
            $this->organizovaneAktivityIds[$a->rok()] = dbOneIndex(<<<SQL
                SELECT akce_seznam.id_akce
                FROM akce_organizatori
                JOIN akce_seznam
                    ON akce_seznam.id_akce = akce_organizatori.id_akce AND akce_seznam.rok = {$a->rok()}
                WHERE akce_organizatori.id_uzivatele = {$this->id()}
                SQL,
            );
        }

        return isset($this->organizovaneAktivityIds[$a->rok()][$a->id()]);
    }

    /** Vrátí medailonek vypravěče */
    public function oSobe()
    {
        return $this->medailonek()
            ? $this->medailonek()->oSobe()
            : null;
    }

    /**
     * Otočí (znovunačte, přihlásí a odhlásí, ...) uživatele
     */
    public function otoc(): void
    {
        if (PHP_SAPI === 'cli') {
            $this->r = self::zId($this->id())->r;

            return;
        }

        if (!$this->klic) {
            return; // uživatel nebyl přihlášen
        }
        $id   = $this->id();
        $klic = $this->klic;
        // máme obnovit starou proměnnou pro id uživatele (otáčíme aktuálně přihlášeného uživatele)?
        $sesssionObnovit = (isset($_SESSION['id_uzivatele']) && $_SESSION['id_uzivatele'] == $this->id());
        if ($klic === self::UZIVATEL) { // pokud je klíč default, zničíme celou session
            $this->odhlasProTed();      // ponech případnou cookie pro trvalé přihášení
        } else { // pokud je speciální, pouze přemažeme položku v session
            self::odhlasKlic($klic);
        }
        $u       = Uzivatel::prihlasId($id, $klic);
        $this->r = $u->r;
        if ($sesssionObnovit) {
            $_SESSION['id_uzivatele'] = $this->id();
        }
    }

    /**
     * Vrátí timestamp začátku posledního bloku kdy uživatel má aktivitu
     */
    public function posledniBlok(): ?string
    {
        $cas = dbOneCol(<<<SQL
            SELECT MAX(a.zacatek)
            FROM akce_seznam a
            JOIN akce_prihlaseni p USING(id_akce)
            WHERE p.id_uzivatele = {$this->id()} AND a.rok = {$this->systemoveNastaveni->rocnik()}
            SQL,
        );

        return $cas;
    }

    /** Vrátí / nastaví poznámku uživatele */
    public function poznamka($poznamka = null)
    {
        if (isset($poznamka)) {
            dbQueryS('UPDATE uzivatele_hodnoty SET poznamka = $1 WHERE id_uzivatele = $2', [$poznamka, $this->id()]);
            $this->otoc();

            return $poznamka;
        }

        return $this->r['poznamka'];
    }

    public function balicekHtml(): string
    {
        if (!$this->gcPrihlasen()) {
            return '';
        }
        $shop                = $this->shop();
        $objednalNejakeJidlo = $shop->objednalNejakeJidlo();
        $hintedParts         = [];
        $hintParts           = [];

        if ($this->jeBrigadnik()) {
            $hintedParts[] = 'papír na bonus ✍️';
            $hintParts[]   = 'podepsat papír na převzetí bonusu';
        }

        if (!$shop->koupilNejakouVec()) {
            if ($objednalNejakeJidlo) {
                $hintedParts[] = 'jen stravenky 🍽️';
                $hintParts[]   = $shop->objednaneJidloPrehledHtml();
            }
            if (count($hintedParts) === 0) {
                return '';
            }
            $hint   = $this->joinHint($hintParts);
            $hinted = $this->joinHinted($hintedParts);

            return <<<HTML
                  <span class="hinted">{$hinted}<span class="hint">{$hint}</span></span>
                HTML;
        }

        $velikostBalicku = $this->r['infopult_poznamka'] === 'velký balíček ' . $this->systemoveNastaveni->rocnik()
            ? 'velký balíček'
            : 'balíček';
        $nakupy          = [];
        $nakupy[]        = $shop->koupeneVeciPrehledHtml();
        if ($objednalNejakeJidlo) {
            $nakupy[] = $shop->objednaneJidloPrehledHtml();
        }
        $nakupyHtml    = implode('<hr>', $nakupy);
        $hintedParts[] = htmlentities($velikostBalicku) . ' ' . $this->id();
        $hintParts[]   = $nakupyHtml;

        $hint   = $this->joinHint($hintParts);
        $hinted = $this->joinHinted($hintedParts);

        return <<<HTML
            <span class="hinted">{$hinted}<span class="hint">{$hint}</span></span>
        HTML;
    }

    private function joinHint(array $hintParts): string
    {
        return implode('<hr>', array_map('ucfirst', $hintParts));
    }

    private function joinHinted(array $hintedParts): string
    {
        return implode('<br>', array_map('ucfirst', $hintedParts));
    }

    /**
     * Přihlásí uživatele s loginem $login k stránce
     * @param string $klic klíč do $_SESSION kde poneseme hodnoty uživatele
     * @param string $login login nebo primární e-mail uživatele
     * @param string $heslo heslo uživatele
     * @return mixed objekt s uživatelem nebo null
     */
    public static function prihlas(
        string $login,
        string $heslo,
        string $klic = self::UZIVATEL,
    ): ?Uzivatel {
        if (!$login || !$heslo) {
            return null;
        }

        $uzivatelData = dbOneLine(
            'SELECT * FROM uzivatele_hodnoty
            WHERE login_uzivatele = $0 OR email1_uzivatele = $0
            ORDER BY email1_uzivatele = $0 DESC -- e-mail má prioritu
            LIMIT 1',
            [$login],
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
        $p     = dbQuery(<<<SQL
SELECT id_prava
FROM platne_role_uzivatelu
    LEFT JOIN prava_role ON platne_role_uzivatelu.id_role = prava_role.id_role
WHERE platne_role_uzivatelu.id_uzivatele={$idUzivatele}
SQL,
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
    public static function prihlasId(
        int | string $idUzivatele,
                     $klic = self::UZIVATEL,
    ): ?Uzivatel {
        $uzivatelData = dbOneLine("SELECT * FROM uzivatele_hodnoty WHERE id_uzivatele={$idUzivatele}", [$idUzivatele]);
        if (!$uzivatelData) {
            return null;
        }
        if (!session_id()) {
            session_start();
        }
        $_SESSION[$klic]['id_uzivatele'] = $idUzivatele;
        //načtení uživatelských práv
        $p     = dbQuery(
            'SELECT id_prava FROM platne_role_uzivatelu uz LEFT JOIN prava_role pz USING(id_role) WHERE uz.id_uzivatele=' . $idUzivatele,
        );
        $prava = []; //inicializace nutná, aby nepadala výjimka pro uživatele bez práv
        while ($r = mysqli_fetch_assoc($p)) {
            $prava[] = (int)$r['id_prava'];
        }
        $uzivatelData['prava'] = $prava;
        $uzivatel              = new Uzivatel($uzivatelData);
        $uzivatel->klic        = $klic;

        return $uzivatel;
    }

    /** Alias prihlas() pro trvalé přihlášení */
    public static function prihlasTrvale(
        $login,
        $heslo,
        $klic = self::UZIVATEL,
    ) {
        $u    = Uzivatel::prihlas($login, $heslo, $klic);
        $rand = randHex(20);
        if ($u) {
            dbQuery(
                'UPDATE uzivatele_hodnoty
                SET random=$0
                WHERE id_uzivatele=' . $u->id(),
                [$rand],
            );
            setcookie('gcTrvalePrihlaseni', $rand, time() + 3600 * 24 * 365, '/');
        }

        return $u;
    }

    /**
     * @return bool true, pokud je uživatel přihlášen na aktivitu (ač ještě nežačala, nebo už dorazil jako cokoli)
     */
    public function prihlasen(Aktivita $a): bool
    {
        if (!isset($this->prihlaseneAktivityIds)) {
            $this->prihlaseneAktivityIds = dbOneIndex(<<<SQL
                SELECT id_akce
                FROM akce_prihlaseni
                WHERE id_uzivatele = $0
                SQL,
                [0 => $this->id()],
            );
        }

        return isset($this->prihlaseneAktivityIds[$a->id()]);
    }

    /**
     * @return bool true, pokud je uživatel přihlášen jako sledující aktivity (ve watchlistu).
     */
    public function prihlasenJakoSledujici(Aktivita $a): bool
    {
        if (!isset($this->aktivityIdsJakoSledujici)) {
            $this->aktivityIdsJakoSledujici = dbOneIndex("
        SELECT id_akce
        FROM akce_prihlaseni_spec
        WHERE id_uzivatele = $0 AND id_stavu_prihlaseni = $1
      ", [$this->id(), StavPrihlaseni::SLEDUJICI]);
        }

        return isset($this->aktivityIdsJakoSledujici[$a->id()]);
    }

    public function dorazilJakoNahradnik(Aktivita $aktivita)
    {
        return $aktivita->dorazilJakoNahradnik($this);
    }

    /**
     * Vrátí timestamp prvního bloku kdy uživatel má aktivitu
     */
    public function prvniBlok(): ?string
    {
        $prvniBlok = dbOneCol(<<<SQL
            SELECT MIN(a.zacatek)
                FROM akce_seznam a
                    JOIN akce_prihlaseni p USING(id_akce)
                WHERE p.id_uzivatele = {$this->id()} AND a.rok = {$this->systemoveNastaveni->rocnik()}
            SQL,
        );

        return $prvniBlok
            ? (string)$prvniBlok
            : null;
    }

    /**
     * Zaregistruje uživatele podle asoc.pole $tab, které by mělo odpovídat
     * struktuře tabulky uzivatele_hodnoty.
     *
     * Extra položky: heslo a heslo_kontrola (metoda si je sama převede na hash).
     *
     * @return int|null id nově vytvořeného uživatele
     */
    public static function registruj(array $tab): ?int
    {
        $idNeboHlaska = self::registrujUprav($tab, null);
        if (is_numeric($idNeboHlaska)) {
            return (int)$idNeboHlaska;
        }
        if ($idNeboHlaska === '') {
            return null;
        }
        throw new Chyba($idNeboHlaska);
    }

    /**
     * Zregistruje nového uživatele nebo upraví stávajícího $u, pokud je zadán.
     *
     * @return string id nově vytvořeného nebo upraveného uživatele nebo hláška s chybou
     */
    private static function registrujUprav(
        array     $tab,
        ?Uzivatel $u,
    ): string {
        $dbTab                  = $tab;
        $chyby                  = [];
        $preskocitChybejiciPole = (bool)$u;

        // opravy
        $dbTab = array_map(static function (
            $hodnota,
        ) {
            return preg_replace('/\s+/', ' ', trim((string)$hodnota));
        }, $dbTab);

        if (isset($dbTab[Sql::EMAIL1_UZIVATELE])) {
            $dbTab[Sql::EMAIL1_UZIVATELE] = mb_strtolower($dbTab[Sql::EMAIL1_UZIVATELE]);
        }

        // TODO fallback prázdná přezdívka -> mail?

        // validátory
        $validaceLoginu = function (
            $login,
        ) use
        (
            $u,
        ) {
            if (empty($login)) {
                return 'vyber si prosím přezdívku';
            }

            $u2 = Uzivatel::zNicku($login) ?? Uzivatel::zEmailu($login);
            if ($u2 && !$u) {
                return 'přezdívka už je zabraná; pokud je tvoje, přihlaš se nebo si resetuj heslo';
            }
            if ($u2 && $u && $u2->id() != $u->id()) {
                return 'přezdívka už je zabraná, vyber si prosím jinou';
            }

            return '';
        };

        $validaceMailu = function (
            $mail,
        ) use
        (
            $u,
        ) {
            if (!preg_match('/^[a-z0-9_\-.+]+@[a-z0-9_\-.]+\.[a-z]+$/', $mail)) {
                return 'zadej prosím platný e-mail';
            }

            $u2 = Uzivatel::zNicku($mail) ?? Uzivatel::zEmailu($mail);
            if ($u2 && !$u) {
                return 'e-mail už máš zaregistrovaný. Přihlaš se nebo si resetuj heslo';
            }
            if ($u2 && $u && $u2->id() != $u->id()) {
                return 'e-mail už je zabraný. Pokud je tvůj, resetuj si heslo';
            }

            return '';
        };

        $validaceDataNarozeni = function (
            $datum,
        ) {
            // přichází ve formátu rrrr-mm-dd
            if (!DateTimeImmutable::createFromFormat('Y-m-d', trim((string)$datum))) {
                return 'vyplň prosím platné datum narození';
            }

            return '';
        };

        $validaceHesla = function (
            $heslo,
        ) use
        (
            $dbTab,
        ) {
            if (empty($heslo)) {
                return 'vyplň prosím heslo';
            }

            if (
                $heslo != ($dbTab['heslo'] ?? null) ||
                $heslo != ($dbTab['heslo_kontrola'] ?? null)
            ) {
                return 'hesla se neshodují';
            }

            return '';
        };

        $dbTab = self::spojPredvolbuSTelefonem($dbTab);

        $validace = [
            // Osobní
            Sql::EMAIL1_UZIVATELE       => $validaceMailu,
            Sql::TELEFON_UZIVATELE      => ['^[\d \+]+$', 'vyplň prosím telefon, např. +420 789 123 456'],
            Sql::JMENO_UZIVATELE        => ['.+', 'jméno nesmí být prázdné'],
            Sql::PRIJMENI_UZIVATELE     => ['.+', 'příjmení nesmí být prázdné'],
            Sql::DATUM_NAROZENI         => $validaceDataNarozeni,
            Sql::STATNI_OBCANSTVI       => ['[[:alpha:]]{2,}', 'vyplň prosím státní občanství'],
            // Adresa trvalého pobytu
            Sql::ULICE_A_CP_UZIVATELE   => ['.+ [\d\/a-z]+$', 'vyplň prosím ulici, např. Česká 27'],
            Sql::MESTO_UZIVATELE        => ['.+', 'vyplň prosím město'],
            Sql::PSC_UZIVATELE          => ['^[\d ]+$', 'vyplň prosím PSČ, např. 602 00'],
            Sql::STAT_UZIVATELE         => ['^(1|2|-1)$', 'vyber prosím stát'],
            // Platný doklad totožnosti
            Sql::TYP_DOKLADU_TOTOZNOSTI => [implode('|', self::TYPY_DOKLADU), 'vyber prosím typ dokladu totožnosti'],
            Sql::OP                     => ['[a-zA-Z0-9]{5,}', 'vyplň prosím celé číslo dokladu'],
            // Ostatní
            Sql::LOGIN_UZIVATELE        => $validaceLoginu,
            Sql::POHLAVI                => ['^(m|f)$', 'vyber prosím pohlaví'],
            'heslo'                     => $validaceHesla,
            'heslo_kontrola'            => $validaceHesla,
        ];

        // provedení validací
        $navic = array_diff(array_keys($dbTab), array_keys($validace));
        if ($navic) {
            throw new Exception('Data obsahují nepovolené hodnoty: ' . implode(',', $navic));
        }

        $povinneUdaje = self::povinneUdajeProRegistraci(
            $u?->shop()->ubytovani()->maObjednaneUbytovani() ?? false,
        );

        foreach ($validace as $klic => $validator) {
            $hodnota = $dbTab[$klic] ?? null;

            if ($hodnota === null && $preskocitChybejiciPole) {
                continue;
            }
            $hodnota = trim((string)$hodnota);
            if ($hodnota === '') {
                $povinne = in_array($klic, ['heslo', 'heslo_kontrola'])
                           || array_key_exists($klic, $povinneUdaje);
                if (!$povinne) {
                    continue;
                }
            }

            if (is_array($validator)) {
                $regex      = $validator[0];
                $popisChyby = $validator[1];
                if (!preg_match("/$regex/u", $hodnota)) {
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
            $ch->globalniChyba(
                $u
                    ? 'Úprava se nepodařila, oprav prosím zvýrazněné položky.'
                    : 'Registrace se nepodařila. Oprav prosím zvýrazněné položky.',
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

        if (isset($dbTab[Sql::OP])) {
            $dbTab[Sql::OP] = Sifrovatko::zasifruj($dbTab[Sql::OP]);
        }

        // uložení
        if ($u) {
            dbUpdate(Sql::UZIVATELE_HODNOTY_TABULKA, $dbTab, [Sql::ID_UZIVATELE => $u->id()]);
            $u->otoc();
            $idUzivatele  = $u->id();
            $urlUzivatele = self::vytvorUrl($u->r);
        } else {
            dbInsert(Sql::UZIVATELE_HODNOTY_TABULKA, $dbTab);
            $idUzivatele              = dbInsertId();
            $dbTab[Sql::ID_UZIVATELE] = $idUzivatele;
            $urlUzivatele             = self::vytvorUrl($dbTab);
        }
        if ($urlUzivatele !== null) {
            dbInsertUpdate('uzivatele_url', ['id_uzivatele' => $idUzivatele, 'url' => $urlUzivatele]);
        }

        return $idUzivatele;
    }

    protected static function spojPredvolbuSTelefonem(array $data): array
    {
        $telefon   = $data[Sql::TELEFON_UZIVATELE] ?? null;
        $predvolba = $data['predvolba'] ?? null;
        unset($data['predvolba']); // v dalším zpracování dat by předvolba byla považována za neznámý klíč a chybu

        if (empty($telefon) || empty($predvolba)) {
            return $data;
        }

        $data[Sql::TELEFON_UZIVATELE] = $predvolba . ' ' . $telefon;

        return $data;
    }

    /**
     * Rychloregistruje uživatele s omezeným počtem údajů při registraci na místě.
     * @return int id nově vytvořeného uživatele (možno vytvořit objekt uživatele
     *  později jen pokud má smysl - výkonnostní důvody)
     * @todo možno evidovat, že uživatel byl regnut na místě
     * @todo poslat mail s něčím jiným jak std hláškou
     */
    public static function rychloregistrace(
        SystemoveNastaveni $systemoveNastaveni,
        array              $tab = [],
        array              $opt = [],
    ) {
        $tab[Sql::LOGIN_UZIVATELE]                     ??= uniqid('RR.', false);
        $tab[Sql::JMENO_UZIVATELE]                     ??= $tab[Sql::LOGIN_UZIVATELE];
        $tab[Sql::PRIJMENI_UZIVATELE]                  ??= $tab[Sql::JMENO_UZIVATELE];
        $tab[Sql::EMAIL1_UZIVATELE]                    ??= $tab[Sql::LOGIN_UZIVATELE] . '@example.com';
        $tab[Sql::Z_RYCHLOREGISTRACE]                  = 1;
        $tab[Sql::DATUM_NAROZENI]                      ??= date('Y-m-d');
        $tab[Sql::STAT_UZIVATELE]                      ??= Stat::CZ_ID;
        $tab[Sql::RANDOM]                              = $rand = randHex(20);
        $tab[Sql::REGISTROVAN]                         = date("Y-m-d H:i:s");
        $tab[Sql::ID_UZIVATELE]                        = null;
        $tab[Sql::NECHCE_MAILY]                        = null;
        $tab[Sql::MRTVY_MAIL]                          = 0;
        $tab[Sql::ZUSTATEK]                            = 0;
        $tab[Sql::POHLAVI]                             = Pohlavi::MUZ_KOD;
        $tab[Sql::POTVRZENI_ZAKONNEHO_ZASTUPCE]        = null;
        $tab[Sql::POTVRZENI_PROTI_COVID19_PRIDANO_KDY] = null;
        $tab[Sql::POTVRZENI_PROTI_COVID19_OVERENO_KDY] = null;
        foreach (Sql::sloupce() as $sloupec) {
            if (!array_key_exists($sloupec, $tab)) {
                $tab[$sloupec] = '';
            }
        }
        $opt = opt($opt, [
            'informovat' => false,
        ]);

        try {
            dbInsert(Sql::UZIVATELE_HODNOTY_TABULKA, $tab);
        } catch (DbDuplicateEntryException $e) {
            if ($e->key() == Sql::EMAIL1_UZIVATELE) {
                throw new DuplicitniEmail;
            }
            if ($e->key() == Sql::LOGIN_UZIVATELE) {
                throw new DuplicitniLogin;
            }
            throw $e;
        }
        $uid = dbInsertId();
        //poslání mailu
        if ($opt['informovat']) {
            $tab[Sql::ID_UZIVATELE] = $uid;
            $u                      = new Uzivatel($tab); //pozor, spekulativní, nekompletní! využito kvůli std rozhraní hlaskaMail
            $mail                   = new GcMail(
                $systemoveNastaveni,
                hlaskaMail('rychloregMail', $u, $tab[Sql::EMAIL1_UZIVATELE], $rand),
            );
            $mail->adresat($tab[Sql::EMAIL1_UZIVATELE]);
            $mail->predmet('Registrace na GameCon.cz');
            if (!$mail->odeslat()) {
                throw new Exception('Chyba: Email s novým heslem NEBYL odeslán, uživatel má pravděpodobně nastavený neplatný email');
            }
        }

        return $uid;
    }

    private static function posledniPoradiRychloregistrace(string $prefix): int
    {
        return (int)dbFetchSingle(<<<SQL
SELECT MAX(CAST(REPLACE(login_uzivatele, '{$prefix}', '') AS INT))
FROM uzivatele_hodnoty
WHERE z_rychloregistrace = 1
SQL,
        );
    }

    /**
     * Smaže uživatele $u a jeho historii připojí k tomuto uživateli. Sloupečky
     * v poli $zmeny případně aktualizuje podle hodnot smazaného uživatele.
     */
    public function sluc(
        Uzivatel $u,
                 $zmeny = [],
    ) {
        $zmeny             = array_intersect_key($u->r, array_flip($zmeny));

        $slucovani = new UzivatelSlucovani();
        $slucovani->sluc($u, $this, $zmeny);

        $this->otoc();
    }

    public function status(bool $sklonovatDlePohlavi = true): string
    {
        return trim(strip_tags($this->statusHtml($sklonovatDlePohlavi)));
    }

    /** Vrátí html formátovaný „status“ uživatele (pro interní informaci) */
    public function statusHtml(bool $sklonovatDlePohlavi = true): string
    {
        $ka     = $sklonovatDlePohlavi
            ? $this->koncovkaDlePohlavi('ka')
            : '';
        $status = [];
        if ($this->maPravo(Pravo::TITUL_ORGANIZATOR)) {
            $status [] = '<span style="color:red">Organizátor' . $ka . '</span>';
        }
        if ($this->jeVypravec()) {
            $status[] = '<span style="color:blue">Vypravěč' . $ka . '</span>';
        }
        if ($this->jeVypravecskaSkupina()) {
            $status[] = '<span style="color:rgba(0,0,255,0.57)">Vypravěčská skupina</span>';
        }
        if ($this->jeCestnyOrg()) {
            $status[] = '<span style="color:#a80f84">Čestný organizátor' . $ka . '</span>';
        }
        if ($this->jePartner()) {
            $status[] = '<span style="color:darkslateblue">Partner' . $ka . '</span>';
        }
        if ($this->jeInfopultak()) {
            $status[] = '<span style="color:orange">Infopult</span>';
        }
        if ($this->jeHerman()) {
            $status[] = '<span style="color:orange">Herman</span>';
        }
        if ($this->jeBrigadnik()) {
            $status[] = '<span style="color:yellowgreen">Brigádník</span>';
        }
        if ($this->jeZazemi()) {
            $status[] = "Zázemí";
        }
        if (count($status) > 0) {
            return implode(', ', $status);
        }

        return 'Účastník';
    }

    public function telefon(bool $html = false): string
    {
        $telefon = trim((string)$this->r['telefon_uzivatele']);
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
    public function uprav(array $tab): ?int
    {
        $tab = array_filter($tab);

        $idNeboHlaska = self::registrujUprav($tab, $this);
        if (is_numeric($idNeboHlaska)) {
            return (int)$idNeboHlaska;
        }
        if ($idNeboHlaska === '') {
            return null;
        }
        throw new Chyba($idNeboHlaska);
    }

    /**
     * Vrátí url cestu k stránce uživatele (bez domény).
     */
    public function url(bool $vcetneId = false): ?string
    {
        if (!$this->r['jmeno_uzivatele']) {
            return null; // nevracet url, asi vypravěčská skupina nebo podobně
        }
        if (!empty($this->r['url'])) {
            return $vcetneId
                ? $this->id() . '-' . $this->r['url']
                : $this->r['url'];
        }

        return self::vytvorUrl($this->r);
    }

    private static function vytvorUrl(array $uzivatelData): ?string
    {
        $jmenoNick = self::jmenoNickZjisti($uzivatelData);
        $url       = slugify($jmenoNick);

        return Url::povolena($url)
            ? $url
            : null;
    }

    public function vek(): ?int
    {
        if ($this->r['datum_narozeni'] == '0000-00-00' || $this->r['datum_narozeni'] == '1970-01-01') {
            return null;
        }
        $narozeni = new DateTime($this->r['datum_narozeni']);

        return $narozeni->diff(new DateTime(DEN_PRVNI_DATE))->y;
    }

    /**
     * Vrátí věk uživatele k zadanému datu. Pokud nemá uživatel datum narození, vrací se null.
     *
     * @param DateTimeCz $datum
     * @return ?int
     */
    public function vekKDatu(DateTimeInterface $datum): ?int
    {
        if ($this->r['datum_narozeni'] == '0000-00-00') {
            return null;
        }

        return date_diff($this->datumNarozeni(), $datum)->y;
    }

    /**
     * Odstraní uživatele z role a aktualizuje jeho práva.
     */
    public function odeberRoli(
        int      $idRole,
        Uzivatel $editor,
    ): bool {
        $result           = dbQuery('DELETE FROM uzivatele_role WHERE id_uzivatele=' . $this->id() . ' AND id_role=' . $idRole);
        $roleNoveOdebrana = dbAffectedOrNumRows($result) > 0;
        if ($roleNoveOdebrana) {
            $this->zalogujZmenuRole($idRole, $editor->id(), self::SESAZEN);
        }
        $this->aktualizujPrava();

        return $roleNoveOdebrana;
    }

    private function zalogujZmenuRole(
        int    $idRole,
        int    $idEditora,
        string $zmena,
    ): void {
        dbQuery(<<<SQL
INSERT INTO uzivatele_role_log(id_uzivatele, id_role, id_zmenil, zmena, kdy)
VALUES ($0, $1, $2, $3, NOW())
SQL,
            [$this->id(), $idRole, $idEditora, $zmena],
        );
        (new RolePodleRocniku($this->systemoveNastaveni))
            ->prepocitejHistoriiRoliProRocnik($this->systemoveNastaveni->rocnik(), $this->id());
    }

    //getters, setters

    public function id(): ?int
    {
        return isset($this->r['id_uzivatele'])
            ? (int)$this->r['id_uzivatele']
            : null;
    }

    /**
     * Vrátí pohlaví ve tvaru 'm' nebo 'f'
     */
    public function pohlavi(): string
    {
        return (string)$this->r[Sql::POHLAVI];
    }

    public function jeZena(): bool
    {
        return $this->pohlavi() === Pohlavi::ZENA_KOD;
    }

    public function jeMuz(): bool
    {
        return $this->pohlavi() === Pohlavi::MUZ_KOD;
    }

    public function prezdivka(): string
    {
        return (string)$this->r[Sql::LOGIN_UZIVATELE];
    }

    /** Vrátí kód státu ve formátu ISO 3166-1 alpha-2 https://en.wikipedia.org/wiki/ISO_3166-1_alpha-2 */
    public function stat(): ?string
    {
        return \Gamecon\Stat::dejKodStatuPodleId($this->r['stat_uzivatele']
            ? (int)$this->r['stat_uzivatele']
            : null);
    }

    /**
     * surová data z DB
     */
    public function rawDb()
    {
        return $this->r;
    }

    /**
     * Na základě řetězce $dotaz zkusí najít všechny uživatele, kteří odpovídají
     * jménem, nickem, apod.
     */
    public static function zHledani(
        string $dotaz,
               $opt = [],
        int    $limit = 20,
        int    $minimumZnaku = 3,
    ) {
        $opt = opt(
            $opt,
            [
                'mail'                       => false,
                'jenPrihlaseniAPritomniNaGc' => false,
                'kromeIdUzivatelu'           => [],
                'jenSRolemi'                 => null,
                'min'                        => $minimumZnaku,
            ],
        );
        if (!is_numeric($dotaz) && mb_strlen($dotaz) < $opt['min']) {
            return [];
        }
        $hodnotaSql              = dbQv($dotaz);
        $hodnotaZacinaLikeSql    = dbQv($dotaz . '%');        // pro LIKE dotazy
        $dalsiSlovoZacinaLikeSql = dbQv('% ' . $dotaz . '%'); // pro LIKE dotazy
        $kromeIdUzivatelu        = $opt['kromeIdUzivatelu'];
        $kromeIdUzivateluSql     = dbQv($kromeIdUzivatelu);
        $pouzeIdsRoli            = [];
        if ($opt['jenSRolemi']) {
            $pouzeIdsRoli = $opt['jenSRolemi'];
        }
        if ($opt['jenPrihlaseniAPritomniNaGc']) {
            $pouzeIdsRoli = array_merge($pouzeIdsRoli, [Role::PRIHLASEN_NA_LETOSNI_GC, Role::PRITOMEN_NA_LETOSNIM_GC]);
        }
        $pouzeIdsRoliSql = dbQv($pouzeIdsRoli);

        return self::zWhere("
      TRUE
      " . ($kromeIdUzivatelu
                ? " AND u.id_uzivatele NOT IN ($kromeIdUzivateluSql)"
                : '') . "
      " . ($pouzeIdsRoli
                ? " AND z.id_role IN ($pouzeIdsRoliSql) "
                : '') . "
      AND (
          u.id_uzivatele = $hodnotaSql
          " . ((string)(int)$dotaz !== (string)$dotaz
                // nehledáme ID
                ? ("
                  OR login_uzivatele LIKE $hodnotaZacinaLikeSql
                  OR jmeno_uzivatele LIKE $hodnotaZacinaLikeSql
                  OR jmeno_uzivatele LIKE $dalsiSlovoZacinaLikeSql -- když účastník napíše do jména jméno i příjmení
                  OR prijmeni_uzivatele LIKE $hodnotaZacinaLikeSql
                  OR prijmeni_uzivatele LIKE $dalsiSlovoZacinaLikeSql -- když účastník napíše do příjmení jméno i příjmení
                  " . ($opt['mail']
                        ? " OR email1_uzivatele LIKE $hodnotaZacinaLikeSql "
                        : "") . "
                  OR CONCAT(jmeno_uzivatele,' ',prijmeni_uzivatele) LIKE $hodnotaZacinaLikeSql
                  ")
                : ''
                            ) . "
      )
    ", null, 'LIMIT ' . $limit);
    }

    public static function zId(
        $id,
        bool $zCache = false,
    ): ?static {
        $id = (int)$id;

        if ($zCache) {
            $objekt = self::$objekty[static::class][$id] ?? null;
            if ($objekt) {
                return $objekt;
            }
        }

        $uzivatel = self::zIds($id)[0] ?? null;

        if ($uzivatel && $zCache) {
            self::$objekty[static::class][$id] = $uzivatel;
        }

        return $uzivatel;
    }

    public static function zIdUrcite(
        $id,
        bool $zCache = false,
    ): self {
        $uzivatel = static::zId($id, $zCache);
        if ($uzivatel !== null) {
            return $uzivatel;
        }
        throw new \Gamecon\Exceptions\UzivatelNenalezen('Neznámé ID uživatele ' . $id);
    }

    /**
     * Vrátí pole uživatelů podle zadaných ID. Lze použít pole nebo string s čísly
     * oddělenými čárkami.
     * @param string|int[] $ids
     * @param bool $zCache
     * @return Uzivatel[]
     */
    public static function zIds(
        $ids,
        bool $zCache = false,
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): array {
        self::zIdsDSC($dataSourcesCollector);

        return parent::zIds($ids, $zCache);
    }

    public static function zIdsDSC(
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): void {
        self::nactiUzivateleDSC($dataSourcesCollector);
    }

    public static function prednactiUzivateleNaAktivitach(int $rocnik)
    {
        static $prednacteniUzivateleNaAktivitach = [];
        if (isset($prednacteniUzivateleNaAktivitach[$rocnik])) {
            return;
        }
        $idUzivatelu = dbFetchColumn(
            <<<SQL
                    SELECT zdroj.id_uzivatele
                    FROM akce_prihlaseni AS zdroj
                    JOIN akce_seznam on zdroj.id_akce = akce_seznam.id_akce
                    WHERE akce_seznam.rok = $rocnik
                    UNION
                    SELECT zdroj.id_uzivatele
                    FROM akce_prihlaseni_spec AS zdroj
                    JOIN akce_seznam on zdroj.id_akce = akce_seznam.id_akce
                    WHERE akce_seznam.rok = $rocnik
                SQL,
        );
        self::zIds($idUzivatelu, true);
        $prednacteniUzivateleNaAktivitach[$rocnik] = true;
    }

    /**
     * Vrátí uživatele dle zadaného mailu.
     */
    public static function zEmailu(?string $email): ?Uzivatel
    {
        if (!$email) {
            return null;
        }
        $uzivatele = Uzivatel::zWhere(
            'email1_uzivatele = $0',
            [0 => filter_var($email, FILTER_SANITIZE_EMAIL)],
        );

        return $uzivatele[0] ?? null;
    }

    public static function zNicku(string $nick): ?Uzivatel
    {
        if (!$nick) {
            return null;
        }
        $uzivatelWrapped = Uzivatel::zWhere('login_uzivatele = $1', [$nick]);

        return reset($uzivatelWrapped)
            ?: null;
    }

    public static function zJmenaAPrijmeni(
        string $jmeno,
        string $prijemni,
    ): ?Uzivatel {
        if (!$jmeno && !$prijemni) {
            return null;
        }
        $uzivatelWrapped = Uzivatel::zWhere(
            Sql::JMENO_UZIVATELE . ' = $0 AND ' . Sql::PRIJMENI_UZIVATELE . ' = $1',
            [$jmeno, $prijemni],
        );

        return reset($uzivatelWrapped)
            ?: null;
    }

    public static function zIndicii(
        string $jmenoNickEmailId,
        bool   $zCache = false,
    ): ?\Uzivatel {
        if (!$jmenoNickEmailId) {
            return null;
        }
        $jmenoNickEmailId = trim($jmenoNickEmailId);
        if (is_numeric($jmenoNickEmailId)) {
            return self::zId((int)$jmenoNickEmailId, $zCache)
                   ?? self::zNicku($jmenoNickEmailId);
        }
        if (filter_var($jmenoNickEmailId, FILTER_VALIDATE_EMAIL)) {
            return self::zEmailu($jmenoNickEmailId);
        }
        ['jmeno' => $jmeno, 'prijmeni' => $prijmeni, 'nick' => $nick] = self::jmenoNickRozloz($jmenoNickEmailId);
        if (trim($nick ?? '') !== '') {
            return self::zNicku($nick);
        }

        return self::zJmenaAPrijmeni($jmeno, $prijmeni);
    }

    /**
     * Vytvoří a vrátí nového uživatele z zadaného pole odpovídajícího db struktuře
     */
    public static function zPole(
        $pole,
        $mod = 0,
    ) {
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
    public static function zPrihlasenych()
    {
        return self::zWhere(
            'u.id_uzivatele IN(
                SELECT id_uzivatele
                FROM platne_role_uzivatelu
                WHERE id_role = ' . Role::PRIHLASEN_NA_LETOSNI_GC . '
            )',
        );
    }

    /**
     * Pokusí se načíst uživatele podle aktivní session případně z perzistentního
     * přihlášení.
     * @param string $klic klíč do $_SESSION kde očekáváme hodnoty uživatele
     * @return Uzivatel|null objekt uživatele nebo null
     * @todo nenačítat znovu jednou načteného, cacheovat
     */
    public static function zSession($klic = self::UZIVATEL)
    {
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
                [$_COOKIE['gcTrvalePrihlaseni']],
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
                [$rand],
            );
            setcookie('gcTrvalePrihlaseni', $rand, time() + 3600 * 24 * 365, '/');

            return Uzivatel::prihlasId($id, $klic);
        }

        return null;
    }

    /**
     * Vrátí uživatele s loginem odpovídajícím dané url
     */
    public static function zUrl(): ?Uzivatel
    {
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
     * @return string[]
     */
    public static function cfosEmaily(): array
    {
        $cfos = Uzivatel::zRole(Role::CFO);

        return array_filter(
            array_map(static fn(
                Uzivatel $cfo,
            ) => $cfo->mail(), $cfos),
            static fn(
                $mail,
            ) => is_string($mail) && filter_var($mail, FILTER_VALIDATE_EMAIL) !== false,
        );
    }

    protected static function dotaz(
        $where,
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): string {
        self::dotazDSC($dataSourcesCollector);

        return <<<SQL
SELECT
    u.*,
    (SELECT url FROM uzivatele_url WHERE uzivatele_url.id_uzivatele = u.id_uzivatele ORDER BY id_url_uzivatele DESC LIMIT 1) AS url,
    GROUP_CONCAT(DISTINCT p.id_prava) as prava
FROM uzivatele_hodnoty u
LEFT JOIN platne_role_uzivatelu z ON (z.id_uzivatele = u.id_uzivatele)
LEFT JOIN prava_role p ON (p.id_role = z.id_role)
LEFT JOIN uzivatele_url ON u.id_uzivatele = uzivatele_url.id_uzivatele
$where
GROUP BY u.id_uzivatele
SQL;
    }

    protected static function dotazDSC(?DataSourcesCollector $dataSourcesCollector = null): void
    {
        $dataSourcesCollector?->addDataSource("uzivatele_url");
        $dataSourcesCollector?->addDataSource("uzivatele_hodnoty");
        $dataSourcesCollector?->addDataSource("platne_role_uzivatelu");
        $dataSourcesCollector?->addDataSource("prava_role");
    }

    /** Vrátí pole uživatelů sedících na roli s daným ID */
    public static function zRole($id)
    {
        return self::nactiUzivatele( // WHERE nelze, protože by se omezily načítané práva uživatele
            'JOIN uzivatele_role z2 ON (z2.id_role = ' . dbQv($id) . ' AND z2.id_uzivatele = u.id_uzivatele)',
        );
    }

    ///////////////////////////////// Protected //////////////////////////////////

    /**
     * Aktualizuje práva a role uživatele z databáze (protože se provedla nějaká změna)
     */
    protected function aktualizujPrava()
    {
        $this->idsRoli    = null;
        $this->r['prava'] = null;
    }

    /**
     * Načte uživatele včetně práv z DB podle zadané where klauzule. Tabulka se
     * aliasuje jako u.*
     * @param string $where
     * @return Uzivatel[]
     */
    protected static function nactiUzivatele(
        string                $where,
        ?DataSourcesCollector $dataSourcesCollector = null,
    ): array {
        $query     = self::dotaz(where: $where, dataSourcesCollector: $dataSourcesCollector);
        $o         = dbQuery($query);
        $uzivatele = [];
        while ($r = mysqli_fetch_assoc($o)) {
            $u             = new self($r);
            $u->r['prava'] = explode(',', $u->r['prava'] ?? '');
            $uzivatele[]   = $u;
        }

        return $uzivatele;
    }

    protected static function nactiUzivateleDSC(?DataSourcesCollector $dataSourcesCollector): void
    {
        self::dotazDSC($dataSourcesCollector);
    }

    public function shop(): Shop
    {
        if ($this->shop === null) {
            $this->shop = new Shop(
                $this,
                $this,
                $this->systemoveNastaveni,
            );
        }

        return $this->shop;
    }

    public function maNahranyDokladProtiCoviduProRok(int $rok): bool
    {
        $potvrzeniProtiCovid19PridanoKdy = $this->potvrzeniProtiCoviduPridanoKdy();

        return $potvrzeniProtiCovid19PridanoKdy
               && $potvrzeniProtiCovid19PridanoKdy->format('Y') === (string)$rok;
    }

    public function maOverenePotvrzeniProtiCoviduProRok(
        int  $rok,
        bool $musiMitNahranyDokument = false,
    ): bool {
        if ($musiMitNahranyDokument && !$this->maNahranyDokladProtiCoviduProRok($rok)) {
            return false;
        }
        $potvrzeniProtiCovid19OverenoKdy = $this->potvrzeniProtiCoviduOverenoKdy();

        return $potvrzeniProtiCovid19OverenoKdy
               && $potvrzeniProtiCovid19OverenoKdy->format('Y') === (string)$rok;
    }

    public function covidFreePotvrzeniHtml(int $rok): string
    {
        return UserController::covidFreePotvrzeniHtml($this, $rok);
    }

    public function zpracujPotvrzeniProtiCovidu(): bool
    {
        return UserController::zpracujPotvrzeniProtiCovidu($this);
    }

    public function ulozPotvrzeniProtiCoviduPridanyKdy(?\DateTimeInterface $kdy)
    {
        dbUpdate('uzivatele_hodnoty', [
            'potvrzeni_proti_covid19_pridano_kdy' => $kdy,
        ], [
            'id_uzivatele' => $this->id(),
        ]);
        $this->r['potvrzeni_proti_covid19_pridano_kdy'] = $kdy
            ? $kdy->format(DateTimeCz::FORMAT_DB)
            : null;
    }

    private function ulozPotvrzeniProtiCoviduOverenoKdy(?\DateTimeInterface $kdy)
    {
        dbUpdate('uzivatele_hodnoty', [
            'potvrzeni_proti_covid19_overeno_kdy' => $kdy,
        ], [
            'id_uzivatele' => $this->id(),
        ]);
        $this->r['potvrzeni_proti_covid19_overeno_kdy'] = $kdy
            ? $kdy->format(DateTimeCz::FORMAT_DB)
            : null;
    }

    public function ulozPotvrzeniRodicuPridanoKdy(?\DateTimeInterface $kdy)
    {
        dbUpdate(Sql::UZIVATELE_HODNOTY_TABULKA, [
            Sql::POTVRZENI_ZAKONNEHO_ZASTUPCE_SOUBOR => $kdy,
        ], [
            Sql::ID_UZIVATELE => $this->id(),
        ]);
        $this->r[Sql::POTVRZENI_ZAKONNEHO_ZASTUPCE_SOUBOR] = $kdy
            ? $kdy->format(DateTimeCz::FORMAT_DB)
            : null;
    }

    public function urlNaPotvrzeniProtiCoviduProAdmin(): string
    {
        // admin/scripts/zvlastni/infopult/potvrzeni-proti-covidu.php
        return URL_ADMIN . '/infopult/potvrzeni-proti-covidu?id=' . $this->id();
    }

    public function urlNaPotvrzeniProtiCoviduProVlastnika(): string
    {
        // admin/scripts/zvlastni/uvod/potvrzeni-proti-covidu.php
        return URL_WEBU . '/prihlaska/potvrzeni-proti-covidu?id=' . $this->id();
    }

    public function urlNaSmazaniPotrvrzeniVlastnikem(): string
    {
        // admin/scripts/zvlastni/uvod/potvrzeni-proti-covidu.php
        return URL_WEBU . '/prihlaska/potvrzeni-proti-covidu?id=' . $this->id() . '&smazat=1';
    }

    public function cestaKSouboruSPotvrzenimProtiCovidu(): string
    {
        return WWW . '/soubory/systemove/potvrzeni/covid-19-' . $this->id() . '.png';
    }

    public function smazPotvrzeniProtiCovidu()
    {
        if (is_file($this->cestaKSouboruSPotvrzenimProtiCovidu())) {
            unlink($this->cestaKSouboruSPotvrzenimProtiCovidu());
        }
        $this->ulozPotvrzeniProtiCoviduPridanyKdy(null);
        $this->ulozPotvrzeniProtiCoviduOverenoKdy(null);
    }

    public function potvrzeniProtiCoviduPridanoKdy(): ?\DateTimeInterface
    {
        $potvrzeniProtiCovid19PridanoKdy = $this->r['potvrzeni_proti_covid19_pridano_kdy'] ?? null;

        return $potvrzeniProtiCovid19PridanoKdy
            ? new DateTimeImmutable($potvrzeniProtiCovid19PridanoKdy)
            : null;
    }

    public function potvrzeniProtiCoviduOverenoKdy(): ?\DateTimeInterface
    {
        $potvrzeniProtiCovid19OverenoKdy = $this->r['potvrzeni_proti_covid19_overeno_kdy'] ?? null;

        return $potvrzeniProtiCovid19OverenoKdy
            ? new DateTimeImmutable($potvrzeniProtiCovid19OverenoKdy)
            : null;
    }

    public function cestaKSouboruSPotvrzenimRodicu(string $pripona = 'png'): ?string
    {
        return WWW . '/soubory/systemove/potvrzeni/potvrzeni-rodicu-' . $this->id() . '.' . $pripona;
    }

    public function zpracujPotvrzeniRodicu(): bool
    {
        return UserController::zpracujPotvrzeniRodicu($this);
    }

    public function uvodniAdminUrl(string $zakladniAdminUrl = URL_ADMIN): string
    {
        if ($this->maPravo(Pravo::ADMINISTRACE_INFOPULT)) {
            return $this->infopultAdminUrl($zakladniAdminUrl);
        }
        if ($this->maPravo(Pravo::ADMINISTRACE_MOJE_AKTIVITY)) {
            return $this->mojeAktivityAdminUrl($zakladniAdminUrl);
        }

        return $zakladniAdminUrl;
    }

    public function infopultAdminUrl(string $zakladniAdminUrl = URL_ADMIN): string
    {
        // vrátí "infopult" - máme to schválně přes cestu ke skriptu, protože jeho název udává výslednou URL a nechceme mít neplatnou URL, kdyby někdo ten skrip přejmenoval.
        return $zakladniAdminUrl . '/' . basename(__DIR__ . '/../admin/scripts/modules/infopult/infopult.php', '.php');
    }

    /**
     * Může vrátit i URL na web mimo admin, pokud jediná admin stránka, na kterou má uživatel právo, je nechtěná
     * moje-aktivity.
     * @param string $zakladniAdminUrl
     * @param string $zakladniWebUrl
     * @return string[] nazev => název, url => URL
     */
    public function mimoMojeAktivityUvodniAdminLink(
        string $zakladniAdminUrl = URL_ADMIN,
        string $zakladniWebUrl = URL_WEBU,
    ): array {
        // URL máme schválně přes cestu ke skriptu, protože jeho název udává výslednou URL a nechceme mít neplatnou URL, kdyby někdo ten skrip přejmenoval.
        if ($this->maPravo(Pravo::ADMINISTRACE_INFOPULT)) {
            /** 'uvod' viz například @link http://admin.beta.gamecon.cz/moje-aktivity/infopult */
            $adminUvodUrl = basename(__DIR__ . '/../admin/scripts/modules/infopult/infopult.php', '.php');

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

    public function mojeAktivityAdminUrl(string $zakladniAdminUrl = URL_ADMIN): string
    {
        // vrátí "moje-aktivity" - máme to schválně přes cestu ke skriptu, protože jeho název udává výslednou URL a nechceme mít neplatnou URL, kdyby někdo ten skrip přejmenoval.
        return $zakladniAdminUrl . '/' . basename(__DIR__ . '/../admin/scripts/modules/moje-aktivity/moje-aktivity.php', '.php');
    }

    public function kdySeRegistrovalNaLetosniGc(): ?DateTimeImmutable
    {
        if (!$this->gcPrihlasen()) {
            return null;
        }
        if (!$this->kdySeRegistrovalNaLetosniGc) {
            $this->kdySeRegistrovalNaLetosniGc = UserRepository::kdySeRegistrovalNaLetosniGc($this->id() ?? 0);
        }

        return $this->kdySeRegistrovalNaLetosniGc;
    }
}
