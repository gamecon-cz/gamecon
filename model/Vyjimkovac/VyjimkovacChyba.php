<?php

namespace Gamecon\Vyjimkovac;

class VyjimkovacChyba {

  private $r;

  protected function __construct($r) {
    $this->r = $r;
  }

  function __call($metoda, $args) {
    if(isset($this->r[$metoda]) && $args === []) {
      return $this->r[$metoda];
    }
    throw new \BadMethodCallException();
  }

  /**
   * Vrátí pole s řádkem inicializované z glob. proměnných (čas vytvoření, url,
   * ...)
   */
  private static function radekInit() {
    $r = [
      'vznikla' => time(),
      'url'     => 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],
      'zdroj'   => $_SERVER['HTTP_REFERER'] ?? null,
    ];

    try {
      if ($u = \Uzivatel::zSession()) { // selze pokud uz session jeste nebezi a na vystupu uz je nejaky outpout
        $r['uzivatel'] = $u->id();
      }
    } catch (\Throwable $throwable) {
      // nothing to do with that here...
    }

    return $r;
  }

  /** Vrátí čitelný formát pro zadaný číselný typ chyby */
  protected static function typHr($type) {
      $return ="";
      if($type & E_ERROR) // 1 //
          $return.='& E_ERROR ';
      if($type & E_WARNING) // 2 //
          $return.='& E_WARNING ';
      if($type & E_PARSE) // 4 //
          $return.='& E_PARSE ';
      if($type & E_NOTICE) // 8 //
          $return.='& E_NOTICE ';
      if($type & E_CORE_ERROR) // 16 //
          $return.='& E_CORE_ERROR ';
      if($type & E_CORE_WARNING) // 32 //
          $return.='& E_CORE_WARNING ';
      if($type & E_COMPILE_ERROR) // 64 //
          $return.='& E_COMPILE_ERROR ';
      if($type & E_COMPILE_WARNING) // 128 //
          $return.='& E_COMPILE_WARNING ';
      if($type & E_USER_ERROR) // 256 //
          $return.='& E_USER_ERROR ';
      if($type & E_USER_WARNING) // 512 //
          $return.='& E_USER_WARNING ';
      if($type & E_USER_NOTICE) // 1024 //
          $return.='& E_USER_NOTICE ';
      if($type & E_STRICT) // 2048 //
          $return.='& E_STRICT ';
      if($type & E_RECOVERABLE_ERROR) // 4096 //
          $return.='& E_RECOVERABLE_ERROR ';
      if($type & E_DEPRECATED) // 8192 //
          $return.='& E_DEPRECATED ';
      if($type & E_USER_DEPRECATED) // 16384 //
          $return.='& E_USER_DEPRECATED ';
      return substr($return,2);
  }

  function uloz(\EPDO $db) {
    $db->query('CREATE TABLE IF NOT EXISTS chyby(
      jazyk     TEXT,
      typ       TEXT,
      zprava    TEXT,
      soubor    TEXT,
      radek     INTEGER,

      zavaznost INTEGER,
      vznikla   INTEGER,
      url       TEXT,
      zdroj     TEXT,
      uzivatel  INTEGER,
      data      TEXT,
      vyjimka   TEXT
    )');
    $db->insert('chyby', $this->r);
  }

  static function zVyjimky($e) {
    $r = self::radekInit();
    $es = null;
    try {
      $es = serialize($e);
    } catch(\Throwable $serializeError) {
      if ($e instanceof \Throwable) {
        $es = $e->getTraceAsString();
      }
    }
    $r = array_merge($r, [
      'jazyk'     => 'php',
      'radek'     => $e->getLine(),
      'soubor'    => $e->getFile(),
      'typ'       => get_class($e),
      'zavaznost' => 3,
      'zprava'    => $e->getMessage(),
      'vyjimka'   => base64_encode($es),
    ]);
    if($e instanceof \DbException) {
      $r['data'] = trim($e->getTrace()[0]['args'][0] ?? null);
    }
    if($e instanceof JsException) {
      $r['url'] = @$_SERVER['HTTP_REFERER'] ?: null;
      $r['zavaznost'] = 2;
      $r['jazyk'] = 'js';
    }
    if($e instanceof \ErrorException) {
      $s = $e->getSeverity();
      $r['typ'] = self::typHr($s);
      $r['zavaznost'] = 1;
      if($s & E_WARNING)  $r['zavaznost'] = 2;
      if($s & E_ERROR)    $r['zavaznost'] = 4;
    }
    return new self($r);
  }

}
