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
        $stylUrl     = self::zabalWebSoubor($souborySlozka . '/ui/style.css', $jeAdmin);
        $bundleUrl   = self::zabalWebSoubor($souborySlozka . '/ui/bundle.js', $jeAdmin);
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
        $legendaText = Stranka::zUrl('program-legenda-text')?->html();
        $basePathApi = ($jeAdmin ? URL_ADMIN : URL_WEBU) . '/api/';
        $basePathPage = ($jeAdmin ? URL_ADMIN : URL_WEBU) . '/' . $pageName . '/';

        $konstanty = [
            'BASE_PATH_API'                => $basePathApi,
            'BASE_PATH_PAGE'               => $basePathPage,
            'ROCNIK'                       => ROCNIK,
            'LEGENDA'                      => $legendaText,
            'FORCE_REDUX_DEVTOOLS'         => defined('FORCE_REDUX_DEVTOOLS'),
            'PROGRAM_OD'                   => (new DateTimeCz(PROGRAM_OD))->getTimestamp() * 1000,
            'PROGRAM_DO'                   => (new DateTimeCz(PROGRAM_DO))->getTimestamp() * 1000,
            'PROGRAM_ZACATEK'              => PROGRAM_ZACATEK,
            'PROGRAM_KONEC'                => PROGRAM_KONEC,
            'CAS_NA_PRIPRAVENI_TYMU_MINUT' => AktivitaTym::CAS_NA_PRIPRAVENI_TYMU_MINUT,
            'URL_PROGRAM_CACHE'            => URL_CACHE . '/program',
            'programManifest'              => (new ProgramStaticFileGenerator(SystemoveNastaveni::zGlobals()))->readManifest(),
        ];

        if ($jeAdmin) {
            $konstanty['JE_ADMIN'] = $jeAdmin;
        }

        return $konstanty;
    }

    private static function zabalWebSoubor(string $cestaKSouboru, $admin = false): string
    {
        $baseUrl = $admin ? URL_ADMIN : URL_WEBU;
        return $baseUrl . '/' . $cestaKSouboru . '?version=' . md5_file(($admin ? ADMIN : WWW) . '/' . $cestaKSouboru);
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
