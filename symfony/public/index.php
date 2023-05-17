<?php

use Gamecon\Symfony\Kernel;

// just a little hack to workaround unusual Symfony run
$originalScriptFilename = null;
if (!empty($_SERVER['SCRIPT_FILENAME'])) {
    $originalScriptFilename     = $_SERVER['SCRIPT_FILENAME'];
    $_SERVER['SCRIPT_FILENAME'] = __FILE__;
}

require_once dirname(__DIR__) . '/vendor/autoload_runtime.php';

if ($originalScriptFilename !== null) {
    $_SERVER['SCRIPT_FILENAME'] = $originalScriptFilename;
}

return function (array $context) {
    return new Kernel($context['APP_ENV'], (bool)$context['APP_DEBUG']);
};
