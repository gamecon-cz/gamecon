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
        if (!isset($a['den']) || !isset($a['zacatek'])) {
            return null;
        }

        return $a['zacatek'] >= PROGRAM_ZACATEK
            ? new DateTimeCz($a['den'])
            : (new DateTimeCz($a['den']))->plusDen();
    }

    /**
     * Den ve kterém se odehrává aktivita (např. po půlnoci je stále v předchozím dni) bráno z času ukončení
     * @param array $a
     */
    public static function denAktivityDleKonce($a)
    {
        if (!isset($a['den']) || !isset($a['konec'])) {
            return null;
        }

        return $a['konec'] > PROGRAM_ZACATEK
            ? new DateTimeCz($a['den'])
            : (new DateTimeCz($a['den']))->plusDen();
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
