<?php

use Gamecon\Aktivita\Program;

/** @var Uzivatel $u */
/** @var Uzivatel|null $uPracovni */
/** @var \Gamecon\SystemoveNastaveni\SystemoveNastaveni $systemoveNastaveni */

require __DIR__ . '/program-test-mock.php';

// URL mock API: BASE_PATH_API → admin/api/mock/ → dispatcher → admin/scripts/api/mock/
$mockBasePathApi  = URL_ADMIN . '/api/mock/';

// Statické soubory musí být skutečné soubory na disku — web server by .json přes PHP router nepropustil.
// Zapíšeme je do admin/files/program-mock/ (statická složka servovaná přímo).
$mockStaticDir = ADMIN . '/files/program-mock';
(new \Symfony\Component\Filesystem\Filesystem())->mkdir($mockStaticDir, 0775);
foreach ([
    'aktivity-mock.json'    => $programTestMockAktivity,
    'popisy-mock.json'      => $programTestMockPopisy,
    'obsazenosti-mock.json' => $programTestMockObsazenosti,
    'tagy-mock.json'        => $programTestMockTagy,
] as $filename => $data) {
    file_put_contents(
        $mockStaticDir . '/' . $filename,
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    );
}

// URL mock statických souborů odpovídá zapisované složce
$mockProgramCache = URL_ADMIN . '/files/program-mock';

// GAMECON_KONSTANTY s přesměrovaným API
$konstanty                      = Program::gameconKonstanty(true, 'program-test');
$konstanty['BASE_PATH_API']     = $mockBasePathApi;
$konstanty['URL_PROGRAM_CACHE'] = $mockProgramCache;
$konstanty['programManifest']   = $programTestMockManifest;

$konstantyJson   = json_encode($konstanty, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$prednacteniJson = json_encode(
    ['přihlášenýUživatel' => $programTestMockPrihlasenyUzivatel],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
);

// URL bundle/style — replikuje Program::zabalWebSoubor (privátní metoda)
$stylUrl   = URL_ADMIN . '/files/ui/style.css?version=' . md5_file(ADMIN . '/files/ui/style.css');
$bundleUrl = URL_ADMIN . '/files/ui/bundle.js?version=' . md5_file(ADMIN . '/files/ui/bundle.js');

// -----------------------------------------------------------------------
// Pomocné funkce pro tabulku
// -----------------------------------------------------------------------

$jsonBlock = static function (mixed $data, string $id): string {
    $pretty = htmlspecialchars(
        json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT),
        ENT_QUOTES | ENT_HTML5,
    );
    return <<<HTML
        <details>
            <summary style="cursor:pointer;color:#4a9eff;font-weight:bold">Zobrazit JSON</summary>
            <pre id="{$id}" style="
                background:#1e1e2e;color:#cdd6f4;
                padding:12px;border-radius:6px;
                font-size:11px;line-height:1.4;
                max-height:400px;overflow:auto;
                margin-top:6px;white-space:pre-wrap;word-break:break-all;
            ">{$pretty}</pre>
        </details>
        HTML;
};

$stavBadge = static function (?string $stav): string {
    $stav ??= 'null';
    $colors = [
        'null'                  => '#555',
        'prihlasen'             => '#4caf50',
        'prihlasenADorazil'     => '#2196f3',
        'dorazilJakoNahradnik'  => '#00bcd4',
        'prihlasenAleNedorazil' => '#f44336',
        'pozdeZrusil'           => '#ff9800',
        'sledujici'             => '#9c27b0',
    ];
    $color = $colors[$stav] ?? '#555';
    return "<span style=\"display:inline-block;padding:2px 8px;border-radius:4px;background:{$color};color:#fff;font-size:10px;font-family:monospace\">{$stav}</span>";
};

$check = static fn(bool $v): string => $v ? '<span class="flag-true">✓</span>' : '';

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <title>Program – mock test<?= $uPracovni ? ' (' . htmlspecialchars($uPracovni->jmenoNick(), ENT_QUOTES | ENT_HTML5) . ')' : '' ?></title>
    <script src="files/jquery-3.4.1.min.js"></script>
    <script src="files/jquery-ui-v1.12.1.min.js"></script>
    <base href="<?= URL_ADMIN ?>/">
    <link rel="stylesheet" href="files/design/hint.css">
    <link rel="stylesheet" href="files/design/ui-lightness/jquery-ui-v1.12.1.min.css">
    <style>
        body {
            font-family: tahoma, sans, sans-serif;
            font-size: 11px;
            line-height: 1.2;
            background-color: #fff;
            overflow-y: scroll;
        }
        .program-odkaz { color: #fff; }

        /* Mock panel */
        .mock-panel {
            background: #13131f;
            color: #cdd6f4;
            padding: 20px 24px;
            border-bottom: 3px solid #4a9eff;
        }
        .mock-panel h2 {
            margin: 0 0 4px;
            font-size: 15px;
            color: #89b4fa;
            letter-spacing: .03em;
        }
        .mock-panel .subtitle {
            color: #6c7086;
            font-size: 11px;
            margin-bottom: 18px;
        }

        /* Vnější tabulka endpointů */
        .mock-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
        }
        .mock-table th {
            background: #1e1e2e;
            color: #89b4fa;
            text-align: left;
            padding: 7px 10px;
            border-bottom: 1px solid #313244;
            white-space: nowrap;
        }
        .mock-table > tbody > tr:nth-child(even) > td { background: #191926; }
        .mock-table > tbody > tr:hover > td { background: #1e1e2e; }
        .mock-table td {
            padding: 8px 10px;
            border-bottom: 1px solid #1e1e2e;
            vertical-align: top;
        }
        .mock-table .url {
            font-family: monospace;
            font-size: 10px;
            color: #a6e3a1;
            word-break: break-all;
        }
        .mock-table .popis { color: #bac2de; }

        /* Vnitřní mini-tabulky dat */
        .act-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
            margin-top: 6px;
        }
        .act-table th {
            background: #2a2a3e;
            color: #89b4fa;
            padding: 3px 6px;
            text-align: left;
            white-space: nowrap;
        }
        .act-table td {
            padding: 3px 6px;
            border-bottom: 1px solid #1e1e2e;
            font-family: monospace;
            color: #cdd6f4;
        }
        .act-table tbody tr:nth-child(even) td { background: #1e1e2e; }
        .act-table tbody tr:hover td { background: #2a2a3e; }

        .flag-true  { color: #a6e3a1; font-weight: bold; }
        .flag-false { color: transparent; }
    </style>
</head>
<body>

<!-- Sticky záhlaví -->
<div style="
    text-align: left;
    font-size: 16px;
    position: -webkit-sticky;
    position: sticky;
    top: 0; left: 0;
    width: 350px;
    padding: 10px;
    color: #fff;
    background-color: rgba(0,0,0,0.8);
    border-bottom-right-radius: 12px;
    z-index: 20;
  ">
    <input type="button" value="Zavřít"
           onclick="window.location = '<?= $u ? $u->mimoMojeAktivityUvodniAdminLink()['url'] : URL_ADMIN . '/uzivatel' ?>'"
           style="float:right;width:100px;height:35px">
    <?= $uPracovni ? $uPracovni->jmenoNick() : '(mock uživatel)' ?><br>
    <?= $uPracovni ? '<span id="stavUctu">' . $uPracovni->finance()->formatovanyStav() . '</span><br>' : '' ?>
</div>

<!-- ================================================================ -->
<!-- MOCK PANEL – přehled endpointů                                    -->
<!-- ================================================================ -->
<div class="mock-panel">
    <h2>🧪 Program mock – přehled API endpointů</h2>
    <div class="subtitle">
        BASE_PATH_API = <code style="color:#a6e3a1"><?= htmlspecialchars($mockBasePathApi, ENT_QUOTES | ENT_HTML5) ?></code> &nbsp;|&nbsp;
        URL_PROGRAM_CACHE = <code style="color:#a6e3a1"><?= htmlspecialchars($mockProgramCache, ENT_QUOTES | ENT_HTML5) ?></code>
    </div>

    <table class="mock-table">
        <thead>
            <tr>
                <th style="width:220px">Endpoint</th>
                <th style="width:280px">Popis</th>
                <th>Odpověď / Obsah</th>
            </tr>
        </thead>
        <tbody>

            <!-- prihlasenyUzivatel -->
            <tr>
                <td>
                    <div class="url"><?= htmlspecialchars($mockBasePathApi . 'prihlasenyUzivatel', ENT_QUOTES | ENT_HTML5) ?></div>
                </td>
                <td class="popis">Mock přihlášený uživatel.<br>Organiz., gcStav = přítomen, id = 9999.</td>
                <td><?= $jsonBlock($programTestMockPrihlasenyUzivatel, 'json-user') ?></td>
            </tr>

            <!-- programManifest (pre-loaded) -->
            <tr>
                <td>
                    <div class="url">programManifest</div>
                    <div style="color:#6c7086;font-size:10px;margin-top:3px">(pre-loaded v GAMECON_KONSTANTY)</div>
                </td>
                <td class="popis">Manifest ukazuje na soubory v <code>files/program-mock/</code>. Frontend ho dostane přímo, API nevolá.</td>
                <td><?= $jsonBlock($programTestMockManifest, 'json-manifest') ?></td>
            </tr>

            <!-- aktivity -->
            <tr>
                <td>
                    <div class="url"><?= htmlspecialchars($mockProgramCache . '/aktivity-mock.json', ENT_QUOTES | ENT_HTML5) ?></div>
                </td>
                <td class="popis">
                    Statický soubor aktivit — <?= count($programTestMockAktivity) ?> aktivit pokrývajících různé kombinace stavů.
                </td>
                <td>
                    <table class="act-table">
                        <thead>
                            <tr>
                                <th>id</th>
                                <th>Název</th>
                                <th>Linie</th>
                                <th>Čas</th>
                                <th>Cena</th>
                                <th>prihl.</th>
                                <th>tým.</th>
                                <th>brig.</th>
                                <th>proběhl.</th>
                                <th>vBudouc.</th>
                                <th>vDalšíVlně</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($programTestMockAktivity as $a): ?>
                            <tr>
                                <td><?= $a['id'] ?></td>
                                <td style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($a['nazev'], ENT_QUOTES | ENT_HTML5) ?>"><?= htmlspecialchars($a['nazev'], ENT_QUOTES | ENT_HTML5) ?></td>
                                <td><?= htmlspecialchars($a['linie'], ENT_QUOTES | ENT_HTML5) ?></td>
                                <td style="white-space:nowrap"><?= htmlspecialchars(html_entity_decode($a['casText']), ENT_QUOTES | ENT_HTML5) ?></td>
                                <td><?= $a['cenaZaklad'] ?> Kč</td>
                                <td><?= $check($a['prihlasovatelna']) ?></td>
                                <td><?= $check($a['tymova']) ?></td>
                                <td><?= $check($a['jeBrigadnicka']) ?></td>
                                <td><?= $check($a['probehnuta']) ?></td>
                                <td><?= $check($a['vBudoucnu']) ?></td>
                                <td><?= $check($a['vdalsiVlne']) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                    <?= $jsonBlock($programTestMockAktivity, 'json-aktivity') ?>
                </td>
            </tr>

            <!-- obsazenosti -->
            <tr>
                <td>
                    <div class="url"><?= htmlspecialchars($mockProgramCache . '/obsazenosti-mock.json', ENT_QUOTES | ENT_HTML5) ?></div>
                </td>
                <td class="popis">Obsazenosti aktivit (m/f/km/kf/ku). ID 1002 je plno (4+4 / 4+4).</td>
                <td>
                    <table class="act-table">
                        <thead>
                            <tr><th>id</th><th>m</th><th>f</th><th>km</th><th>kf</th><th>ku</th><th>stav</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($programTestMockObsazenosti as $o):
                            $obs    = $o['obsazenost'];
                            $celkem = $obs['m'] + $obs['f'];
                            $kapac  = $obs['km'] + $obs['kf'] + $obs['ku'];
                            $plno   = $kapac > 0 && $celkem >= $kapac;
                        ?>
                            <tr>
                                <td><?= $o['idAktivity'] ?></td>
                                <td><?= $obs['m'] ?></td>
                                <td><?= $obs['f'] ?></td>
                                <td><?= $obs['km'] ?></td>
                                <td><?= $obs['kf'] ?></td>
                                <td><?= $obs['ku'] ?></td>
                                <td><?= $plno ? '<span style="color:#f38ba8">PLNO</span>' : '<span style="color:#a6e3a1">volno</span>' ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                    <?= $jsonBlock($programTestMockObsazenosti, 'json-obsazenosti') ?>
                </td>
            </tr>

            <!-- tagy -->
            <tr>
                <td>
                    <div class="url"><?= htmlspecialchars($mockProgramCache . '/tagy-mock.json', ENT_QUOTES | ENT_HTML5) ?></div>
                </td>
                <td class="popis">Štítky / tagy (<?= count($programTestMockTagy) ?> kusů, 2 kategorie).</td>
                <td>
                    <table class="act-table">
                        <thead>
                            <tr><th>id</th><th>Název</th><th>Kategorie</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($programTestMockTagy as $t): ?>
                            <tr>
                                <td><?= $t['id'] ?></td>
                                <td><?= htmlspecialchars($t['nazev'],          ENT_QUOTES | ENT_HTML5) ?></td>
                                <td><?= htmlspecialchars($t['nazevKategorie'], ENT_QUOTES | ENT_HTML5) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                </td>
            </tr>

            <!-- popisy -->
            <tr>
                <td>
                    <div class="url"><?= htmlspecialchars($mockProgramCache . '/popisy-mock.json', ENT_QUOTES | ENT_HTML5) ?></div>
                </td>
                <td class="popis">Popisy aktivit (<?= count($programTestMockPopisy) ?> kusů).</td>
                <td><?= $jsonBlock($programTestMockPopisy, 'json-popisy') ?></td>
            </tr>

            <!-- aktivityUzivatel -->
            <tr>
                <td>
                    <div class="url"><?= htmlspecialchars($mockBasePathApi . 'aktivityUzivatel', ENT_QUOTES | ENT_HTML5) ?></div>
                </td>
                <td class="popis">
                    Stavy přihlášení uživatele na aktivity.<br>
                    Pokrývá všechny hodnoty <code>stavPrihlaseni</code> + sleva + vedoucí + interní.
                </td>
                <td>
                    <table class="act-table">
                        <thead>
                            <tr><th>id</th><th>stavPrihlaseni</th><th>slevaNasobic</th><th>vedu</th><th>interni</th><th>mistnosti</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($programTestMockAktivityUzivatel['data']['aktivityUzivatel'] as $au): ?>
                            <tr>
                                <td><?= $au['id'] ?></td>
                                <td><?= $stavBadge($au['stavPrihlaseni']) ?></td>
                                <td style="color:<?= $au['slevaNasobic'] < 1.0 ? '#f9e2af' : '#6c7086' ?>"><?= $au['slevaNasobic'] ?></td>
                                <td><?= $check($au['vedu']) ?></td>
                                <td><?= $check($au['interni']) ?></td>
                                <td style="color:#89b4fa"><?= $au['mistnosti'] ? count($au['mistnosti']) . ' mís.' : '' ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                    <div style="margin-top:10px;color:#89b4fa;font-size:10px;font-weight:bold">aktivitySkryte</div>
                    <table class="act-table">
                        <thead>
                            <tr>
                                <th>id</th>
                                <th>Název</th>
                                <th>Linie</th>
                                <th>Čas</th>
                                <th>Cena</th>
                                <th>prihl.</th>
                                <th>tým.</th>
                                <th>brig.</th>
                                <th>proběhl.</th>
                                <th>vBudouc.</th>
                                <th>vDalšíVlně</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($programTestMockAktivityUzivatel['data']['aktivitySkryte'] as $a): ?>
                            <tr>
                                <td><?= $a['id'] ?></td>
                                <td style="max-width:160px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($a['nazev'], ENT_QUOTES | ENT_HTML5) ?>"><?= htmlspecialchars($a['nazev'], ENT_QUOTES | ENT_HTML5) ?></td>
                                <td><?= htmlspecialchars($a['linie'], ENT_QUOTES | ENT_HTML5) ?></td>
                                <td style="white-space:nowrap"><?= htmlspecialchars(html_entity_decode($a['casText']), ENT_QUOTES | ENT_HTML5) ?></td>
                                <td><?= $a['cenaZaklad'] ?> Kč</td>
                                <td><?= $check($a['prihlasovatelna']) ?></td>
                                <td><?= $check($a['tymova']) ?></td>
                                <td><?= $check($a['jeBrigadnicka']) ?></td>
                                <td><?= $check($a['probehnuta']) ?></td>
                                <td><?= $check($a['vBudoucnu']) ?></td>
                                <td><?= $check($a['vdalsiVlne']) ?></td>
                            </tr>
                        <?php endforeach ?>
                        </tbody>
                    </table>
                    <?= $jsonBlock($programTestMockAktivityUzivatel, 'json-au') ?>
                </td>
            </tr>

            <!-- aktivitaAkce (no-op) -->
            <tr>
                <td>
                    <div class="url"><?= htmlspecialchars($mockBasePathApi . 'aktivitaAkce', ENT_QUOTES | ENT_HTML5) ?></div>
                </td>
                <td class="popis">Přihlašovací akce (prihlasit, odhlasit, …). Mock vrátí ok=true, nic v DB nezmění.</td>
                <td><?= $jsonBlock(['ok' => true, 'message' => 'Mock: akce zaznamenána, žádná skutečná změna v DB'], 'json-akce') ?></td>
            </tr>

            <!-- aktivitaTym (no-op) -->
            <tr>
                <td>
                    <div class="url"><?= htmlspecialchars($mockBasePathApi . 'aktivitaTym', ENT_QUOTES | ENT_HTML5) ?></div>
                </td>
                <td class="popis">Týmové operace (vytvoř tým, připoj se, …). Mock vrátí ok=true, nic nezmění.</td>
                <td><?= $jsonBlock(['ok' => true, 'message' => 'Mock: týmová akce zaznamenána, žádná skutečná změna v DB'], 'json-tym') ?></td>
            </tr>

        </tbody>
    </table>
</div>
<!-- ================================================================ -->

<!-- Program vykreslený přes mock GAMECON_KONSTANTY -->
<link rel="stylesheet" href="<?= htmlspecialchars($stylUrl, ENT_QUOTES | ENT_HTML5) ?>">
<div id="preact-program">Program se načítá ...</div>
<script>
    window.GAMECON_KONSTANTY   = <?= $konstantyJson ?>;
    window.gameconPřednačtení  = <?= $prednacteniJson ?>;
</script>
<script type="module" src="<?= htmlspecialchars($bundleUrl, ENT_QUOTES | ENT_HTML5) ?>"></script>

<?php profilInfo(); ?>

</body>
</html>
