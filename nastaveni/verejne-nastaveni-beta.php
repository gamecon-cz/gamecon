<?php

require_once __DIR__ . '/nastaveni-beta.php';

define('URL_WEBU', 'https://beta.gamecon.cz'); // absolutní url uživatelského webu
define('URL_ADMIN', 'https://admin.beta.gamecon.cz'); // absolutní url adminu
define('URL_CACHE', 'https://cache.beta.gamecon.cz'); // url sdílených cachí

define('ANALYTICS', false);
define('HTTPS_ONLY', true);

define('REACT_V_PROHLIZECI', true);
define('AUTOMATICKE_SESTAVENI', true);
define('BABEL_BINARKA', null);

define('FORCE_REDUX_DEVTOOLS', true);

/**
 * Pozor! Při vypnutí na betě to ovlivní i posílání mailů z CRONu, pokud není výslovně nastavená konstanta MAILY_DO_SOUBORU
 * @see admin/cron.php
 */
@define('TESTING', true);
@define('TEST_MAZAT_VSECHNA_RAZITKA_POSLEDNICH_ZMEN', true);
@define('TEST_HROMADNE_AKCE_AKTIVIT_CRONEM_PORAD', true);

@define('PRODEJ_JIDLA_POZASTAVEN', false);

@define('MAILY_DO_SOUBORU', __DIR__ . '/../cache/private/maily.log');
@define('MAILY_ROLIM', [\Gamecon\Role\Role::ORGANIZATOR]);

@define('SUPERADMINI', [
    4032 /* Jaroslav "Kostřivec" Týc */,
    1112 /* Lenka "Cemi" Zavadilová */,
    4275 /* Roman "Sciator" Wehmhoner */,
    5475 /* Michal "Gerete" Bezděk */
]);
