<?php

require_once __DIR__ . '/nastaveni-jakublounek.php';

define('URL_WEBU', 'https://jakublounek.gamecon.cz'); // absolutní url uživatelského webu
define('URL_ADMIN', 'https://admin.jakublounek.gamecon.cz'); // absolutní url adminu
define('URL_CACHE', 'https://cache.jakublounek.gamecon.cz'); // url sdílených cachí

define('ANALYTICS', false);
define('HTTPS_ONLY', true);

error_reporting(E_ALL);

define('REACT_V_PROHLIZECI', true);
define('AUTOMATICKE_SESTAVENI', true);
define('BABEL_BINARKA', null);

/**
 * Pozor! Při vypnutí to ovlivní i posílání mailů z CRONu, pokud není výslovně nastavená konstanta MAILY_DO_SOUBORU
 * @see admin/cron.php
 */
@define('TESTING', true);
@define('TEST_MAZAT_VSECHNA_RAZITKA_POSLEDNICH_ZMEN', true);

@define('PRODEJ_JIDLA_POZASTAVEN', false);

@define('MAILY_DO_SOUBORU', __DIR__ . '/../cache/private/maily.log');

@define('ADRESAR_WEBU_S_OBRAZKY', __DIR__ . '/../../ostra/web');
