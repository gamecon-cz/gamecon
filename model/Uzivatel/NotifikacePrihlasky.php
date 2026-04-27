<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Accounting;
use Gamecon\Accounting\PersonalAccount;
use Gamecon\Accounting\Transaction;
use Gamecon\Accounting\TransactionCategory;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Kanaly\GcMail;
use Gamecon\Shop\TypPredmetu;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Uzivatel;

class NotifikacePrihlasky
{
    private const ODESILATEL_MAILU = 'GameCon <info@gamecon.cz>';
    private const KATEGORIE_AKTIVITY  = 'Aktivity';
    private const KATEGORIE_UBYTOVANI = 'Ubytování';
    private const KATEGORIE_JIDLO     = 'Jídlo';
    private const KATEGORIE_MERCH     = 'Merch';
    private const KATEGORIE_VSTUPNE   = 'Dobrovolné vstupné';

    public function __construct(
        private readonly Uzivatel          $uzivatel,
        private readonly SystemoveNastaveni $systemoveNastaveni,
    ) {
    }

    /**
     * @return array<string, array<string, int>>
     */
    public function snapshotObjednavekZUctu(): array
    {
        $snapshot = $this->prazdnySnapshot();

        foreach ($this->uzivatel->aktivityRyzePrihlasene() as $aktivita) {
            $aktivity = &$snapshot[self::KATEGORIE_AKTIVITY];
            $this->prictiPolozku($aktivity, $aktivita->nazev(), 1);
            unset($aktivity);
        }

        foreach ($this->uzivatel->finance()->dejStrukturovanyPrehled() as $polozka) {
            if (!is_array($polozka) || !isset($polozka['typ'], $polozka['nazev'])) {
                continue;
            }
            $kategorie = $this->kategorieZeTypu((int)$polozka['typ']);
            if ($kategorie === null) {
                continue;
            }
            $pocet = isset($polozka['pocet'])
                ? max(1, (int)$polozka['pocet'])
                : 1;
            $cilovaKategorie = &$snapshot[$kategorie];
            $this->prictiPolozku($cilovaKategorie, (string)$polozka['nazev'], $pocet);
            unset($cilovaKategorie);
        }

        foreach (self::poradiKategorii() as $kategorie) {
            ksort($snapshot[$kategorie], SORT_NATURAL | SORT_FLAG_CASE);
        }

        return $snapshot;
    }

    public function odesliMailONovePrihlasce(): void
    {
        $rocnik = $this->systemoveNastaveni->rocnik();
        $predmet = "Úspěšné přihlášení na GameCon {$rocnik}";
        $vypisFinanci = $this->vypisFinanciZUctu();
        $paticka = $this->formatPaticka();
        $a = $this->uzivatel->koncovkaDlePohlavi();

        $text = <<<TEXT
Ahoj!

Děkujeme, právě ses úspěšně přihlásil{$a} na GameCon {$rocnik}.
Zkontroluj, že máš všechny údaje správně vyplněné. Pokud máš objednané ubytování, nezapomeň na údaje z dokladu totožnosti.

Co máš aktuálně objednané:
{$vypisFinanci}

-
Přihlášku můžeš upravit podle potřeby.
Budeme se těšit na GameConu!

Organizační tým GC{$rocnik}

-
{$paticka}
TEXT;

        $this->odesliUcastnikovi($predmet, $text);
    }

    /**
     * @param array<string, array<string, int>> $predchoziSnapshot
     * @param array<string, array<string, int>> $aktualniSnapshot
     */
    public function odesliMailOZmenePrihlasky(
        array $predchoziSnapshot,
        array $aktualniSnapshot,
    ): void {
        $rocnik = $this->systemoveNastaveni->rocnik();
        $predmet = "Změna v přihlášce na GameCon {$rocnik}";
        $rozdilObjednavek = $this->formatRozdilObjednavek($predchoziSnapshot, $aktualniSnapshot);
        $vypisFinanci = $this->vypisFinanciZUctu();
        $paticka = $this->formatPaticka();

        $text = <<<TEXT
Ahoj!

Tvoje přihláška na GameCon {$rocnik} byla právě aktualizována.

Co se změnilo?
{$rozdilObjednavek}

Co máš aktuálně objednané:
{$vypisFinanci}

-
Budeme se těšit na GameConu!

Organizační tým GC{$rocnik}

-
{$paticka}
TEXT;

        $this->odesliUcastnikovi($predmet, $text);
    }

    public function odesliMailOZruseniPrihlasky(string $vypisFinanciPredZrusenim): void
    {
        $rocnik = $this->systemoveNastaveni->rocnik();
        $predmet = "Zrušení přihlášky na GameCon {$rocnik}";
        $financniBlok = $this->formatFinancniBlokProZruseni();
        $paticka = $this->formatPaticka();

        $text = <<<TEXT
Ahoj!

Tvoje přihláška na GameCon {$rocnik} byla zrušena.

Co bylo zrušeno:
{$vypisFinanciPredZrusenim}

{$financniBlok}

-
Mrzí nás, že se letos na GameConu neuvidíme.

Organizační tým GC{$rocnik}

-
{$paticka}
TEXT;

        $this->odesliUcastnikovi($predmet, $text);
    }

    public function vypisFinanciZUctu(bool $vcetneUpozorneniNaNedoplatek = true): string
    {
        $ucet = Accounting::getPersonalFinance($this->uzivatel, showDiscounts: true);

        $radky = [
            ...$this->formatKategorieFinanci($ucet, TransactionCategory::ACTIVITY, 'Aktivity', prevratitZnamenko: true),
            ...$this->formatKategorieFinanci($ucet, TransactionCategory::FOOD, 'Strava', prevratitZnamenko: true),
            ...$this->formatKategorieFinanci($ucet, TransactionCategory::SHOP_ITEMS, 'Předměty', prevratitZnamenko: true),
            ...$this->formatKategorieFinanci($ucet, TransactionCategory::ACCOMMODATION, 'Ubytování', prevratitZnamenko: true),
            ...$this->formatKategorieFinanci($ucet, TransactionCategory::VOLUNTARY_DONATION, 'Dobrovolné vstupné', prevratitZnamenko: true),
        ];

        $celkovaCena = -$this->sumaKategorie($ucet, [
            TransactionCategory::ACTIVITY,
            TransactionCategory::FOOD,
            TransactionCategory::SHOP_ITEMS,
            TransactionCategory::ACCOMMODATION,
            TransactionCategory::VOLUNTARY_DONATION,
        ]);
        $radky[] = 'Celková cena: ' . $this->formatCastka((float)$celkovaCena);
        $radky[] = '';

        $radky = [
            ...$radky,
            ...$this->formatKategorieFinanci($ucet, TransactionCategory::LEFTOVER_FROM_LAST_YEAR, 'Zůstatek z minulých let'),
            ...$this->formatKategorieFinanci($ucet, TransactionCategory::MANUAL_MOVEMENTS, 'Připsané platby'),
        ];

        $stavFinanci = $ucet->getTotal();
        $radky[] = 'Stav financí: ' . $this->formatCastka((float)$stavFinanci);

        if ($vcetneUpozorneniNaNedoplatek && $stavFinanci < 0) {
            $datumHromadnehoOdhlaseni = $this->systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy()
                ->format(DateTimeCz::FORMAT_DATUM_A_CAS_STANDARD);
            $nedoplatek = $this->formatCastka((float)abs($stavFinanci));
            $radky[] = '';
            $radky[] = "Tvoje přihláška zatím není plně uhrazena. K jejímu dokončení zbývá doplatit nedoplatek {$nedoplatek}. Děkujeme za jeho co nejdřívější úhradu.";
            $radky[] = "Datum nejbližšího odhlášení neplatičů je {$datumHromadnehoOdhlaseni}.";
            $radky[] = 'Detail financí a platební údaje včetně QR kódu najdeš v přehledu financí: ' . $this->odkazNaFinance();
        }

        return implode("\n", $radky);
    }

    /**
     * @param array<string, int> $kategorie
     */
    private function prictiPolozku(
        array &$kategorie,
        string $nazev,
        int $pocet,
    ): void {
        $cistyNazev = $this->ocistiNazev($nazev);
        if ($cistyNazev === '') {
            return;
        }
        $kategorie[$cistyNazev] = ($kategorie[$cistyNazev] ?? 0) + $pocet;
    }

    private function ocistiNazev(string $nazev): string
    {
        return trim(html_entity_decode(strip_tags($nazev), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function kategorieZeTypu(int $typ): ?string
    {
        return match ($typ) {
            TypPredmetu::UBYTOVANI => self::KATEGORIE_UBYTOVANI,
            TypPredmetu::JIDLO => self::KATEGORIE_JIDLO,
            TypPredmetu::PREDMET, TypPredmetu::TRICKO => self::KATEGORIE_MERCH,
            TypPredmetu::VSTUPNE => self::KATEGORIE_VSTUPNE,
            default => null,
        };
    }

    /**
     * @return array<string>
     */
    private static function poradiKategorii(): array
    {
        return [
            self::KATEGORIE_AKTIVITY,
            self::KATEGORIE_UBYTOVANI,
            self::KATEGORIE_JIDLO,
            self::KATEGORIE_MERCH,
            self::KATEGORIE_VSTUPNE,
        ];
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function prazdnySnapshot(): array
    {
        return [
            self::KATEGORIE_AKTIVITY => [],
            self::KATEGORIE_UBYTOVANI => [],
            self::KATEGORIE_JIDLO => [],
            self::KATEGORIE_MERCH => [],
            self::KATEGORIE_VSTUPNE => [],
        ];
    }

    /**
     * @param array<string, array<string, int>> $predchoziSnapshot
     * @param array<string, array<string, int>> $aktualniSnapshot
     */
    private function formatRozdilObjednavek(
        array $predchoziSnapshot,
        array $aktualniSnapshot,
    ): string {
        $pridano = [];
        $odebrano = [];

        foreach (self::poradiKategorii() as $kategorie) {
            $predchoziKategorie = $predchoziSnapshot[$kategorie] ?? [];
            $aktualniKategorie = $aktualniSnapshot[$kategorie] ?? [];

            $nazvy = array_values(
                array_unique(
                    [
                        ...array_keys($predchoziKategorie),
                        ...array_keys($aktualniKategorie),
                    ],
                ),
            );
            sort($nazvy, SORT_NATURAL | SORT_FLAG_CASE);

            foreach ($nazvy as $nazev) {
                $predchoziPocet = (int)($predchoziKategorie[$nazev] ?? 0);
                $aktualniPocet = (int)($aktualniKategorie[$nazev] ?? 0);
                $rozdil = $aktualniPocet - $predchoziPocet;
                if ($rozdil > 0) {
                    $pridano[] = $this->formatPolozkaRozdilu('+', $kategorie, $nazev, $rozdil);
                } elseif ($rozdil < 0) {
                    $odebrano[] = $this->formatPolozkaRozdilu('-', $kategorie, $nazev, abs($rozdil));
                }
            }
        }

        if ($pridano === [] && $odebrano === []) {
            return 'Nezaznamenali jsme změny v aktivitách, ubytování, jídle, merchi ani vstupném.';
        }

        $radky = [];
        if ($pridano !== []) {
            $radky[] = 'Přidáno:';
            $radky = [...$radky, ...$pridano];
        }
        if ($odebrano !== []) {
            if ($radky !== []) {
                $radky[] = '';
            }
            $radky[] = 'Odebráno:';
            $radky = [...$radky, ...$odebrano];
        }

        return implode("\n", $radky);
    }

    private function formatPolozkaRozdilu(
        string $prefix,
        string $kategorie,
        string $nazev,
        int $pocet,
    ): string {
        $mnozstvi = $pocet > 1
            ? " ({$pocet}x)"
            : '';

        return "{$prefix} {$kategorie}: {$nazev}{$mnozstvi}";
    }

    /**
     * @return array<string>
     */
    private function formatKategorieFinanci(
        PersonalAccount $ucet,
        TransactionCategory $kategorie,
        string $nazevKategorie,
        bool $prevratitZnamenko = false,
    ): array {
        $koeficient = $prevratitZnamenko
            ? -1
            : 1;
        $sumaKategorie = $koeficient * $this->sumaKategorie($ucet, [$kategorie]);

        $radky = [
            $nazevKategorie . ': ' . $this->formatCastka((float)$sumaKategorie),
        ];
        foreach ($ucet->getTransactions() as $transakce) {
            if ($transakce->getCategory() !== $kategorie) {
                continue;
            }
            foreach ($transakce->getSplits() as $split) {
                $castka = $koeficient * $split->getAmount();
                $popis = $this->ocistiNazev($split->getDescription());
                $radky[] = '- ' . $popis . ': ' . $this->formatCastka((float)$castka);
            }
        }

        if (count($radky) === 1) {
            $radky[] = '- žádné';
        }
        $radky[] = '';

        return $radky;
    }

    /**
     * @param array<TransactionCategory> $kategorie
     */
    private function sumaKategorie(
        PersonalAccount $ucet,
        array $kategorie,
    ): int {
        return array_reduce(
            array_filter(
                $ucet->getTransactions(),
                static fn(Transaction $transakce) => in_array($transakce->getCategory(), $kategorie, true),
            ),
            static function (int $carry, Transaction $transakce): int {
                return $carry + $transakce->getTotalAmount();
            },
            0,
        );
    }

    private function formatFinancniBlokProZruseni(): string
    {
        $stav = Finance::zaokouhli($this->uzivatel->finance()->stav());
        $odkazNaFinance = $this->odkazNaFinance();

        if ($stav > 0) {
            $preplatek = $this->formatCastka($stav);
            return <<<TEXT
Na účtu máš přeplatek {$preplatek}.
Peníze si můžeš nechat převést na další ročník, nebo si je nechat poslat zpět na účet – v tom případě se ozvi na finance@gamecon.cz.
Detail svého finančního stavu najdeš v přehledu financí: {$odkazNaFinance}
TEXT;
        }

        if ($stav < 0) {
            $nedoplatek = $this->formatCastka(abs($stav));
            return <<<TEXT
Na účtu máš nedoplatek {$nedoplatek}. Děkujeme za jeho co nejdřívější úhradu.
Detail financí a platební údaje včetně QR kódu najdeš v přehledu financí: {$odkazNaFinance}
TEXT;
        }

        return <<<TEXT
Finanční stav tvého účtu je vyrovnaný. Nemáš žádný přeplatek ani nedoplatek.
Detail svého finančního stavu najdeš v přehledu financí: {$odkazNaFinance}
TEXT;
    }

    private function formatCastka(float $castka): string
    {
        $castka = Finance::zaokouhli($castka);
        if ((float)(int)$castka === $castka) {
            return (int)$castka . ' Kč';
        }
        $formatovanaCastka = number_format($castka, 2, ',', ' ');
        $formatovanaCastka = rtrim(rtrim($formatovanaCastka, '0'), ',');

        return $formatovanaCastka . ' Kč';
    }

    private function odkazNaFinance(): string
    {
        return $this->urlWebu() . '/finance';
    }

    private function formatPaticka(): string
    {
        $urlWebu = $this->urlWebu();

        return <<<TEXT
{$urlWebu}

GameCon na Facebooku: {$urlWebu}/facebook
GameCon na Instagramu: {$urlWebu}/instagram
GameCon na Discordu: {$urlWebu}/discord
TEXT;
    }

    private function urlWebu(): string
    {
        $urlWebu = defined('URL_WEBU')
            ? (string)URL_WEBU
            : 'https://gamecon.cz';

        return rtrim($urlWebu, '/');
    }

    private function odesliUcastnikovi(
        string $predmet,
        string $text,
    ): void {
        $mail = $this->uzivatel->mail();
        if ($mail === null || $mail === '') {
            return;
        }

        (new GcMail($this->systemoveNastaveni))
            ->odesilatel(self::ODESILATEL_MAILU)
            ->adresat($mail)
            ->predmet($predmet)
            ->text($text)
            ->odeslat(GcMail::FORMAT_TEXT);
    }
}
