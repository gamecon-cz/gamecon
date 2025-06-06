<?php
ini_set('zend.exception_ignore_args', '0');            // zaloguj argumenty při erroru
ini_set('zend.exception_string_param_max_len', '999'); // loguj argumenty až do délky 999 bajtů před oříznutím

define('DB_TEST_PREFIX', 'gamecon_test_');
define('DB_NAME', $_COOKIE['gamecon_test_db'] ?? uniqid(DB_TEST_PREFIX, true));
define('DB_ANONYM_NAME', $_COOKIE['gamecon_test_anonym_db'] ?? uniqid(DB_TEST_PREFIX . 'anonym_', true));
if (!defined('SPEC')) define('SPEC', __DIR__ . '/../cache/private/tests/' . getmypid());
define('TESTS_LOG_DIR', __DIR__ . '/../tests/logs');
define('UNIT_TESTS', true);

// konfigurace
// TODO dokud není konfigurace vyřešena jinak, než přes konstanty, musíme testovat jen jeden vydefinovaný stav, tj. "reg na aktivity běží"
define('PRVNI_VLNA_KDY', '2000-01-01 00:00:00');

define('MAILY_DO_SOUBORU', '/dev/null'); // TODO přidat speciální nastavení pro CI

if (!defined('UNIVERZALNI_HESLO')) define('UNIVERZALNI_HESLO', 'Testuj testuj vykrúcaj!');

