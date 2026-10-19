<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Command\FioStazeniNovychPlateb;
use Gamecon\Kanaly\EmailovaSablona\EmailovaSablonaVykreslovac;
use Gamecon\Kanaly\EmailovaSablona\EmailoveSablony;
use Gamecon\Kanaly\EmailovaSablona\SystemoveHodnotyProSablony;
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

    private readonly EmailoveSablony $emailoveSablony;
    private readonly EmailovaSablonaVykreslovac $vykreslovac;

    public function __construct(
        private readonly SystemoveNastaveni        $systemoveNastaveni,
        private readonly JobResultLoggerInterface  $jobResultLogger,
        private readonly FioStazeniNovychPlateb    $fioStazeniNovychPlateb,
        private readonly UpominkaDluznikaLog       $upominkaDluznikaLog = new UpominkaDluznikaLog(),
        private readonly UcastNaGcPodleRoli        $ucastNaGcPodleRoli = new UcastNaGcPodleRoli(),
        private readonly UpominkaVlastniZneni      $upominkaVlastniZneni = new UpominkaVlastniZneni(),
    ) {
        $this->emailoveSablony = new EmailoveSablony($systemoveNastaveni);
        $this->vykreslovac     = new EmailovaSablonaVykreslovac(new SystemoveHodnotyProSablony($systemoveNastaveni));
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
            ->sablona(
                $typUpominky->maVlastniZneni()
                    ? UpominkaVlastniZneni::definice()->kod
                    : $this->kodSablonyUpominky($typUpominky, $ucastNaGc, $rokPosledniUcasti),
            )
            ->adresat($uzivatel->mail())
            ->predmet($this->dejEmailPredmet($typUpominky, $rocnik, $ucastNaGc, $rokPosledniUcasti))
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

    /**
     * The subject depends on how the debtor took part, because each text variant has its own subject
     * in the admin. For a custom wording neither the participation nor the last year are used.
     */
    public function dejEmailPredmet(
        TypUpominky $typUpominky,
        int         $rocnik,
        ?UcastNaGc  $ucastNaGc = null,
        ?int        $rokPosledniUcasti = null,
    ): string {
        if ($typUpominky->maVlastniZneni()) {
            return $this->vykreslovac->vykresliPredmet(
                UpominkaVlastniZneni::definice(),
                $this->dejVyplneneVlastniZneni($rocnik)->predmet,
            );
        }

        if ($ucastNaGc === null) {
            throw new \LogicException('Předmět standardní upomínky závisí na účasti dlužníka, ta se musí předat.');
        }

        return $this->emailoveSablony->vykresliPredmet(
            $this->kodSablonyUpominky($typUpominky, $ucastNaGc, $rokPosledniUcasti),
        );
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
        $promenne = [
            'jmeno' => $jmenoNick,
            'vs'    => $variabilniSymbol,
            'dluh'  => $dluh,
            'a'     => $koncovkaDlePohlavi,
        ];

        if ($typUpominky->maVlastniZneni()) {
            return $this->vykreslovac->vykresli(
                UpominkaVlastniZneni::definice(),
                '',
                $this->dejVyplneneVlastniZneni($rocnik)->text,
                $promenne,
            )->text;
        }

        $kodSablony = $this->kodSablonyUpominky($typUpominky, $ucastNaGc, $rokPosledniUcasti);
        if ($rokPosledniUcasti !== null && $ucastNaGc === UcastNaGc::NEDORAZIL) {
            $promenne['rok_posledni_ucasti'] = $rokPosledniUcasti;
        }

        return $this->emailoveSablony->vykresli($kodSablony, $promenne)->text;
    }

    /**
     * Only somebody who was really at some earlier year may be written to about it - part of the debtors
     * never showed up at GC and their debt comes from an unfinished registration. Hence two 'did not show up' variants.
     */
    private function kodSablonyUpominky(
        TypUpominky $typUpominky,
        UcastNaGc   $ucastNaGc,
        ?int        $rokPosledniUcasti,
    ): string {
        $varianta = match ($ucastNaGc) {
            UcastNaGc::PRITOMEN      => 'pritomen',
            UcastNaGc::JEN_PRIHLASEN => 'jen_prihlasen',
            UcastNaGc::NEDORAZIL     => $rokPosledniUcasti === null
                ? 'nedorazil_nikdy'
                : 'nedorazil_drive',
        };

        return 'upominka_' . $typUpominky->textovaVarianta()->value . '_' . $varianta;
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
        int                $pocetEmailu,
        int                $pocetPreskocenych,
        int                $pocetPodPrahem,
        float              $minimalniCastka,
        \DateTimeInterface $konecGc,
        ?GcMail            $prikladEmailu,
    ): void {
        $cfosEmaily = Uzivatel::cfosEmaily();

        $this->emailoveSablony
            ->vytvorMail('cfo_upominky_' . $typUpominky->value . '_odeslano', [
                'pocet'              => $pocetEmailu,
                'pocet_preskocenych' => $pocetPreskocenych,
                'pocet_pod_prahem'   => $pocetPodPrahem,
                'prah'               => (int)ceil($minimalniCastka),
                'konec_gc'           => $konecGc->format('d.m.Y H:i'),
                'priklad_predmet'    => $prikladEmailu?->dejPredmet() ?? '',
                'priklad_text'       => $prikladEmailu?->dejText() ?? '',
            ])
            ->adresati($cfosEmaily
                ?: ['info@gamecon.cz'])
            ->odeslat();
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
