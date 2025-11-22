<?php

foreach (scandir(__DIR__ . '/knihovny/DbForm') ?: [] as $folder) {
    $folderFullPath = __DIR__ . '/knihovny/DbForm/' . $folder;
    if (is_file($folderFullPath) && str_ends_with($folderFullPath, '.php')) {
        require_once $folderFullPath;
    }
}

class DbFormGc extends DbForm
{

    protected function fieldFromDescription($d): DbFormField
    {
        if ($d['Type'] === 'tinyint(1)' && DbffSelect::commentSplit($d['Comment'])) {
            return new DbffSelect($d);
        }
        if (preg_match('@^markdown@', $d['Comment'])) {
            return new DbffMarkdownDirect($d);
        }
        if (in_array($d['Type'], ['text', 'shorttext', 'mediumtext', 'longtext'])) {
            return new DbffTextAutoresize($d);
        }
        // fallback na originální pole
        return parent::fieldFromDescription($d);
    }

}
