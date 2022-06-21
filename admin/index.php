<?php

require __DIR__ . '/../nastaveni/zavadec.php';

/** získáme @var array|string[] $protipy */
require_once __DIR__ . '/scripts/konstanty.php'; // lokální konstanty pro admin
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

$pageTitle = 'GameCon – Administrace';
// xtemplate inicializace
$xtpl = new XTemplate(__DIR__ . '/templates/main.xtpl');
$xtpl->assign([
    'pageTitle' => $pageTitle,
    'base' => URL_ADMIN . '/',
    'cssVersions' => new \Gamecon\Web\VerzeSouboru(__DIR__ . '/files/design', 'css'),
    'jsVersions' => new \Gamecon\Web\VerzeSouboru(__DIR__ . '/files', 'js'),
]);

// nastavení stránky, prázdná url => přesměrování na úvod
if (!get('req')) {
    back(URL_ADMIN . '/uvod');
}
$req = explode('/', get('req'));
$stranka = $req[0];
$podstranka = isset($req[1]) ? $req[1] : '';

// zobrazení stránky
if (!$u && !in_array($stranka, ['last-minute-tabule', 'program-obecny'])) {
    $chyba = chyba::vyzvedniChybu();
    $xtpl->assign('chyba', $chyba ? '<div class="error">' . $chyba . '</div>' : '');
    $xtpl->parse('all.prihlaseni');
    $xtpl->parse('all');
    $xtpl->out('all');
    profilInfo();
} elseif (is_file(__DIR__ . '/scripts/zvlastni/' . $stranka . '.php')) {
    chdir(__DIR__ . '/scripts/zvlastni/');
    require($stranka . '.php');
} elseif (is_file(__DIR__ . '/scripts/zvlastni/' . $stranka . '/' . $podstranka . '.php')) {
    chdir(__DIR__ . '/scripts/zvlastni/' . $stranka);
    require($podstranka . '.php');
} else {
    // načtení menu
    $menuObject = new AdminMenu('./scripts/modules/');
    $menu = $menuObject->pole();

    // načtení submenu
    $submenu = [];
    $submenuObject = null;
    if (!empty($menu[$stranka]['submenu'])) {
        $submenuObject = new AdminMenu('./scripts/modules/' . $stranka . '/', true);
        $submenu = $submenuObject->pole();
    }

    // zjištění práv na zobrazení stránky
    $strankaExistuje = isset($menu[$stranka]);
    $podstrankaExistuje = isset($submenu[$podstranka]);
    $uzivatelMaPristup = ($strankaExistuje && $podstrankaExistuje && $u->maPravo($submenu[$podstranka]['pravo']))
        || ($strankaExistuje && !$podstrankaExistuje && $u->maPravo($menu[$stranka]['pravo']));

    // konstrukce stránky
    if ($strankaExistuje && $uzivatelMaPristup) {
        $_SESSION['id_admin'] = $u->id(); // součást interface starých modulů
        $_SESSION['id_uzivatele'] = $uPracovni ? $uPracovni->id() : null; // součást interface starých modulů
        $BEZ_DEKORACE = false;
        $cwd = getcwd(); // uložíme si aktuální working directory pro pozdější návrat
        if ($submenu) {
            chdir('./scripts/modules/' . $stranka . '/');
            $soubor = $podstranka && $podstrankaExistuje
                ? $cwd . '/' . $submenu[$podstranka]['soubor']
                : $cwd . '/' . $submenu[$stranka]['soubor'];
        } else {
            chdir('./scripts/modules/');
            $soubor = $cwd . '/' . $menu[$stranka]['soubor'];
            $pageTitle .= ' | ' . $menu[$stranka]['nazev'];
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
    } elseif ($strankaExistuje && !$uzivatelMaPristup) {
        http_response_code(403);
        $xtpl->parse('all.zakazano');
    } else {
        http_response_code(404);
        $xtpl->parse('all.nenalezeno');
    }

    // operátor - info & odhlašování
    $xtpl->assign('a', $u->koncovkaDlePohlavi());
    $xtpl->assign('operator', $u->jmenoNick());
    if ($u->isSuperAdmin()) {
        $xtpl->parse('all.operator.prepnutiUzivatele');
    }
    $xtpl->parse('all.operator');
    // výběr uživatele
    if ($u->maPravo(\Gamecon\Pravo::ADMINISTRACE_UVOD)) // panel úvod
    {
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
        if ($u->maPravo($polozka['pravo'])) {
            $xtpl->assign('url', $url == $stranka ? $url : $stranka . '/' . $url);
            $xtpl->assign('nazev', $polozka['nazev']);
            $addAttributes = [];
            if ($polozka['link_in_blank']) {
                $addAttributes[] = 'target="_blank"';
            }
            if (($podstranka != '' && $podstranka == $url) || ($podstranka == '' && $stranka == $url)) {
                $addAttributes[] = 'class="activeSubmenuLink"';
                $pageTitle = $pageTitle . ' | ' . $polozka['nazev'];
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

    // výstup
    $xtpl->assign('protip', $protipy[array_rand($protipy)]);
    $xtpl->parse('all.paticka');
    $xtpl->assign('chyba', chyba::vyzvedniHtml());
    $xtpl->assign('jsVyjimkovac', \Gamecon\Vyjimkovac\Vyjimkovac::js(URL_WEBU . '/ajax-vyjimkovac'));
    $xtpl->assign('pageTitle', $pageTitle);
    $xtpl->parse('all');
    $xtpl->out('all');
    profilInfo();
}
