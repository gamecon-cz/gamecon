<?php

foreach ([
             'db-form',
             'db-form-field',
             'dbff-pkey',
             'dbff-string',
             'dbff-text',
         ] as $f) {
    require __DIR__ . '/knihovny/DbForm/' . $f . '.php';
}

class DbFormGc extends DbForm
{

    protected function fieldFromDescription($d): DbFormField
    {
        if ($d['Type'] === 'tinyint(1)' && DbffSelect::commentSplit($d['Comment'])) {
            return new DbffSelect($d);
        }
        if ($d['Type'] === 'int(11)' && $d['Field'] === 'text') {
            return new DbffMarkdown($d);
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
