<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Command\FioStazeniNovychPlateb;
use Gamecon\Kanaly\GcMail;
use Gamecon\Logger\JobResultLogger;
use Gamecon\Logger\LogHomadnychAkciTrait;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Dto\Dluznik;
use Gamecon\Uzivatel\Enum\TypUpominky;
use Uzivatel;

/**
 * Třída zodpovídající za upomínání dlužníků (uživatelů se záporným zůstatkem)
 */
class UpominaniDluzniku
{
    use LogHomadnychAkciTrait;

    private const SKUPINA_UPOMINANI = 'upominani-dluzniku';

    public function __construct(
        private readonly SystemoveNastaveni     $systemoveNastaveni,
        private readonly JobResultLogger        $jobResultLogger,
        private readonly FioStazeniNovychPlateb $fioStazeniNovychPlateb,
    ) {
    }

    /**
     * Najde uživatele, kteří dluží GameConu (mají záporný zůstatek)
     *
     * @return Dluznik[] Pole dlužníků
     */
    public function najdiDluzniky(): array
    {
        $dluznici = [];
        foreach (Uzivatel::zVsech(true) as $uzivatel) {
            // Přepočítáme aktuální stav pomocí Finance třídy pro daný rok
            $zustatek = $uzivatel->finance()->stav();

            if ($zustatek >= 0) {
                continue; // Přeskočit, pokud aktuální přepočet říká, že nedluží
            }

            $dluznici[] = new Dluznik(
                uzivatel: $uzivatel,
                dluh: -$zustatek, // Převedeme na kladné číslo
            );
        }

        return $dluznici;
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

        $dluzniciSZustatkem = $this->najdiDluzniky();

        if (count($dluzniciSZustatkem) === 0) {
            $nazev = $this->dejNazevUpominky($typUpominky);
            $this->jobResultLogger->logs("Upomínání dlužníků ($nazev): Žádní dlužníci k upomínání");

            return -1;
        }

        $pocetOdeslanychEmailu = 0;
        $posledniGcMail        = null;

        foreach ($dluzniciSZustatkem as $uzivatelSDluhem) {
            $uzivatel = $uzivatelSDluhem->uzivatel;
            if (!$uzivatel->mail()) {
                continue;
            }

            $dluh             = (int)round($uzivatelSDluhem->dluh);
            $variabilniSymbol = $uzivatel->id();

            $predmet = $this->dejEmailPredmet($typUpominky, $rocnik);
            $zprava  = $this->dejEmailZpravu($typUpominky, $dluh, $variabilniSymbol);

            $gcMail = (new GcMail($this->systemoveNastaveni))
                ->adresat($uzivatel->mail())
                ->predmet($predmet)
                ->text($zprava);

            $qrKod = $uzivatel->finance()->dejQrKodProPlatbu();
            if ($qrKod) {
                // Uložit QR kód do dočasného souboru
                $qrSoubor = tempnam($this->systemoveNastaveni->cacheDir(), 'upominani_qr_') . '.png';
                file_put_contents($qrSoubor, $qrKod->getString());
                $gcMail->prilohaSoubor($qrSoubor)->prilohaNazev('qr-platba.png');
            }

            $gcMail->odeslat(GcMail::FORMAT_TEXT);

            // Smazat dočasný QR soubor
            if (isset($qrSoubor) && file_exists($qrSoubor)) {
                @unlink($qrSoubor);
            }

            $posledniGcMail = $gcMail;
            $pocetOdeslanychEmailu++;
            set_time_limit(10); // Prodloužit timeout pro každý e-mail
        }

        // Zaloguj odeslání do databáze
        match ($typUpominky) {
            TypUpominky::TYDEN => $this->zalogujUpominaniTyden($rocnik, $pocetOdeslanychEmailu),
            TypUpominky::MESIC => $this->zalogujUpominaniMesic($rocnik, $pocetOdeslanychEmailu),
        };

        // Poslat CFO informaci o počtu odeslaných e-mailů
        $this->odeslInfoCfo($typUpominky, $rocnik, $pocetOdeslanychEmailu, $konecGc, $posledniGcMail);

        $nazev = $this->dejNazevUpominky($typUpominky);
        logs("Upomínání dlužníků ($nazev): Odesláno $pocetOdeslanychEmailu e-mailů");

        return $pocetOdeslanychEmailu;
    }

    private function dejNazevUpominky(TypUpominky $typUpominky): string
    {
        return match ($typUpominky) {
            TypUpominky::TYDEN => '1 týden',
            TypUpominky::MESIC => '1 měsíc',
        };
    }

    private function dejEmailPredmet(
        TypUpominky $typUpominky,
        int         $rocnik,
    ): string {
        return match ($typUpominky) {
            TypUpominky::TYDEN => "GameCon $rocnik - nedoplatky",
            TypUpominky::MESIC => "GameCon $rocnik - PŘIPOMÍNKA nedoplatků",
        };
    }

    private function dejEmailZpravu(
        TypUpominky $typUpominky,
        int         $dluh,
        int         $variabilniSymbol,
    ): string {
        $ucetCz = UCET_CZ;
        $iban   = IBAN;

        return match ($typUpominky) {
            TypUpominky::TYDEN => <<<TEXT
Ahoj!

Doufáme, že tě letošní GameCon bavil!

V systému ti nicméně zbyly nějaké nedoplatky, pravděpodobně za last moment aktivity nebo jiné objednávky během GC. Konkrétně se jedná o $dluh korun. Můžeš nám prosím nedoplatek co nejdřív srovnat?

Stačí jako obvykle poslat danou částku na GC účet $ucetCz ($iban) s variabilním symbolem $variabilniSymbol, popř. využít platební QR kód přiložený níže.

Jakékoliv dotazy, nejasnosti nebo reklamace směřuj prosím v odpovědi na tento e-mail, nebo na finance@gamecon.cz.

Moc děkujeme a těšíme se zase za rok!

PS: rovnou si dovolíme i připomenout možnost vyplnění zpětných vazeb na GC i na jednotlivé aktivity, které jsou pro nás velmi důležité pro další zlepšování. Najdeš je tady: https://gamecon.cz/prakticke-informace#dotazniky
TEXT,
            TypUpominky::MESIC => <<<TEXT
Ahoj!

Doufáme, že ti na GameCon zůstaly krásné vzpomínky!

Na GC účtu ti nicméně zůstal i nedoplatek $dluh korun a nám už se velmi blíží účetní uzávěrka – můžeš svůj GC účet prosím co nejdřív srovnat? Částku zašli na GC účet $ucetCz ($iban) s variabilním symbolem $variabilniSymbol, popř. využij platební QR kód přiložený níže. Moc děkujeme!

Jakékoliv dotazy, nejasnosti nebo reklamace směřuj prosím v odpovědi na tento e-mail, nebo na finance@gamecon.cz.

Těšíme se zase za rok!

PS: stále ještě případně zbývá trochu času na vyplnění zpětné vazby na GC i jednotlivé aktivity. Dotazníky najdeš tady: https://gamecon.cz/prakticke-informace#dotazniky. Jejich vyplnění je pro další zlepšování akce velmi důležité.
TEXT,
        };
    }

    private function odeslInfoCfo(
        TypUpominky        $typUpominky,
        int                $rocnik,
        int                $pocetEmailu,
        \DateTimeInterface $konecGc,
        ?GcMail            $prikladEmailu,
    ): void {
        $cfosEmaily = Uzivatel::cfosEmaily();
        $oddelovac  = str_repeat('═', 50);
        $nazev      = $this->dejNazevUpominky($typUpominky);

        $predmet = match ($typUpominky) {
            TypUpominky::TYDEN => "Upomínky dlužníkům: odesláno $pocetEmailu e-mailů",
            TypUpominky::MESIC => "PŘIPOMÍNKA upomínek dlužníkům: odesláno $pocetEmailu e-mailů",
        };

        $typTextu = match ($typUpominky) {
            TypUpominky::TYDEN => 'Upomínkové',
            TypUpominky::MESIC => 'Připomínkové',
        };

        $zprava = <<<TEXT
$typTextu e-maily dlužníkům ($nazev po skončení GameConu $rocnik) byly odeslány.

Počet dlužníků: $pocetEmailu
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
