<?php
ini_set('zend.exception_ignore_args', '0'); // zaloguj argumenty při erroru
ini_set('zend.exception_string_param_max_len', '999'); // loguj argumenty až do délky 999 bajtů před oříznutím

define('DB_NAME', $_COOKIE['gamecon_test_db'] ?? uniqid('gamecon_test_', true));
define('SPEC', sys_get_temp_dir());
define('UNIT_TESTS', true);

// konfigurace
// TODO dokud není konfigurace vyřešena jinak, než přes konstanty, musíme testovat jen jeden vydefinovaný stav, tj. "reg na aktivity i GC běží"
define('REG_GC_OD', '2000-01-01 00:00:00');
define('REG_GC_DO', '2038-01-01 00:00:00');
define('REG_AKTIVIT_OD', '2000-01-01 00:00:00');
define('REG_AKTIVIT_DO', '2038-01-01 00:00:00');

define('BONUS_ZA_1H_AKTIVITU', 1);
define('BONUS_ZA_2H_AKTIVITU', 2);
define('BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU', 3);
define('BONUS_ZA_6H_AZ_7H_AKTIVITU', 6);
define('BONUS_ZA_8H_AZ_9H_AKTIVITU', 8);
define('BONUS_ZA_10H_AZ_11H_AKTIVITU', 10);
define('BONUS_ZA_12H_AZ_13H_AKTIVITU', 12);

define('MAILY_DO_SOUBORU', '/dev/null'); // TODO přidat speciální nastavení pro CI
