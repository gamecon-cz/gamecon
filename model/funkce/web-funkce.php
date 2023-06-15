<?php
/**
 * Vrací hlášku s daným názvem. Libovolný počet argumentů. Pokud je druhým
 * argumentem uživatel, podporuje symbol {a} jako proměnlivou koncovku a. Další
 * argumenty jsou dostupné jako %1, %2 atd... Čísluje se od jedné.
 *
 * Pokud není uživatel uveden, bere se druhý argument jako %1, třetí jako %2
 * atd...
 *
 * @return string hláška s případnými substitucemi
 * @todo fixnout málo zadaných argumentů
 */
function hlaska($nazev, $u = null, ...$parametry)
{
    global $HLASKY, $HLASKY_SUBST;

    if (func_num_args() == 1) {
        return $HLASKY[$nazev];
    } else if ($u instanceof Uzivatel) {
        $koncA = $u->pohlavi() == 'f' ? 'a' : '';
        return strtr($HLASKY_SUBST[$nazev], [
            "\n"  => '<br />',
            '{a}' => $koncA,
            '%1'  => func_num_args() > 2 ? func_get_arg(2) : '',
            '%2'  => func_num_args() > 3 ? func_get_arg(3) : '',
            '%3'  => func_num_args() > 4 ? func_get_arg(4) : '',
            '%4'  => func_num_args() > 5 ? func_get_arg(5) : '',
        ]);
    } else if (func_num_args() > 1) {
        return strtr($HLASKY_SUBST[$nazev], [
            "\n" => '<br />',
            '%1' => func_num_args() > 1 ? func_get_arg(1) : '',
            '%2' => func_num_args() > 2 ? func_get_arg(2) : '',
            '%3' => func_num_args() > 3 ? func_get_arg(3) : '',
            '%4' => func_num_args() > 4 ? func_get_arg(4) : '',
            '%5' => func_num_args() > 4 ? func_get_arg(5) : '',
        ]);
    } else {
        throw new Exception('missing mandatory argument');
    }
}

function hlaskaMail($nazev, $u = null, ...$parametry)
{
    $out = hlaska($nazev,
        func_num_args() > 1 ? func_get_arg(1) : '',
        func_num_args() > 2 ? func_get_arg(2) : '',
        func_num_args() > 3 ? func_get_arg(3) : '',
        func_num_args() > 4 ? func_get_arg(4) : '',
        func_num_args() > 5 ? func_get_arg(5) : '');
    return '<html><body>' . $out . '</body></html>';
}

/**
 * Přesměruje na adresu s https, pokud jde požadavek z adresy s http,
 * a následně ukončí skript.
 */
function httpsOnly()
{
    if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off') {
        $redirect = 'https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
        //header('HTTP/1.1 301 Moved Permanently');
        header('Location: ' . $redirect);
        exit();
    }
}

/**
 * Předá chybu volajícímu skriptu, vyvolá reload
 */
function chyba($zprava, $back = true)
{
    Chyba::nastav((string)$zprava, Chyba::CHYBA);
    if ($back) {
        back();
    }
}

/**
 * Předá chybu volajícímu skriptu, vyvolá reload
 */
function varovani(string $zprava, bool $back = true)
{
    Chyba::nastav($zprava, Chyba::VAROVANI);
    if ($back) {
        back();
    }
}

/**
 * Předá oznámení volajícímu skritpu, vyvolá reload
 * @param string $zprava
 * @param bool $back má se reloadovat?
 */
function oznameni($zprava, $back = true)
{
    Chyba::nastav((string)$zprava, Chyba::OZNAMENI);
    if ($back) {
        back();
    }
}

/**
 * Předá oznámení volajícímu skritpu a přesměruje na $cil
 */
function oznameniPresmeruj($zprava, $cil)
{
    Chyba::nastav((string)$zprava, Chyba::OZNAMENI);
    back($cil);
}

/** Tisk informace profileru. */
function profilInfo()
{
    if (!PROFILOVACI_LISTA) {
        return; // v ostré verzi se neprofiluje
    }
    $schema  = 'data:image/png;base64,';
    $iDb     = $schema . base64_encode(file_get_contents(__DIR__ . '/db.png'));
    $iHodiny = $schema . base64_encode(file_get_contents(__DIR__ . '/hodiny.png'));
    //$iconRoot = URL_ADMIN.'/files/design/';
    $delka = microtime(true) - $GLOBALS['SKRIPT_ZACATEK'];
    // počet sekund, kdy už je skript pomalý (čas zčervená)
    $barva = $delka > 0.2 ? 'color:#f80;' : '';
    // výstup
    echo '
    <div class="profilInfo" style="
      background-color: rgba(0,192,255,0.80);
      color: #fff;
      bottom: 0;
      right: 0;
      position: fixed;
      padding: 2px 7px;
      cursor: default;
      z-index: 9999;
      border-top-left-radius: 4px;
      font: 13px Tahoma, sans-serif;
    ">
    <style>
      .profilInfo img { vertical-align: bottom; }
      @media (max-width: 480px) { .profilInfo { display: none; } }
    </style>
    <img src="' . $iHodiny . '" alt="délka skriptu včetně DB">
    <span style="' . $barva . '">' . round($delka * 1000) . '&thinsp;ms</span>
    &ensp;
    <img src="' . $iDb . '" alt="délka odbavení DB/počet dotazů">
    ' . round(dbExecTime() * 1000) . '&thinsp;ms (' . dbNumQ() . ' dotazů)
    </div>';
}

/** if current call is AJAX */
function is_ajax(): bool
{
    return (!empty($_REQUEST['ajax'])
        || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest')
        || str_contains($_SERVER['REQUEST_URI'] ?? '', '/api/')
    );
}
