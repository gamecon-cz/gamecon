<?php

foreach(array(
  'db-form',
  'db-form-field',
  'dbff-pkey',
  'dbff-string',
) as $f) {
  require __DIR__ . '/knihovny/DbForm/'.$f.'.php';
}


class DbFormGc extends DbForm {

  protected function fieldFromDescription($d) {
    if($d['Type'] == 'tinyint(1)' && DbffSelect::commentSplit($d['Comment']))
      return new DbffSelect($d);
    if($d['Type'] == 'int(11)' && $d['Field'] == 'text')
      return new DbffMarkdown($d);
    if($d['Comment'] == 'markdown')
      return new DbffMarkdownDirect($d);
    // fallback na originální pole
    return parent::fieldFromDescription($d);
  }

}
