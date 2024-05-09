<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\XTemplate\XTemplate;
use Nahled;

class Loga
{
    public static function logaSponzoru(): static
    {
        return new static(ADRESAR_WEBU_S_OBRAZKY . '/soubory/systemove/sponzori');
    }

    public static function logaPartneru(): static
    {
        return new static(ADRESAR_WEBU_S_OBRAZKY . '/soubory/systemove/partneri');
    }

    public function __construct(
        private readonly string $adresarLog,
    )
    {
        if (!is_dir($this->adresarLog)) {
            throw new \RuntimeException("Adresář '{$this->adresarLog}' neexistuje nebo nelez přečíst.");
        }
    }

    public function vypisDoSablony(
        XTemplate $template,
        string    $templateBlock,
    )
    {
        foreach ($this->serazenaLoga() as ['src' => $src, 'url' => $url]) {
            $template->assign(['src' => $src, 'url' => $url]);
            $template->parse($templateBlock);
        }
    }

    public function serazenaLoga()
    {
        $soubory = glob($this->adresarLog . '/*', GLOB_NOSORT);
        ['obrazky' => $obrazky, 'radici_soubor' => $radiciSoubor] = $this->rozdel($soubory);
        $obrazky  = $this->vyhodVyrazene($obrazky);
        $serazene = $this->serad($obrazky, $radiciSoubor);

        foreach ($serazene as $obrazek) {
            if (substr($obrazek, 0) === '_') {
                continue;
            }
            $info = pathinfo($obrazek);
            // odstraníme prefix pro řazení 'číslo_'
            $urlPartnera = preg_replace('~^\d+_~', '', $info['filename']);
            yield [
                'src' => Nahled::zeSouboru($obrazek)->pasuj(120, 60),
                'url' => 'http://' . $urlPartnera,
            ];
        }
    }

    private function serad(array $obrazky, ?string $radiciSoubor)
    {
        $klicovaneObrazky = [];
        foreach ($obrazky as $obrazek) {
            $klicovaneObrazky[$this->baseNazev($obrazek)] = $obrazek;
        }
        ksort($klicovaneObrazky);

        $razeniZeSouboru = $radiciSoubor
            ? str_getcsv(file_get_contents($radiciSoubor), "\n")
            : false;

        if (!$razeniZeSouboru) {
            return $klicovaneObrazky; // seřazené abecedně
        }

        $razeniZeSouboru = array_filter(
            $razeniZeSouboru,
            // odtranění prázdných řádků a #komentářů
            fn(string $radek) => trim($radek) !== '' && !str_starts_with(trim($radek), '#'),
        );

        if (count($razeniZeSouboru) === 0) {
            return $klicovaneObrazky; // seřazené abecedně
        }

        $baseRazeniZeSouboru = array_map(fn(string $nazev) => $this->baseNazev($nazev), $razeniZeSouboru);
        $serazeneObrazky     = [];
        foreach ($baseRazeniZeSouboru as $baseNazevZeSouboru) {
            if (array_key_exists($baseNazevZeSouboru, $klicovaneObrazky)) {
                $serazeneObrazky[$baseNazevZeSouboru] = $klicovaneObrazky[$baseNazevZeSouboru];
            }
        }

        if (!$serazeneObrazky) {
            return $klicovaneObrazky; // seřazené abecedně
        }

        foreach ($klicovaneObrazky as $nazev => $obrazek) {
            if (!array_key_exists($nazev, $serazeneObrazky)) {
                $serazeneObrazky[$nazev] = $obrazek; // doplníme obrázky seřazené podle seznamu v souboru ještě obrázky s pořadím abecedním, pokud nějaké v souboru nebyly
            }
        }
        // seřazené nejdříve podle seznamu v souboru a pokud v něm nebyly, tak přidané ke konci v abecedním pořadí
        return $serazeneObrazky;
    }

    /**
     * Například /foo/bar/www.Albi.cz => albi
     */
    private function baseNazev(string $soubor): string
    {
        $basename = basename($soubor);
        $base     = preg_replace('~(^www[.]|[.][^.]+$)~', '', $basename);
        return mb_strtolower($base);
    }

    private function rozdel(array $soubory): array
    {
        $obrazky = [];
        foreach ($soubory as $index => $soubor) {
            if (str_starts_with(mime_content_type($soubor), 'image/')) {
                $obrazky[] = $soubor;
                unset($soubory[$index]);
            }
        }
        $radiciSoubory = array_filter(
            $soubory,
            fn(string $soubor) => str_ends_with($soubor, 'RAZENI.csv'),
        );
        $radiciSoubor  = reset($radiciSoubory) ?: null;
        return [
            'obrazky'       => $obrazky,
            'radici_soubor' => $radiciSoubor,
        ];
    }

    private function vyhodVyrazene(array $obrazky): array
    {
        return array_filter(
            $obrazky,
            // vyřazený obrázek, jeho název začíná podtržítkem '_'
            fn(string $obrazek) => substr($obrazek, 0) !== '_',
        );
    }
}
