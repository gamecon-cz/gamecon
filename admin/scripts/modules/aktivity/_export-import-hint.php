<?php

use Gamecon\XTemplate\XTemplate;

return (static function (): string {
    $template = new XTemplate(__DIR__ . '/_export-import-hint.xtpl');
    $template->parse('exportImportHint');

    return $template->text('exportImportHint');
})();
