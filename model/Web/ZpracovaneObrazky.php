<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\XTemplate\XTemplate;
use Nahled;

readonly class ZpracovaneObrazky
{
    private const OBSAH = '/soubory/obsah';
    private const ADRESAR_SPONZORU = 'sponzori';
    private const ADRESAR_PARTNERU = 'partneri';
    private const ADRESAR_MATERIALU = 'materialy';
    private const ADRESAR_ESHOPU = 'eshop';

    public static function logaSponzoruTitulka(): static
    {
        return new static(self::adresarSponzoruTitulka());
    }

    public static function logaPartneruTitulka(): static
    {
        return new static(self::adresarPartneruTitulka());
    }

    public static function logaSponzoruPrehled(): static
    {
        return new static(self::adresarSponzoruPrehled());
    }

    public static function logaPartneruPrehled(): static
    {
        return new static(self::adresarPartneruPrehled());
    }

    public static function fotkyTitulka(): static
    {
        return new static(ADRESAR_WEBU_S_OBRAZKY . '/soubory/blackarrow/fotky');
    }

    public static function cislaTitulka(): static
    {
        return new static(ADRESAR_WEBU_S_OBRAZKY . '/soubory/blackarrow/cisla');
    }

    public static function kartyTitulka(): static
    {
        return new static(ADRESAR_WEBU_S_OBRAZKY . '/soubory/blackarrow/karty');
    }

    public static function linieTitulka(): static
    {
        return new static(ADRESAR_WEBU_S_OBRAZKY . '/soubory/systemove/linie');
    }

    public static function adresarSponzoruTitulka(): string
    {
        return self::adresarSponzoruPrehled() . '/titulka';
    }

    public static function adresarPartneruTitulka(): string
    {
        return self::adresarPartneruPrehled() . '/titulka';
    }

    public static function adresarSponzoruPrehled(): string
    {
        return self::adresarObsahu() . '/' . self::ADRESAR_SPONZORU;
    }

    public static function adresarPartneruPrehled(): string
    {
        return self::adresarObsahu() . '/' . self::ADRESAR_PARTNERU;
    }

    /** Fotky k medailonkům, `<id uživatele>.<přípona>`, viz @see \Uzivatel::fotka() */
    public static function adresarFotekUzivatelu(): string
    {
        return WWW . '/soubory/systemove/fotky';
    }

    /** Obrázky v hlavičkách linií, `org_<id typu>.<přípona>`, viz @see cestaObrazkuLinie() */
    public static function adresarObrazkuLinii(): string
    {
        return ADRESAR_WEBU_S_OBRAZKY . '/soubory/systemove/linie-ikony';
    }

    /** Obrázky pro texty stránek a novinek, dřív nahrávané přes FTP */
    public static function adresarObsahu(): string
    {
        return ADRESAR_WEBU_S_OBRAZKY . self::OBSAH;
    }

    /** Galerie e-shopu v přihlášce */
    public static function adresarObrazkuEshopu(int $rocnik = ROCNIK): string
    {
        return WWW . self::OBSAH . '/' . self::ADRESAR_MATERIALU . '/' . $rocnik . '/' . self::ADRESAR_ESHOPU;
    }

    /**
     * Loga spravuje modul Loga a galerii e-shopu modul Shop, každý s vlastním právem, editor textu do nich nesahá.
     *
     * @param string $cestaVuciObsahu například `sponzori/logo.png`
     */
    public static function jeAdresarJinehoModulu(string $cestaVuciObsahu): bool
    {
        $vzor = sprintf(
            '~^(?:%s|%s)/|^%s/[^/]+/%s/~i',
            self::ADRESAR_SPONZORU,
            self::ADRESAR_PARTNERU,
            self::ADRESAR_MATERIALU,
            self::ADRESAR_ESHOPU,
        );

        return preg_match($vzor, str_replace('\\', '/', $cestaVuciObsahu)) === 1;
    }

    /**
     * Cesta vůči kořeni webu, jak ji píšou texty (`soubory/obsah/blog/obrazek.jpg`), nebo null mimo web.
     */
    public static function cestaVuciWebu(string $absolutniCesta): ?string
    {
        // nejdřív podle kořene webu tak, jak je zapsaný: kdyby byl `soubory` symlink, skutečná cesta by vedla mimo web
        $zapsanyKoren = rtrim(ADRESAR_WEBU_S_OBRAZKY, '/') . '/';
        if (str_starts_with($absolutniCesta, $zapsanyKoren)) {
            $cesta = substr($absolutniCesta, strlen($zapsanyKoren));

            return in_array('..', explode('/', $cesta), true)
                ? null
                : $cesta;
        }
        $web = realpath(ADRESAR_WEBU_S_OBRAZKY);
        $adresar = realpath(dirname($absolutniCesta));
        if ($web === false || $adresar === false) {
            return null;
        }
        $cesta = $adresar . '/' . basename($absolutniCesta);

        return str_starts_with($cesta, $web . '/')
            ? substr($cesta, strlen($web) + 1)
            : null;
    }

    /**
     * Veřejná adresa souboru uloženého ve webu, s cestou zakódovanou pro URL.
     */
    public static function urlNaWebu(string $absolutniCesta): ?string
    {
        $cesta = self::cestaVuciWebu($absolutniCesta);

        return $cesta === null
            ? null
            : URL_WEBU . '/' . implode('/', array_map('rawurlencode', explode('/', $cesta)));
    }

    /**
     * Popisek obrázku v galerii e-shopu: název souboru bez přípony a bez úvodního čísla,
     * které určuje pořadí. `08_Účastnické triko.jpeg` → `Účastnické triko`
     */
    public static function popisekObrazkuEshopu(string $soubor): string
    {
        // název z FTP nemusí být UTF-8, preg_replace s `u` by pak vrátilo null
        $bezPripony = mb_scrub(basename($soubor, '.' . pathinfo($soubor, PATHINFO_EXTENSION)), 'UTF-8');
        $slova = (string) preg_replace('~[^[:alnum:]]+~u', ' ', $bezPripony);

        return trim((string) preg_replace('~^\d+~', '', $slova));
    }

    public function __construct(
        private string $adresarLog,
    ) {
    }

    public function vypisDoSablony(
        XTemplate $template,
        string    $templateBlock,
    ) {
        foreach ($this->serazenaLoga() as ['src' => $src, 'url' => $url]) {
            $template->assign(['src' => $src, 'url' => $url]);
            $template->parse($templateBlock);
        }
    }

    public function vypisDoSablonySorted(
        XTemplate $template,
        string    $templateBlock,
        int       $width = 120,
        int       $height = 60,
    ) {
        $i = 1;
        foreach ($this->serazenaLoga($width, $height) as ['src' => $src, 'url' => $url]) {
            $template->assign(['src' . $i => $src, 'url' . $i => $url]);
            $i++;
        }
        $template->parse($templateBlock);
    }

    /**
     * @return array<string, array{src: Nahled, url: string, name: string}>
     */
    public function seznamObrazku(): array
    {
        $seznam = [];
        foreach ($this->serazenaLoga() as ['src' => $src, 'url' => $url, 'name' => $name]) {
            $seznam[$name] = [
                'src' => $src,
                'url' => $url,
            ];
        }

        return $seznam;
    }

    /**
     * @return iterable{src: Nahled, url: string, name: string}
     */
    public function serazenaLoga(
        $width = 120,
        $height = 60,
    ): iterable {
        if (!is_dir($this->adresarLog)) {
            throw new \RuntimeException("Adresář '{$this->adresarLog}' neexistuje nebo nelze přečíst.");
        }
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
                'src'  => Nahled::zeSouboru($obrazek)->pasuj($width, $height),
                'name' => $info['filename'],
                'url'  => 'https://' . $urlPartnera,
            ];
        }
    }

    /**
     * @param array<string> $obrazky
     * @return array<string, string>
     */
    private function serad(
        array   $obrazky,
        ?string $radiciSoubor,
    ) {
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
            fn(
                string $radek,
            ) => trim($radek) !== '' && !str_starts_with(trim($radek), '#'),
        );

        if (count($razeniZeSouboru) === 0) {
            return $klicovaneObrazky; // seřazené abecedně
        }

        $baseRazeniZeSouboru = array_map(fn(
            string $nazev,
        ) => $this->baseNazev($nazev), $razeniZeSouboru);
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

    /**
     * Rozdělí soubory na obrázky a soubor pro řazení.
     *
     * @param array<string> $soubory
     * @return array{obrazky: array<string>, radici_soubor: string|null}
     */
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
            fn(
                string $soubor,
            ) => str_ends_with($soubor, 'RAZENI.csv'),
        );
        $radiciSoubor  = reset($radiciSoubory)
            ?: null;

        return [
            'obrazky'       => $obrazky,
            'radici_soubor' => $radiciSoubor,
        ];
    }

    /**
     * Vyhodí obrázky, které začínají podtržítkem '_'.
     *
     * @param array<string> $obrazky
     * @return array<string>
     */
    private function vyhodVyrazene(array $obrazky): array
    {
        return array_filter(
            $obrazky,
            // vyřazený obrázek, jeho název začíná podtržítkem '_'
            fn(
                string $obrazek,
            ) => substr($obrazek, 0) !== '_',
        );
    }
}
