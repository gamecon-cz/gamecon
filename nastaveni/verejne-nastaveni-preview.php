<?php

use Gamecon\Role\Role;

require_once __DIR__ . '/nastaveni-preview.php';

// Preview environments self-identify from the request host
// (<slug>.preview.gamecon.cz). Unlike beta/produkce which have fixed
// hostnames, every feature branch gets a different slug, so URL_WEBU
// can't be hard-coded.
$_previewHost = $_SERVER['HTTP_HOST'] ?? 'preview.gamecon.cz';
define('URL_WEBU', 'https://' . $_previewHost);
define('URL_ADMIN', 'https://' . $_previewHost . '/admin');
define('URL_CACHE', 'https://' . $_previewHost . '/cache/public');
unset($_previewHost);

define('ANALYTICS', false);
define('HTTPS_ONLY', true);

define('REACT_V_PROHLIZECI', true);
define('AUTOMATICKE_SESTAVENI', true);
define('BABEL_BINARKA', null);

/** aktuální ročník -- při změně roku viz Překlápění ročníku @link PREKLOPENI_ROCNIKU_NAVOD.md */
if (!defined('ROCNIK')) {
    $rocnikOverrideFile = __DIR__ . '/../cache/private/rocnik_override';
    $rocnikOverride     = is_readable($rocnikOverrideFile)
        ? (int)trim((string)file_get_contents($rocnikOverrideFile))
        : null;
    define('ROCNIK', $rocnikOverride ?: 2026);
}

// Same diagnostic/testing facilities as beta — previews are
// developer-facing, never customer-visible.
@define('TESTING', true);
@define('TEST_MAZAT_VSECHNA_RAZITKA_POSLEDNICH_ZMEN', true);
@define('TEST_HROMADNE_AKCE_AKTIVIT_CRONEM_PORAD', true);

@define('PRODEJ_JIDLA_POZASTAVEN', false);

// Never send real mail from a preview. Capture to a log instead.
@define('MAILY_DO_SOUBORU', __DIR__ . '/../cache/private/maily.log');
@define('MAILY_ROLIM', [Role::ORGANIZATOR]);

@define('SUPERADMINI', [
    102 /* Sirien */,
    4032 /* Jaroslav "Kostřivec" Týc */,
    1112 /* Lenka "Cemi" Zavadilová */,
    4275 /* Roman "Sciator" Wehmhoner */,
    5475 /* Michal "Gerete" Bezděk*/
]);
