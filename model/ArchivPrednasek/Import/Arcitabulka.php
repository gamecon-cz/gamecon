<?php

declare(strict_types=1);

namespace Gamecon\ArchivPrednasek\Import;

use Gamecon\ArchivPrednasek\NazevPrednasky;
use Gamecon\ArchivPrednasek\YoutubeOdkaz;

/**
 * Reads the sheet „Arcitabulka přednášek“ (TSV, kept unchanged in migrace/pomocne/archiv-prednasek) and unifies it.
 * Only the title (A), the speakers (F), the year (K), the keywords (L) and the YouTube link (M) are used,
 * the ratings stay out (záměr).
 */
final class Arcitabulka
{
    private const int SLOUPEC_NAZEV = 0;
    private const int SLOUPEC_PREDNASEJICI = 5;
    private const int SLOUPEC_ROK = 10;
    private const int SLOUPEC_KLICOVA_SLOVA = 11;
    private const int SLOUPEC_YOUTUBE = 12;

    /**
     * "x" means "we do not know"
     */
    private const string NEZNAME = 'x';

    /**
     * both mean there is no recording yet, an admin adds the link later
     */
    private const array BEZ_ZAZNAMU = ['', 'x', 'záznam není', 'záznam dodáme'];

    /**
     * @var array<string, string> lower-case keyword => the spelling met first
     */
    private array $prvniZapisSlova = [];

    /**
     * @param array<string, string>       $klicovaSlova        lower-case keyword => unified one
     * @param array<string, string>       $jmenaPrednasejicich name as written in the sheet => unified one
     * @param array<string, string>       $opravyNazvu         title as written in the sheet (trimmed) => corrected one
     * @param array<string, list<string>> $clenoveSkupin       unified name of a group => its members, who speak with it
     */
    public function __construct(
        private readonly array $klicovaSlova = DataArcitabulky::KLICOVA_SLOVA,
        private readonly array $jmenaPrednasejicich = DataArcitabulky::JMENA_PREDNASEJICICH,
        private readonly array $opravyNazvu = DataArcitabulky::OPRAVY_NAZVU,
        private readonly array $clenoveSkupin = DataArcitabulky::CLENOVE_SKUPIN,
    ) {
    }

    /**
     * @return list<RadekArcitabulky>
     */
    public function nactiSoubor(string $cesta): array
    {
        $obsah = file_get_contents($cesta);
        if ($obsah === false) {
            throw new \RuntimeException("Arcitabulku přednášek '{$cesta}' nelze přečíst");
        }
        $radky = preg_split('~\r\n|\n|\r~', $obsah) ?: [];
        $vysledek = [];
        foreach ($radky as $index => $radek) {
            // the first line is the header
            if ($index === 0 || trim($radek) === '') {
                continue;
            }
            $vysledek[] = $this->radek(explode("\t", $radek), $index + 1);
        }

        return $vysledek;
    }

    /**
     * @param list<string> $sloupce
     */
    public function radek(array $sloupce, int $cisloRadku): RadekArcitabulky
    {
        $puvodniNazev = trim($sloupce[self::SLOUPEC_NAZEV] ?? '');
        if ($puvodniNazev === '') {
            throw new \RuntimeException("Řádek {$cisloRadku} arcitabulky nemá název přednášky");
        }
        $rok = trim($sloupce[self::SLOUPEC_ROK] ?? '');
        if (! preg_match('~^\d{4}$~', $rok)) {
            throw new \RuntimeException("Řádek {$cisloRadku} arcitabulky má neplatný rok '{$rok}'");
        }

        return new RadekArcitabulky(
            cisloRadku: $cisloRadku,
            puvodniNazev: $puvodniNazev,
            nazev: NazevPrednasky::uprav($this->opravyNazvu[$puvodniNazev] ?? $puvodniNazev),
            rok: (int) $rok,
            prednasejici: $this->prednasejici($sloupce[self::SLOUPEC_PREDNASEJICI] ?? ''),
            klicovaSlova: $this->klicovaSlova($sloupce[self::SLOUPEC_KLICOVA_SLOVA] ?? ''),
            youtubeId: $this->youtubeId($sloupce[self::SLOUPEC_YOUTUBE] ?? '', $cisloRadku),
        );
    }

    /**
     * @return list<string>
     */
    private function prednasejici(string $bunka): array
    {
        if (trim($bunka) === self::NEZNAME) {
            return [];
        }
        $jmena = [];
        foreach (explode(',', $bunka) as $jmeno) {
            $jmeno = trim($jmeno);
            if ($jmeno === '' || $jmeno === self::NEZNAME) {
                continue;
            }
            $jmeno = $this->jmenaPrednasejicich[$jmeno] ?? $jmeno;
            // a group stays a speaker without a user, its members follow it with their profiles
            foreach ([$jmeno, ...($this->clenoveSkupin[$jmeno] ?? [])] as $prednasejici) {
                if (! in_array($prednasejici, $jmena, true)) {
                    $jmena[] = $prednasejici;
                }
            }
        }

        return $jmena;
    }

    /**
     * @return list<string>
     */
    private function klicovaSlova(string $bunka): array
    {
        $slova = [];
        // the trailing comma ("Talk,") leaves an empty item
        foreach (explode(',', $bunka) as $slovo) {
            $slovo = trim($slovo);
            if ($slovo === '') {
                continue;
            }
            $klic = mb_strtolower($slovo);
            $slovo = $this->klicovaSlova[$klic] ?? ($this->prvniZapisSlova[$klic] ??= $slovo);
            if (! in_array($slovo, $slova, true)) {
                $slova[] = $slovo;
            }
        }

        return $slova;
    }

    private function youtubeId(string $bunka, int $cisloRadku): ?string
    {
        $bunka = trim($bunka);
        if (in_array(mb_strtolower($bunka), self::BEZ_ZAZNAMU, true)) {
            return null;
        }

        // a link that is not understood must not be lost silently
        return YoutubeOdkaz::getIdZOdkazu($bunka)
            ?? throw new \RuntimeException("Řádek {$cisloRadku} arcitabulky má odkaz, který není video na YouTube: '{$bunka}'");
    }
}
