<?php

namespace Gamecon\Aktivita;

use App\Service\AktivitaTymService;
use Gamecon\Cache\ProgramStaticFileGenerator;
use Gamecon\Cas\DateTimeCz;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Stranka;
use Uzivatel;

class Program
{
    /**
     * Aktivity začínají a končí po čtvrthodinách. Tabulka programu má proto na každou hodinu
     * čtyři sloupce (ui/src/pages/program/components/tabulka/seskupování.ts, SLOUPCŮ_ZA_HODINU),
     * při změně je potřeba změnit obojí.
     */
    public const KROK_CASU_AKTIVITY_MINUT = 15;

    public static function vypisPreact($jeAdmin = false, $pageName = "program"): void
    {
        $souborySlozka = ($jeAdmin ? 'files' : 'soubory');
        $stylSoubor    = $souborySlozka . '/ui/style.css';
        $bundleSoubor  = $souborySlozka . '/ui/bundle.js';

        if (self::vypisAutoRefreshPokudChybiUiSoubory([$stylSoubor, $bundleSoubor], $jeAdmin)) {
            return;
        }

        $stylUrl     = self::zabalWebSoubor($stylSoubor, $jeAdmin);
        $bundleUrl   = self::zabalWebSoubor($bundleSoubor, $jeAdmin);
        $konstanty   = json_encode(self::gameconKonstanty($jeAdmin, $pageName));
        $prednacteni = json_encode(
            ['přihlášenýUživatel' => Uzivatel::apiPrihlasenyUzivatel()],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
        );

        echo <<<HTML
        <link rel="stylesheet" href="{$stylUrl}">
        <div id="preact-program">Program se načítá ...</div>
        <script>
            // Konstanty předáváné do Preactu (env.ts)
            window.GAMECON_KONSTANTY = {$konstanty}
            window.gameconPřednačtení = {$prednacteni}
        </script>
        <script type="module" src="{$bundleUrl}"></script>
        HTML;
    }

    public static function gameconKonstanty($jeAdmin = false, $pageName = "program"): array
    {
        $legendaText        = Stranka::zUrl('program-legenda-text')?->html();
        $basePathApi        = ($jeAdmin ? URL_ADMIN : URL_WEBU) . '/api/';
        $basePathPage       = ($jeAdmin ? URL_ADMIN : URL_WEBU) . '/' . $pageName . '/';
        $systemoveNastaveni = SystemoveNastaveni::zGlobals();

        $konstanty = [
            'BASE_PATH_API'                => $basePathApi,
            'BASE_PATH_PAGE'               => $basePathPage,
            'ROCNIK'                       => ROCNIK,
            'LEGENDA'                      => $legendaText,
            'FORCE_REDUX_DEVTOOLS'         => defined('FORCE_REDUX_DEVTOOLS'),
            'PROGRAM_OD'                   => (new DateTimeCz(PROGRAM_OD))->getTimestamp() * 1000,
            'PROGRAM_DO'                   => (new DateTimeCz(PROGRAM_DO))->getTimestamp() * 1000,
            // serverové "teď" – respektuje ročník zobrazený v daném prostředí
            // (beta loňský ročník, produkce letošní, …). Frontend z něj určuje,
            // který den programu právě probíhá.
            'TED'                          => $systemoveNastaveni->ted()->getTimestamp() * 1000,
            'PROGRAM_ZACATEK'              => PROGRAM_ZACATEK,
            'PROGRAM_KONEC'                => PROGRAM_KONEC,
            'CAS_NA_PRIPRAVENI_TYMU_MINUT' => AktivitaTym::CAS_NA_PRIPRAVENI_TYMU_MINUT,
            'URL_PROGRAM_CACHE'            => URL_CACHE . '/program',
            'programManifest'              => (new ProgramStaticFileGenerator($systemoveNastaveni))->readManifest(),
        ];

        if ($jeAdmin) {
            $konstanty['JE_ADMIN'] = $jeAdmin;
            // Úplný seznam místností (seřazený dle pořadí) pro aktuální ročník –
            // frontend jím v zobrazení „po místnostech“ doplní i prázdné místnosti
            // bez aktivit. Místnosti s rok=0 jsou globální (napříč ročníky),
            // proto bereme rok=0 i rok=ROCNIK; cizí ročníky vynecháváme, ať se
            // do programu aktuálního ročníku netahají nerelevantní místnosti.
            $lokaceProRocnik = array_filter(
                Lokace::zVsech(),
                static fn(Lokace $lokace) => $lokace->rok() === 0 || $lokace->rok() === ROCNIK,
            );
            usort(
                $lokaceProRocnik,
                static fn(Lokace $a, Lokace $b) => $a->poradi() <=> $b->poradi(),
            );
            $konstanty['PROGRAM_MISTNOSTI'] = array_map(
                static fn(Lokace $lokace) => $lokace->apiLokace(),
                array_values($lokaceProRocnik),
            );
        }

        // Výchozí nastavení zobrazení programu pro konkrétní stránku. Klíče
        // PROGRAM_VYCHOZI_NASTAVENI odpovídají query parametrům url-stavu Preactu
        // (viz logic/url.ts) a použijí se jen když daný query param v URL chybí;
        // PROGRAM_VYCHOZI_VYBER je výchozí slug výběru dne (např. "vsechny-dny"),
        // použije se když je v URL prázdný slug. Dedikovaná stránka „Program po
        // místnostech“ tak nabíhá rovnou přes všechny dny a seskupená dle
        // místností, jak tomu bývalo před přechodem na Preact.
        $vychoziNastaveniProgramu = [];
        $vychoziVyberProgramu     = null;
        if ($jeAdmin) {
            // Adminům defaultně zapneme filtr „Interní", aby v programu rovnou
            // viděli nepublikované (NOVA) technické/brigádnické aktivity a mohli
            // na ně jednoduše zapisovat (bez tohoto defaultu chybí i celá sekce
            // „Brigádnické (aktivity)", protože se skládá jen z reálně
            // zobrazených aktivit). Přepínač jde vypnout – „interni=false" se pak
            // zapíše do URL a vypnutí přežije reload (viz PROGRAM_VYCHOZI_NASTAVENI
            // v logic/url.ts). Klíč je query klíč „interni" (ZOBRAZ_INTERNÍ_QUERY_KEY),
            // ne stavový „zobrazInterni".
            $vychoziNastaveniProgramu['interni'] = true;
        }
        if ($pageName === 'program-mistnosti') {
            $vychoziNastaveniProgramu['podleMistnosti'] = true;
            $vychoziNastaveniProgramu['zobrazPrazdne']  = true;
            $vychoziVyberProgramu                       = 'vsechny-dny';
        }
        $konstanty['PROGRAM_VYCHOZI_NASTAVENI'] = (object)$vychoziNastaveniProgramu;
        $konstanty['PROGRAM_VYCHOZI_VYBER']     = $vychoziVyberProgramu;

        return $konstanty;
    }

    private static function zabalWebSoubor(string $cestaKSouboru, $admin = false): string
    {
        $baseUrl = $admin ? URL_ADMIN : URL_WEBU;
        return $baseUrl . '/' . $cestaKSouboru . '?version=' . md5_file(($admin ? ADMIN : WWW) . '/' . $cestaKSouboru);
    }

    /**
     * Pokud na lokále některý z UI souborů (style.css / bundle.js) zatím není zkompilovaný,
     * vypíše hlášku s auto-refreshem a vrátí true (volající má skončit).
     * Na betě/produkci nic nedělá.
     */
    private static function vypisAutoRefreshPokudChybiUiSoubory(array $relativniCesty, bool $jeAdmin): bool
    {
        if (!jsmeNaLocale()) {
            return false;
        }
        $zakladniCesta = $jeAdmin ? ADMIN : WWW;
        $chybejici     = [];
        foreach ($relativniCesty as $relativniCesta) {
            if (!file_exists($zakladniCesta . '/' . $relativniCesta)) {
                $chybejici[] = $relativniCesta;
            }
        }
        if (!$chybejici) {
            return false;
        }
        $seznam = htmlspecialchars(implode(', ', $chybejici), ENT_QUOTES, 'UTF-8');
        echo <<<HTML
        <meta http-equiv="refresh" content="1">
        <div id="preact-program">UI se kompiluje (chybí: {$seznam})</div>
        HTML;
        return true;
    }

    /**
     * Den ve kterém se odehrává aktivita (např. po půlnoci je stále v předchozím dni) bráno z času zahájení
     * @param array $a
     */
    public static function denAktivityDleZacatku($a): ?DateTimeCz
    {
        $minuty = self::minutyZCasuFormulare($a['zacatek'] ?? null);
        // den „?“ posílá editor jako 0
        if (empty($a['den']) || $minuty === null) {
            return null;
        }

        return $minuty >= PROGRAM_ZACATEK * 60
            ? new DateTimeCz($a['den'])
            : (new DateTimeCz($a['den']))->plusDen();
    }

    /**
     * Den ve kterém se odehrává aktivita (např. po půlnoci je stále v předchozím dni) bráno z času ukončení
     * @param array $a
     */
    public static function denAktivityDleKonce($a): ?DateTimeCz
    {
        $minuty = self::minutyZCasuFormulare($a['konec'] ?? null);
        // den „?“ posílá editor jako 0
        if (empty($a['den']) || $minuty === null) {
            return null;
        }

        return $minuty > PROGRAM_ZACATEK * 60
            ? new DateTimeCz($a['den'])
            : (new DateTimeCz($a['den']))->plusDen();
    }

    /**
     * Čas z editoru aktivity jako počet minut od půlnoci, null když chybí nebo neleží na čtvrthodině.
     * Editor posílá „14:15“, starší formuláře jen celou hodinu „14“, půlnoc i jako „24“ nebo „24:00“.
     */
    public static function minutyZCasuFormulare(mixed $cas): ?int
    {
        if ((!is_string($cas) && !is_int($cas))
            || !preg_match('~^\s*(?<hodiny>\d{1,2})(?::(?<minuty>\d{2}))?\s*$~', (string)$cas, $casti)
        ) {
            return null;
        }
        $minuty = (int)$casti['hodiny'] * 60 + (int)($casti['minuty'] ?? 0);
        if ((int)($casti['minuty'] ?? 0) > 59 || $minuty > 24 * 60 || $minuty % self::KROK_CASU_AKTIVITY_MINUT !== 0) {
            return null;
        }

        return $minuty;
    }

    /**
     * Minuta dne, kterou editor aktivity u začátku předvybere – čas mimo čtvrthodiny
     * (starší aktivity) zaokrouhlí dolů, stejně jako dřív na celé hodiny.
     */
    public static function minutaZacatkuVEditoru(\DateTimeInterface $zacatek): int
    {
        return self::minutaDneDoluNaKrok($zacatek);
    }

    /**
     * Minuta dne, kterou editor aktivity u konce předvybere. Konec se zaokrouhluje přes poslední
     * úsek aktivity, aby konec o půlnoci vyšel 24:00 (= 0 modulo den) a ne začátek dne.
     */
    public static function minutaKonceVEditoru(\DateTimeInterface $konec): int
    {
        $posledniUsek = \DateTimeImmutable::createFromInterface($konec)
            ->modify('-' . self::KROK_CASU_AKTIVITY_MINUT . ' minutes');

        return (self::minutaDneDoluNaKrok($posledniUsek) + self::KROK_CASU_AKTIVITY_MINUT) % (24 * 60);
    }

    private static function minutaDneDoluNaKrok(\DateTimeInterface $cas): int
    {
        $minuta = (int)$cas->format('G') * 60 + (int)$cas->format('i');

        return intdiv($minuta, self::KROK_CASU_AKTIVITY_MINUT) * self::KROK_CASU_AKTIVITY_MINUT;
    }

    /**
     * Čas pro editor aktivity ve tvaru „14:15“, půlnoc na konci dne jako „24:00“
     */
    public static function formatujCasFormulare(int $minutyOdPulnoci): string
    {
        return sprintf('%d:%02d', intdiv($minutyOdPulnoci, 60), $minutyOdPulnoci % 60);
    }

    /**
     * Vrátí časy (v minutách od půlnoci), kdy můžou začínat aktivity, po krocích KROK_CASU_AKTIVITY_MINUT
     * @return array<int>
     */
    public static function seznamCasuZacatku(): array
    {
        $casyZacatku = [];
        foreach (self::seznamHodinZacatku() as $hodina) {
            foreach (range(0, 60 - self::KROK_CASU_AKTIVITY_MINUT, self::KROK_CASU_AKTIVITY_MINUT) as $minuta) {
                $casyZacatku[] = $hodina * 60 + $minuta;
            }
        }

        return $casyZacatku;
    }

    /**
     * Vrátí range hodin, kdy začínají aktivity
     * @return array<int>
     */
    public static function seznamHodinZacatku(): array
    {
        static $hodinyZacatku = null;
        if ($hodinyZacatku === null) {
            if (PROGRAM_KONEC < PROGRAM_ZACATEK) {
                $hodinyZacatku = [
                    ...range(PROGRAM_ZACATEK, 24 - 1, 1),
                    ...range(0, PROGRAM_KONEC - 1, 1),
                ];
            } else {
                $hodinyZacatku = range(PROGRAM_ZACATEK, PROGRAM_KONEC - 1, 1);
            }
        }

        return $hodinyZacatku;
    }
}
