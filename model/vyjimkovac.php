<?php

/**
 * Třída starající se o zpracování, zobrazení a zaznamenávání výjimek a chyb
 */
class Vyjimkovac {

  private
    $dbFile,
    $db,
    $ukoncitPriNotice = true, // TODO nastavení zvenčí
    $zobrazeni = self::PLAIN;

  const
    NIC     = 1,
    PLAIN   = 2,
    TRACY   = 3,
    PICARD  = 4;

  function __construct($dbFile) {
    $this->dbFile = $dbFile;
  }

  /**
   * Zapne zpracování výjimek
   */
  function aktivuj() {

    // fatal errory
    register_shutdown_function(function() {
      $error = error_get_last();
      if($error["type"] != E_ERROR) return;

      $eException = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
      $eFixed = Tracy\Helpers::fixStack($eException);
      $this->zpracuj($eFixed);
    });

    // typicky notice, warningy a stricty
    set_error_handler(function($typ, $msg, $file, $line, $context) {
      // omezení typu na pouze aktuálně reportované
      // (nutné kvůli operátoru @ použitého typicky v parse_ metodách šablon,
      // který by jinak tento handler odchytával)
      if(!(error_reporting() & $typ)) return;

      $eException = new ErrorException($msg, 0, $typ, $file, $line);
      $eFixed = Tracy\Helpers::fixStack($eException);
      $this->zpracuj($eFixed);
    });

    // standardní výjimky
    set_exception_handler(function($e) {
      if($e instanceof Chyba)
        $e->zpet(); // u zobrazitelných chyb ignorovat a jen zobrazit upo
      elseif($e instanceof XTemplateRecompilationException)
        back($_SERVER['REQUEST_URI']);
      else
        $this->zpracuj($e);
    });

  }

  /**
   * Vrátí PDO instanci s připravenou databází pro uložení / čtení chyb
   */
  protected function db() {
    if(!$this->db) {
      $this->db = new EPDO('sqlite:'.$this->dbFile);
    }
    return $this->db;
  }

  /**
   * Vrátí HTML skript element s kódem aktivujícím js výjimkovač
   * @todo předělat z jQuery volání na vanilla JS
   */
  static function js($url) {
    ob_start();
    ?><script>
      window.onerror = function(msg, url, line) {
        $.post('<?=$url?>', {
          msg: msg,
          url: url,
          line: line
        });
      };
    </script><?php
    return ob_get_clean();
  }

  /**
   * Zavoláno ze stránky zpracovávající ajaxové info z výjimkovače
   */
  function jsZpracuj() {
    $e = new JsException(post('msg'), post('url'), post('line'));
    $this->zpracuj($e);
  }

  function zobrazeni(...$args) {
    if(!$args) {
      return $this->zobrazeni;
    } else {
      $this->zobrazeni = $args[0];
    }
  }

  /**
   * Zobrazí public omluvnou stránku uživateli
   */
  function zobrazOmluvu() {
    $out = file_get_contents(__DIR__ . '/vyjimkovac-omluva.xtpl');
    $out = strtr($out, [
      '{picard}'  => URL_WEBU . '/soubory/styl/exception.jpg',
    ]);
    echo $out;
  }

  /**
   * Uloží výjimku a zobrazí info podle nastaveného stylu zobrazování chyb
   * a případně ukončí skript.
   */
  protected function zpracuj($e) {
    // uložení
    VyjimkovacChyba::zVyjimky($e)->uloz($this->db());

    // hlavičky
    if($e instanceof JsException)       return; // js výjimky nezobrazovat
    if (!headers_sent()) {
      if ($e instanceof UrlException)     header('HTTP/1.1 400 Bad Request'); // nastavení chybových hlaviček
      else                                header('HTTP/1.1 500 Internal Server Error');
    }

    // zobrazení
    if($this->zobrazeni == self::PLAIN) {
      echo $e . "\n";
    } elseif($this->zobrazeni == self::TRACY) {
      (new Tracy\BlueScreen)->render($e);
      if($e instanceof DbException) echo '<pre>', dbLastQ();
    } elseif($this->zobrazeni == self::PICARD) {
      $this->zobrazOmluvu(); // TODO možná nějaké maily / reporting?
    } else {
      // self::NIC => nezobrazovat nic
    }

    // ukončení skriptu - efektivně řešíme jen notice, vše ostatní by vedlo
    // k ukončení skriptu automaticky i po návratu z funkce `zpracuj`
    if($this->ukoncitPriNotice) {
      exit(1);
    }
  }

}

/**
 * Speciální výjimka, která se nevyhazuje, ale pouze slouží jako reprezentace
 * javascriptové výjimky obdržené ajaxem (aby mohla být dále zpracována).
 */
class JsException extends Exception {

  function __construct($zprava, $soubor, $radek) {
    $this->message = $zprava;
    $this->file = $soubor;
    $this->line = $radek;
  }

}
