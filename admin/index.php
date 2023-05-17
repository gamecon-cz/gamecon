<?php

use Gamecon\XTemplate\XTemplate;

require __DIR__ . '/../nastaveni/zavadec.php';

/** získáme @var array|string[] $protipy */
require_once __DIR__ . '/scripts/admin-menu.php'; // třída administračního menu

if (HTTPS_ONLY) {
    httpsOnly();
}

// nastaví uživatele $u a $uPracovni
require __DIR__ . '/scripts/prihlaseni.php';
/**
 * @var Uzivatel|void|null $u
 * @var Uzivatel|void|null $uPracovni
 */

[$stranka, $podstranka] = parseRoute();

// nastavení stránky, prázdná url => přesměrování na úvod
if (!$stranka) {
    if ($u) {
        if ($u->jeOrganizator()) {
            back(URL_ADMIN . '/' . basename(__DIR__ . '/scripts/modules/uzivatel.php', '.php'));
        }
        if ($u->maPravo(\Gamecon\Pravo::ADMINISTRACE_INFOPULT)) {
            back(URL_ADMIN . '/' . basename(__DIR__ . '/scripts/modules/infopult.php', '.php'));
        }
        back(URL_ADMIN . '/' . basename(__DIR__ . '/scripts/modules/moje-aktivity'));
    }
}

if ($stranka == "api") {
    chdir(__DIR__ . '/scripts/api/');
    require $podstranka . '.php';
    return;
}

global $systemoveNastaveni;
$info = new \Gamecon\Web\Info($systemoveNastaveni);
$info->nazev('Administrace');

// xtemplate inicializace
$xtpl = new XTemplate(__DIR__ . '/templates/main.xtpl');
$xtpl->assign([
    'headerPageInfo' => $info->html(),
    'base'           => URL_ADMIN . '/',
    'cssVersions'    => new \Gamecon\Web\VerzeSouboru(__DIR__ . '/files/design', 'css'),
    'jsVersions'     => new \Gamecon\Web\VerzeSouboru(__DIR__ . '/files', 'js'),
]);
if ($systemoveNastaveni->jeApril()) {
    $xtpl->parse('all.april');
}

// zobrazení stránky
if (!$u && !in_array($stranka, ['last-minute-tabule', 'program-obecny'])) {
    require __DIR__ . '/login.php';
    profilInfo();
    return;
} else if (is_file(__DIR__ . '/scripts/zvlastni/' . $stranka . '.php')) {
    chdir(__DIR__ . '/scripts/zvlastni/');
    require($stranka . '.php');
} else if (is_file(__DIR__ . '/scripts/zvlastni/' . $stranka . '/' . $podstranka . '.php')) {
    chdir(__DIR__ . '/scripts/zvlastni/' . $stranka);
    require($podstranka . '.php');
} else {
    // načtení menu
    $menuObject = new AdminMenu('./scripts/modules/');
    $menu       = $menuObject->pole();

    // načtení submenu
    $submenu       = [];
    $submenuObject = null;
    if (!empty($menu[$stranka]['submenu'])) {
        $submenuObject = new AdminMenu('./scripts/modules/' . $stranka . '/', true);
        $submenu       = $submenuObject->pole();
    }

    // zjištění práv na zobrazení stránky
    $strankaExistuje    = isset($menu[$stranka]);
    $podstrankaExistuje = isset($submenu[$podstranka]);
    $uzivatelMaPristup  = $strankaExistuje && $u->maPravo($menu[$stranka]['pravo'])
        && (!$podstrankaExistuje || $u->maPravo($submenu[$podstranka]['pravo']));

    // konstrukce stránky
    if ($strankaExistuje && $uzivatelMaPristup) {
        $_SESSION['id_admin']     = $u->id(); // součást interface starých modulů
        $_SESSION['id_uzivatele'] = $uPracovni ? $uPracovni->id() : null; // součást interface starých modulů
        $BEZ_DEKORACE             = false;
        $cwd                      = getcwd(); // uložíme si aktuální working directory pro pozdější návrat
        if ($submenu) {
            chdir('./scripts/modules/' . $stranka . '/');
            $soubor = $podstranka && $podstrankaExistuje
                ? $cwd . '/' . $submenu[$podstranka]['soubor']
                : $cwd . '/' . $submenu[$stranka]['soubor'];
        } else {
            chdir('./scripts/modules/');
            $soubor = $cwd . '/' . $menu[$stranka]['soubor'];
            $info->nazev($menu[$stranka]['nazev']);
        }
        ob_start(); // výstup uložíme do bufferu
        require $soubor;

        if ($submenuObject && $submenuObject->getPatickaSoubor()) {
            require $submenuObject->getPatickaSoubor();
        }

        $vystup = ob_get_clean();
        if ($BEZ_DEKORACE) {
            echo $vystup;
        } else {
            $xtpl->assign('obsahRetezec', $vystup);
        }
        chdir($cwd);
        unset($_SESSION['id_uzivatele'], $_SESSION['id_admin']);
        if ($BEZ_DEKORACE) {
            return;
        }
    } else if ($strankaExistuje && !$uzivatelMaPristup) {
        http_response_code(403);
        if ($u) {
            $xtpl->assign('a', $u->koncovkaDlePohlavi());
            $xtpl->assign('login', $u->login());
            $xtpl->parse('all.zakazano.kdoJsi');
        }
        $xtpl->parse('all.zakazano');
    } else {
        $stareRouty = include __DIR__ . '/stare-routy.php';
        if ($novaRouta = $stareRouty[$stranka] ?? false) {
            back(URL_ADMIN . '/' . $novaRouta);
        }
        http_response_code(404);
        $xtpl->parse('all.nenalezeno');
    }

    // operátor - info & odhlašování
    $xtpl->assign('a', $u->koncovkaDlePohlavi());
    $xtpl->assign('operator', $u->jmenoNick());
    if ($u && ($u->jeSuperAdmin() || $u->jeInfopultak())) {
        $dataOmnibox = [];
        if ($u->jeInfopultak()) {
            $dataOmnibox['jenSRolemi'] = [\Gamecon\Role\Role::LETOSNI_VYPRAVEC, \Gamecon\Role\Role::LETOSNI_PARTNER];
        }
        $xtpl->assign('dataOmniboxJson', htmlspecialchars(json_encode($dataOmnibox, JSON_FORCE_OBJECT)));
        $xtpl->parse('all.operator.prepnutiUzivatele');
    }
    $xtpl->parse('all.operator');
    // výběr uživatele
    if ($u && $u->maPravo(\Gamecon\Pravo::ADMINISTRACE_INFOPULT)) {
        if ($uPracovni) {
            $xtpl->assign('uPracovni', $uPracovni);
            $xtpl->parse('all.uzivatel.vybrany');
        } else {
            $xtpl->parse('all.uzivatel.omnibox');
        }
        $xtpl->parse('all.uzivatel');
    }

    // výstup menu
    foreach ($menu as $url => $polozka) {
        if ($u->maPravo($polozka['pravo'])) {
            $xtpl->assign('url', $url);
            $xtpl->assign('nazev', $polozka['nazev']);
            $xtpl->assign('aktivni', $stranka == $url ? 'class="active"' : '');
            $xtpl->parse('all.menuPolozka');
        }
    }

    // submenu setřídění dle group, pak order, pak nazev
    uasort($submenu, function ($a, $b) {
        $diff = $a['group'] - $b['group'];
        if ($diff == 0) {
            $diff = $a['order'] - $b['order'];
            if ($diff == 0) {
                return 0;
            }
        }
        return $diff;
    });

    // výstup submenu
    foreach ($submenu as $url => $polozka) {
        if ($u && $u->maPravo($polozka['pravo'])) {
            $xtpl->assign('url', $url == $stranka ? $url : $stranka . '/' . $url);
            $xtpl->assign('nazev', $polozka['nazev']);
            $addAttributes = [];
            if ($polozka['link_in_blank']) {
                $addAttributes[] = 'target="_blank"';
            }
            if (($podstranka != '' && $podstranka == $url) || ($podstranka == '' && $stranka == $url)) {
                $addAttributes[] = 'class="activeSubmenuLink"';
                $info->nazev($polozka['nazev']);
            }
            $xtpl->assign('add_attributes', implode(' ', $addAttributes));

            $display = '';
            if (!empty($polozka['hidden'])) {
                $display = 'none';
            }
            $xtpl->assign('display', $display);

            $itemBreak = '';
            if ($polozka['order'] == 1 && $polozka['group'] > 1) {
                $itemBreak = '</ul></li><li><ul class="adm_submenu_group">';
            }
            $xtpl->assign('break', $itemBreak);

            $xtpl->assign('group', $polozka['group']);
            $xtpl->assign('order', $polozka['order']);
            $xtpl->parse('all.submenu.polozka');
        }
    }
    $xtpl->assign('stranka', $stranka);
    $xtpl->parse('all.submenu');

    $protipy = [
        'tlačítka, které mají podtržené písmeno, je možné zrychleně použít pomocí alt+písmeno',
        'užij si GameCon',
        'alt+u vybírá uživatele, alt+z ruší',
        'odhlášením uživatele z GC se nenávratně zruší všechny jeho aktivity a nákupy',
        'osobní údaje lze upravit kliknutím a přepsáním na úvodní straně',
        'používání klávesových zkratek urychlí práci',
        '<q>Bacha, tady můžeš něco posrat, ses si jistej, že víš co děláš?"</q> -Cemi, 2022',
    ];

    $info->nazev($info->nazev() ?? '', 'Administrace');
    // výstup
    $xtpl->assign('protip', $protipy[array_rand($protipy)]);
    $xtpl->parse('all.paticka');
    $xtpl->assign('chyba', chyba::vyzvedniHtml());
    $xtpl->assign('jsVyjimkovac', \Gamecon\Vyjimkovac\Vyjimkovac::js(URL_WEBU));
    $xtpl->assign('headerPageInfo', $info->html());
    $xtpl->parse('all');
    $xtpl->out('all');
    profilInfo();
}

if ($systemoveNastaveni->jsmeNaBete()) {
    $xtpl->parse('all.jsmeNaBete');
}
