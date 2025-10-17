<?php
register_shutdown_function(static function () {
    if (defined('SHUTDOWN_FUNCTION_REGISTERED') && SHUTDOWN_FUNCTION_REGISTERED) {
        return; // další funkce na řešení pádů už je registrovaná, tuhle už nepotřebujeme
    }
    if (ini_get('display_errors')) {
        return; // protože error bude zobrazen celý, tak není potřeba řešit náhradní text
    }
    $error = error_get_last();
    if (empty($error['type']) || (!(error_reporting() & $error['type']))) { // tenhle typ errorů nechceme hlásit
        return;
    }
    if (!($error['type'] & E_ERROR
        || $error['type'] & E_USER_ERROR
        || $error['type'] & E_RECOVERABLE_ERROR
        || $error['type'] & E_COMPILE_ERROR
        || $error['type'] & E_PARSE
    )) {
        return; // zřejmě nešlo o fatální error
    }
    if (!headers_sent()) {
        header(($_SERVER['SERVER_PROTOCOL'] ?? '') . ' 500 Internal Server Error', true, 500);
    }
    echo '500 Internal Server Error';
});
