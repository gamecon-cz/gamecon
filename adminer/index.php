<?php
require_once __DIR__ . '/../nastaveni/zavadec-zaklad.php';

if (!defined('ENVIRONMENT') || ENVIRONMENT !== 'local') {
  header('Forbidden', true, 403);
  echo 'Adminer is usable only for local development, sorry.';
  exit;
}

function adminer_object(): Adminer {

  class GameconAdminer extends Adminer
  {

    function name() {
      // custom name in title and heading
      return 'Gamecon';
    }

    function credentials() {
      // server, username and password for connecting to database
      return array(DB_SERV, DB_USER, DB_PASS);
    }

    function database() {
      // database name, will be escaped by Adminer
      return DB_NAME;
    }

    function login($login, $password) {
      // validate user submitted credentials
      return $login === DB_USER && $password === DB_PASS;
    }

    function tableName($tableStatus) {
      // tables without comments would return empty string and will be ignored by Adminer
      return h($tableStatus['Name']);
    }

    function fieldName($field, $order = 0) {
      // only columns with comments will be displayed and only the first five in select
      return $field['field'];
    }

  }

  return new GameconAdminer;
}

if (!file_exists(__DIR__ . '/static')) {
  symlink('./vendor/vrana/adminer/adminer/static', 'static');
}

chdir(__DIR__ . '/vendor/vrana/adminer/editor'); // because adminer counts with that on "includes"
include __DIR__ . '/vendor/vrana/adminer/editor/index.php';
