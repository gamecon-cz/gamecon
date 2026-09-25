<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Command\FioStazeniNovychPlateb;
use Gamecon\Kanaly\GcMail;
use Gamecon\Logger\JobResultLoggerInterface;
use Gamecon\Logger\LogHomadnychAkciTrait;
use Gamecon\Role\Role;
use Gamecon\Stat;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Dto\Dluznik;
use Gamecon\Uzivatel\Enum\TypUpominky;
use Gamecon\Uzivatel\Dto\VlastniZneniUpominky;
use Gamecon\Uzivatel\Enum\UcastNaGc;
use Gamecon\Uzivatel\Exceptions\VlastniZneniNeniVyplnene;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Uzivatel;

/**
 * Třída zodpovídající za upomínání dlužníků (uživatelů se záporným zůstatkem)
 */
class UpominaniDluzniku
{
    use LogHomadnychAkciTrait;

    private const SKUPINA_UPOMINANI = 'upominani-dluzniku';

    public function __construct(
        private readonly SystemoveNastaveni        $systemoveNastaveni,
        private readonly JobResultLoggerInterface  $jobResultLogger,
        private readonly FioStazeniNovychPlateb    $fioStazeniNovychPlateb,
        private readonly UpominkaDluznikaLog       $upominkaDluznikaLog = new UpominkaDluznikaLog(),
        private readonly UcastNaGcPodleRoli        $ucastNaGcPodleRoli = new UcastNaGcPodleRoli(),
        private readonly UpominkaVlastniZneni      $upominkaVlastniZneni = new UpominkaVlastniZneni(),
    ) {
    }

    /**
     * Log se zapisuje po každém e-mailu, ne až na konci běhu, aby se po pádu
     * (timeout, výpadek SMTP) dalo pokračovat bez rizika duplicitních upomínek.
     */
    public function odesliUpominkuJednomu(
        Uzivatel    $uzivatel,
        TypUpominky $typUpominky,
        int         $dluh,
        int         $rocnik,
        Uzivatel    $odesilatel,
        UcastNaGc   $ucastNaGc,
        ?int        $rokPosledniUcasti,
    ): GcMail {
        $gcMail = (new GcMail($this->systemoveNastaveni))
            ->adresat($uzivatel->mail())
            ->predmet($this->dejEmailPredmet($typUpominky, $rocnik))
            ->text($this->dejEmailZpravu(
                $typUpominky,
                $dluh,
                $uzivatel->id(),
                $ucastNaGc,
                $rokPosledniUcasti,
                $uzivatel->koncovkaDlePohlavi(),
                (string) $uzivatel->jmenoNick(),
                $rocnik,
            ));

        $docasneQrSoubory = [];
        // Bez existujícího adresáře tempnam() tiše spadne zpátky do systémového
        // /tmp, které je mimo zapisovatelný mount a může se mezi requesty vyprázdnit.
        $adresarProQr = $this->pripravAdresarProQrKody();
        $qrKody       = $adresarProQr === null
            ? []
            : $this->dejQrKodyProUpominku($uzivatel);

        foreach ($qrKody as $nazevPrilohy => $qrKod) {
            if (!$qrKod) {
                continue;
            }

            $qrSoubor = tempnam($adresarProQr, 'upominani_qr_');
            if ($qrSoubor === false) {
                continue;
            }

            if (file_put_contents($qrSoubor, $qrKod->getString()) === false) {
                @unlink($qrSoubor);
                continue;
            }

            $gcMail->prilohaSoubor($qrSoubor)->prilohaNazev($nazevPrilohy);
            $docasneQrSoubory[] = $qrSoubor;
        }

        $gcMail->odeslat(GcMail::FORMAT_TEXT);

        // Smazat dočasné QR soubory
        foreach ($docasneQrSoubory as $qrSoubor) {
            if (file_exists($qrSoubor)) {
                @unlink($qrSoubor);
            }
        }

        $this->upominkaDluznikaLog->zaloguj(
            idUzivatele: $uzivatel->id(),
            typUpominky: $typUpominky,
            dluh: $dluh,
            rocnik: $rocnik,
            odeslal: $odesilatel->id(),
            kdy: $this->systemoveNastaveni->ted(),
        );

        return $gcMail;
    }

    private function pripravAdresarProQrKody(): ?string
    {
        $adresar = $this->systemoveNastaveni->privateCacheDir();

        try {
            (new Filesystem())->mkdir($adresar, 0775);
        } catch (IOException $chyba) {
            $this->jobResultLogger->logs(
                "Upomínání dlužníků: adresář pro QR kódy nejde vytvořit ($adresar), upomínky odejdou bez QR. {$chyba->getMessage()}",
            );

            return null;
        }

        return $adresar;
    }

    /**
     * Najde všechny uživatele, kteří dluží GameConu (mají záporný zůstatek)
     *
     * Záměrně bez ohledu na letošní účast - dluh z minulých let se přes
     * Finance::stav() promítá do aktuálního zůstatku a upomenout se má i ten,
     * kdo letos nepřijel. Jak se kdo letos zúčastnil, nese Dluznik::$ucastNaGc,
     * aby mu text upomínky netvrdil něco, co pro něj neplatí.
     *
     * @return Dluznik[] Pole dlužníků
     */
    public function najdiDluzniky(?int $rocnik = null): array
    {
        $rocnik ??= $this->systemoveNastaveni->rocnik();

        $dluznici = [];
        foreach ($this->idsMoznychDluzniku($rocnik) as $idUzivatele) {
            $uzivatel = Uzivatel::zId($idUzivatele);
            if (!$uzivatel) {
                continue;
            }

            $dluznik = $this->dejDluznika($uzivatel, $rocnik);
            if ($dluznik) {
                $dluznici[] = $dluznik;
            }
        }

        return $dluznici;
    }

    /**
     * Koho vůbec má smysl přepočítávat.
     *
     * Finance::stav() je drahý (jednotky dotazů na uživatele), takže ho nemá cenu
     * pouštět na všechny v databázi. Do mínusu se dá dostat jen třemi cestami:
     * letošní objednávkou (má letošní přihlášku), zůstatkem z minulých let
     * (sloupec zustatek), nebo zápornou platbou - vratkou či opravou, která
     * může uživatele dostat do mínusu i bez letošní účasti.
     *
     * @return int[]
     */
    private function idsMoznychDluzniku(int $rocnik): array
    {
        $idRolePrihlasen = Role::prihlasenNaRocnik($rocnik);

        $ids = dbFetchColumn(<<<SQL
SELECT DISTINCT id_uzivatele
FROM (
    SELECT id_uzivatele FROM uzivatele_hodnoty WHERE zustatek < 0
    UNION
    SELECT id_uzivatele FROM platne_role_uzivatelu WHERE id_role = $0
    UNION
    SELECT id_uzivatele FROM platby WHERE castka < 0
) AS moznidluznici
SQL,
            [
                0 => $idRolePrihlasen,
            ],
        );

        return array_map('intval', $ids);
    }

    /**
     * Dlužník podle jednoho uživatele, nebo null když nedluží.
     *
     * Náhled a odeslání jednotlivci se tím vyhnou přepočtu financí všech
     * uživatelů v databázi, který trvá desítky sekund.
     */
    public function dejDluznika(
        Uzivatel $uzivatel,
        ?int     $rocnik = null,
    ): ?Dluznik {
        $rocnik ??= $this->systemoveNastaveni->rocnik();

        if ($uzivatel->id() === Uzivatel::SYSTEM) {
            return null; // Systémový účet není člověk, byť má e-mail i záporný zůstatek
        }

        // Přepočítáme aktuální stav pomocí Finance třídy pro daný rok
        $zustatek = $uzivatel->finance()->stav();

        if ($zustatek >= 0) {
            return null;
        }

        return new Dluznik(
            uzivatel: $uzivatel,
            dluh: -$zustatek, // Převedeme na kladné číslo
            ucastNaGc: $this->ucastNaGcPodleRoli->ucast($uzivatel->id(), $rocnik),
            rokPosledniUcasti: $this->ucastNaGcPodleRoli->rokPosledniUcasti($uzivatel->id()),
        );
    }

    /**
     * Zaloguje odeslání upomínkového e-mailu (1 týden po)
     */
    public function zalogujUpominaniTyden(
        int $rocnik,
        int $pocetEmailu,
    ): void {
        $this->zalogujHromadnouAkci(
            self::SKUPINA_UPOMINANI,
            $this->nazevAkceUpominaniTyden($rocnik),
            $pocetEmailu,
            Uzivatel::zId(Uzivatel::SYSTEM, true),
        );
    }

    /**
     * Zaloguje odeslání upomínkového e-mailu (1 měsíc po)
     */
    public function zalogujUpominaniMesic(
        int $rocnik,
        int $pocetEmailu,
    ): void {
        $this->zalogujHromadnouAkci(
            self::SKUPINA_UPOMINANI,
            $this->nazevAkceUpominaniMesic($rocnik),
            $pocetEmailu,
            Uzivatel::zId(Uzivatel::SYSTEM, true),
        );
    }

    private function nazevAkceUpominaniTyden(int $rocnik): string
    {
        return "upominani-tyden-$rocnik";
    }

    private function nazevAkceUpominaniMesic(int $rocnik): string
    {
        return "upominani-mesic-$rocnik";
    }

    /**
     * Odešle upomínkové e-maily dlužníkům
     *
     * @param TypUpominky $typUpominky Typ upomínky (týden/měsíc)
     * @param bool $znovu Zda má být upomínání spuštěno znovu i když už bylo odesláno
     * @return int Počet odeslaných e-mailů, nebo -1 pokud se upomínání nespustilo
     */
    public function odesliUpominkyDluznikum(
        TypUpominky $typUpominky,
        bool        $znovu = false,
    ): int {
        // Zkontroluj, jestli je správný čas
        $konecGc            = $this->systemoveNastaveni->spocitanyKonecLetosnihoGameconu();
        $casovyOffset       = match ($typUpominky) {
            TypUpominky::TYDEN => '+1 week',
            TypUpominky::MESIC => '+1 month',
            TypUpominky::RUCNI, TypUpominky::VLASTNI => throw new \LogicException(
                'Ruční upomínka nemá časové okno, rozesílá se přes odesliUpominkuJednomu()',
            ),
        };
        $ocekavanyTermin    = (clone $konecGc)->modify($casovyOffset);
        $ocekavanyTerminMax = (clone $ocekavanyTermin)->modify('+23 hours');
        $ted                = $this->systemoveNastaveni->ted();

        // Spustit pouze pokud jsme v rozmezí (s tolerancí 23 hodin)
        if ($ted < $ocekavanyTermin || $ted > $ocekavanyTerminMax) {
            $nazev = $this->dejNazevUpominky($typUpominky);
            $this->jobResultLogger->logs(
                sprintf(
                    'Upomínání dlužníků (%s): Není správný čas. Očekáváno od %s do %s, teď: %s',
                    $nazev,
                    $ocekavanyTermin->format('Y-m-d H:i:s'),
                    $ocekavanyTerminMax->format('Y-m-d H:i:s'),
                    $ted->format('Y-m-d H:i:s'),
                ),
            );

            return -1;
        }

        // Zkontroluj, jestli už nebyly e-maily odeslány
        $rocnik = $this->systemoveNastaveni->rocnik();

        if (!$znovu && $this->jizOdeslano($typUpominky, $rocnik)) {
            $nazev = $this->dejNazevUpominky($typUpominky);
            $this->jobResultLogger->logs(
                sprintf(
                    'Upomínání dlužníků (%s): E-maily už byly odeslány pro rocnik %s',
                    $nazev,
                    $rocnik,
                ),
            );

            return -1;
        }

        // Stáhnout nejnovější platby z banky před kontrolou dlužníků
        $this->fioStazeniNovychPlateb->stahniNoveFioPlatby();

        // Přepočet financí všech uživatelů v DB trvá řádově desítky sekund, takže
        // se nesmí počítat do limitu, který nastavil volající cron.
        set_time_limit(300);
        $dluzniciSZustatkem = $this->najdiDluzniky($rocnik);

        if (count($dluzniciSZustatkem) === 0) {
            $nazev = $this->dejNazevUpominky($typUpominky);
            $this->jobResultLogger->logs("Upomínání dlužníků ($nazev): Žádní dlužníci k upomínání");

            return -1;
        }

        $pocetOdeslanychEmailu = 0;
        $pocetPreskocenych     = 0;
        $pocetPodPrahem        = 0;
        $idsPodPrahem          = [];
        $posledniGcMail        = null;
        // Práh hlídá jen automatika; při ručním rozesílání rozhoduje o každém
        // příjemci člověk, takže tam se neuplatňuje.
        $minimalniCastka       = $this->systemoveNastaveni->upominkaMinimalniCastka();
        // $znovu je výslovný pokyn obeslat všechny znovu, takže přeskakování
        // už obeslaných platí jen pro běžný běh (typicky pokračování po pádu).
        $jizUpomenuti          = $znovu
            ? []
            : array_flip($this->upominkaDluznikaLog->idsJizUpomenutych($rocnik, $typUpominky));
        $system                = Uzivatel::zId(Uzivatel::SYSTEM, true);

        foreach ($dluzniciSZustatkem as $uzivatelSDluhem) {
            $uzivatel = $uzivatelSDluhem->uzivatel;
            if (!$uzivatel->mail()) {
                continue;
            }

            // Po spadlém běhu se pokračuje tam, kde předchozí skončil - jinak by
            // lidem ze začátku seznamu přišla upomínka podruhé. Kontrola je před
            // prahem, aby se už obeslaný člověk nepřesunul mezi „pod prahem“
            // jen proto, že mezitím část dluhu doplatil.
            if (isset($jizUpomenuti[$uzivatel->id()])) {
                $pocetPreskocenych++;
                continue;
            }

            // Porovnává se zaokrouhlená částka, tedy ta, která reálně půjde
            // v e-mailu - jinak by dluh 250,60 Kč vypadal jako pod prahem 251,
            // ale v mailu by stálo 251 Kč.
            $dluhVMailu = (int)round($uzivatelSDluhem->dluh);
            if ($dluhVMailu < $minimalniCastka) {
                $pocetPodPrahem++;
                $idsPodPrahem[] = $uzivatel->id();
                continue;
            }

            $posledniGcMail = $this->odesliUpominkuJednomu(
                $uzivatel,
                $typUpominky,
                $dluhVMailu,
                $rocnik,
                $system,
                $uzivatelSDluhem->ucastNaGc,
                $uzivatelSDluhem->rokPosledniUcasti,
            );
            $pocetOdeslanychEmailu++;
            set_time_limit(10); // Prodloužit timeout pro každý e-mail
        }

        if ($idsPodPrahem !== []) {
            $this->jobResultLogger->logs(sprintf(
                'Upomínání dlužníků: %d dlužníků pod prahem %d Kč, neobesláni: %s',
                $pocetPodPrahem,
                (int)ceil($minimalniCastka),
                implode(', ', $idsPodPrahem),
            ));
        }

        // Zaloguj odeslání do databáze
        match ($typUpominky) {
            TypUpominky::TYDEN => $this->zalogujUpominaniTyden($rocnik, $pocetOdeslanychEmailu),
            TypUpominky::MESIC => $this->zalogujUpominaniMesic($rocnik, $pocetOdeslanychEmailu),
        };

        // Poslat CFO informaci o počtu odeslaných e-mailů
        $this->odeslInfoCfo(
            $typUpominky,
            $rocnik,
            $pocetOdeslanychEmailu,
            $pocetPreskocenych,
            $pocetPodPrahem,
            $minimalniCastka,
            $konecGc,
            $posledniGcMail,
        );

        $nazev = $this->dejNazevUpominky($typUpominky);
        $this->jobResultLogger->logs("Upomínání dlužníků ($nazev): Odesláno $pocetOdeslanychEmailu e-mailů");

        return $pocetOdeslanychEmailu;
    }

    private function dejNazevUpominky(TypUpominky $typUpominky): string
    {
        return match ($typUpominky) {
            TypUpominky::TYDEN => '1 týden',
            TypUpominky::MESIC => '1 měsíc',
            TypUpominky::RUCNI => 'ruční rozeslání',
            TypUpominky::VLASTNI => 'vlastní znění',
        };
    }

    /**
     * Nevyplněné znění nesmí tiše propadnout na standardní text - CFO by
     * rozeslal něco jiného, než co vidí v editoru.
     *
     * @throws VlastniZneniNeniVyplnene
     */
    private function dejVyplneneVlastniZneni(int $rocnik): VlastniZneniUpominky
    {
        $zneni = $this->upominkaVlastniZneni->dejZneni($rocnik);
        if ($zneni === null || !$zneni->jeVyplnene()) {
            throw new VlastniZneniNeniVyplnene(
                "Vlastní znění upomínky pro ročník $rocnik nemá vyplněný předmět nebo text.",
            );
        }

        return $zneni;
    }

    public function dejEmailPredmet(
        TypUpominky $typUpominky,
        int         $rocnik,
    ): string {
        if ($typUpominky->maVlastniZneni()) {
            return UpominkaVlastniZneni::dosadPovoleneKonstanty(
                $this->dejVyplneneVlastniZneni($rocnik)->predmet,
            );
        }

        return match ($typUpominky->textovaVarianta()) {
            TypUpominky::TYDEN => "GameCon $rocnik - nedoplatky",
            TypUpominky::MESIC => "GameCon $rocnik - PŘIPOMÍNKA nedoplatků",
        };
    }

    public function dejEmailZpravu(
        TypUpominky $typUpominky,
        int         $dluh,
        int         $variabilniSymbol,
        UcastNaGc   $ucastNaGc,
        ?int        $rokPosledniUcasti,
        string      $koncovkaDlePohlavi,
        string      $jmenoNick,
        int         $rocnik,
    ): string {
        if ($typUpominky->maVlastniZneni()) {
            return $this->upominkaVlastniZneni->dosadSymboly(
                $this->dejVyplneneVlastniZneni($rocnik)->text,
                $jmenoNick,
                $variabilniSymbol,
                $dluh,
                $koncovkaDlePohlavi,
            );
        }

        $ucetCz = UCET_CZ;
        $iban   = IBAN;

        $uvod     = $this->dejUvodPodleUcasti($typUpominky, $ucastNaGc, $koncovkaDlePohlavi);
        $duvod    = $this->dejDuvodDluhuPodleUcasti($ucastNaGc, $rokPosledniUcasti);
        $zaver    = $this->dejZaverPodleUcasti($typUpominky, $ucastNaGc);
        $nalehavost = $typUpominky->textovaVarianta() === TypUpominky::MESIC
            ? ' a nám už se velmi blíží účetní uzávěrka'
            : '';

        return <<<TEXT
Ahoj!

$uvod

$duvod Konkrétně se jedná o $dluh korun$nalehavost. Můžeš nám prosím nedoplatek co nejdřív srovnat?

Stačí poslat danou částku na GC účet $ucetCz ($iban) s variabilním symbolem $variabilniSymbol, popř. využít platební QR kód přiložený níže.

Jakékoliv dotazy, nejasnosti nebo reklamace směřuj prosím v odpovědi na tento e-mail, nebo na finance@gamecon.cz.

$zaver
TEXT;
    }

    private function dejUvodPodleUcasti(
        TypUpominky $typUpominky,
        UcastNaGc   $ucastNaGc,
        string      $koncovkaDlePohlavi,
    ): string {
        $jeMesicni = $typUpominky->textovaVarianta() === TypUpominky::MESIC;

        return match ($ucastNaGc) {
            UcastNaGc::PRITOMEN => $jeMesicni
                ? 'Doufáme, že ti na GameCon zůstaly krásné vzpomínky!'
                : 'Doufáme, že tě letošní GameCon bavil!',
            UcastNaGc::JEN_PRIHLASEN => "Mrzí nás, že ses na letošní GameCon nakonec nedostal{$koncovkaDlePohlavi}.",
            UcastNaGc::NEDORAZIL     => 'Ozýváme se ohledně tvého účtu na GameConu.',
        };
    }

    private function dejDuvodDluhuPodleUcasti(
        UcastNaGc $ucastNaGc,
        ?int      $rokPosledniUcasti,
    ): string {
        // O dřívějším ročníku se smí psát jen tomu, kdo na nějakém opravdu byl -
        // část dlužníků na GC nikdy nedorazila a dluh má z nedokončené přihlášky.
        $nedorazil = $rokPosledniUcasti === null
            ? 'Na tvém GC účtu evidujeme nedoplatek.'
            : "Z GameConu $rokPosledniUcasti ti na GC účtu zůstal nedoplatek.";

        return match ($ucastNaGc) {
            UcastNaGc::PRITOMEN => 'V systému ti nicméně zbyly nějaké nedoplatky, pravděpodobně za last moment aktivity nebo jiné objednávky během GC.',
            UcastNaGc::JEN_PRIHLASEN => 'I tak ti ale na GC účtu zůstal nedoplatek za objednávky z letošní přihlášky.',
            UcastNaGc::NEDORAZIL     => $nedorazil,
        };
    }

    private function dejZaverPodleUcasti(
        TypUpominky $typUpominky,
        UcastNaGc   $ucastNaGc,
    ): string {
        $jeMesicni = $typUpominky->textovaVarianta() === TypUpominky::MESIC;

        // Zpětné vazby dává smysl připomínat jen tomu, kdo na GC opravdu byl.
        if ($ucastNaGc !== UcastNaGc::PRITOMEN) {
            return 'Děkujeme a snad se uvidíme na některém z dalších ročníků!';
        }

        $dotazniky = $jeMesicni
            ? 'PS: stále ještě případně zbývá trochu času na vyplnění zpětné vazby na GC i jednotlivé aktivity. Dotazníky najdeš tady: https://gamecon.cz/prakticke-informace#dotazniky. Jejich vyplnění je pro další zlepšování akce velmi důležité.'
            : 'PS: rovnou si dovolíme i připomenout možnost vyplnění zpětných vazeb na GC i na jednotlivé aktivity, které jsou pro nás velmi důležité pro další zlepšování. Najdeš je tady: https://gamecon.cz/prakticke-informace#dotazniky';

        return "Moc děkujeme a těšíme se zase za rok!\n\n$dotazniky";
    }

    public function dejQrKodyProUpominku(Uzivatel $uzivatel): array
    {
        return match ($uzivatel->stat()) {
            Stat::CZ => [
                'qr-platba-cz.png' => $uzivatel->finance()->dejQrKodProCeskouPlatbu(),
            ],
            default => [
                'qr-platba-cz.png'   => $uzivatel->finance()->dejQrKodProCeskouPlatbu(),
                'qr-platba-sk.png'   => $uzivatel->finance()->dejQrKodProSlovenskouPlatbu(),
                'qr-platba-sepa.png' => $uzivatel->finance()->dejQrKodProSepaPlatbu(),
            ],
        };
    }

    private function odeslInfoCfo(
        TypUpominky        $typUpominky,
        int                $rocnik,
        int                $pocetEmailu,
        int                $pocetPreskocenych,
        int                $pocetPodPrahem,
        float              $minimalniCastka,
        \DateTimeInterface $konecGc,
        ?GcMail            $prikladEmailu,
    ): void {
        $prahVKc    = (int)ceil($minimalniCastka);
        $cfosEmaily = Uzivatel::cfosEmaily();
        $oddelovac  = str_repeat('═', 50);
        $nazev      = $this->dejNazevUpominky($typUpominky);

        $predmet = match ($typUpominky) {
            TypUpominky::TYDEN => "Upomínky dlužníkům: odesláno $pocetEmailu e-mailů",
            TypUpominky::MESIC => "PŘIPOMÍNKA upomínek dlužníkům: odesláno $pocetEmailu e-mailů",
            TypUpominky::RUCNI => "Ručně rozeslané upomínky dlužníkům: odesláno $pocetEmailu e-mailů",
            TypUpominky::VLASTNI => "Upomínky vlastním zněním: odesláno $pocetEmailu e-mailů",
        };

        $typTextu = match ($typUpominky) {
            TypUpominky::TYDEN => 'Upomínkové',
            TypUpominky::MESIC => 'Připomínkové',
            TypUpominky::RUCNI => 'Ručně rozeslané upomínkové',
            TypUpominky::VLASTNI => 'Vlastním zněním rozeslané upomínkové',
        };

        $zprava = <<<TEXT
$typTextu e-maily dlužníkům ($nazev po skončení GameConu $rocnik) byly odeslány.

Počet dlužníků: $pocetEmailu
Přeskočeno (upomínku už dostali dřív): $pocetPreskocenych
Přeskočeno (dluh pod prahem {$prahVKc} Kč): $pocetPodPrahem
Konec GameConu: {$konecGc->format('d.m.Y H:i')}

$oddelovac

Příklad odeslaného emailu:

{$prikladEmailu?->dejPredmet()}

$oddelovac

{$prikladEmailu?->dejText()}
TEXT;

        (new GcMail($this->systemoveNastaveni))
            ->adresati($cfosEmaily
                ?: ['info@gamecon.cz'])
            ->predmet($predmet)
            ->text($zprava)
            ->odeslat(GcMail::FORMAT_TEXT);
    }

    private function jizOdeslano(
        TypUpominky $typUpominky,
        int         $rocnik,
    ): bool {
        return match ($typUpominky) {
                   TypUpominky::TYDEN => $this->upominaniTydenOdeslanoKdy($rocnik),
                   TypUpominky::MESIC => $this->upominaniMesicOdeslanoKdy($rocnik),
               } !== null;
    }

    /**
     * Zjistí, zda už bylo pro daný ročník odesláno první upomínkové upozornění (1 týden po)
     */
    private function upominaniTydenOdeslanoKdy(int $rocnik): ?\DateTimeInterface
    {
        return $this->posledniHromadnaAkceKdy(
            self::SKUPINA_UPOMINANI,
            $this->nazevAkceUpominaniTyden($rocnik),
        );
    }

    /**
     * Zjistí, zda už bylo pro daný ročník odesláno druhé upomínkové upozornění (1 měsíc po)
     */
    private function upominaniMesicOdeslanoKdy(int $rocnik): ?\DateTimeInterface
    {
        return $this->posledniHromadnaAkceKdy(
            self::SKUPINA_UPOMINANI,
            $this->nazevAkceUpominaniMesic($rocnik),
        );
    }
}
