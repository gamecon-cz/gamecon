<?php

/**
 * Třída starající se o zpracování, zobrazení a zaznamenávání výjimek a chyb
 */

class Vyjimkovac
{

  private $dbFile;
  private $db;

  function __construct($dbFile) {
    $this->dbFile = $dbFile;
  }

  /** Zapne zpracování výjimek */
  function aktivuj() {
    // fatal errory
    register_shutdown_function(function(){
      $e = error_get_last();
      if($e["type"] == E_ERROR) {
        //ob_end_clean(); // odstranění výstupu xdebugu či výstupu stránky
        $this->zpracuj(Tracy\Helpers::fixStack(new ErrorException($e['message'], 0, $e['type'], $e['file'], $e['line'])));
      }
    });
    // typicky notice, warningy a stricty
    set_error_handler(function($typ, $msg, $file, $line, $context){
      // omezení typu na pouze aktuálně reportované
      // (nutné kvůli operátoru @ použitého typicky v parse_ metodách šablon,
      // který by jinak tento handler odchytával)
      if(error_reporting() & $typ) {
        $this->zpracuj(Tracy\Helpers::fixStack(new ErrorException($msg, 0, $typ, $file, $line)));
      }
    });
    // standardní výjimky
    set_exception_handler(function($e){
      if($e instanceof Chyba)
        $e->zpet(); // u zobrazitelných chyb ignorovat a jen zobrazit upo
      elseif($e instanceof XTemplateRecompilationException)
        back($_SERVER['REQUEST_URI']);
      else
        $this->zpracuj($e);
    });
  }

  /** Vrátí PDO instanci s připravenou databází pro uložení / čtení chyb */
  protected function db() {
    if(!$this->db) {
      $this->db = new EPDO('sqlite:'.$this->dbFile);
    }
    return $this->db;
  }

  /** Vrátí HTML skript element s kódem aktivujícím js výjimkovač */
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

  /** Zavoláno ze stránky zpracovávající ajaxové info z výjimkovače */
  function jsZpracuj() {
    $e = new JsException(post('msg'), post('url'), post('line'));
    $this->zpracuj($e);
  }

  /** Zobrazí public omluvnou stránku uživateli */
  function zobrazOmluvu() {
    $out = file_get_contents(__DIR__ . '/vyjimkovac-omluva.xtpl');
    $out = strtr($out, array(
      '{picard}'  => URL_WEBU . '/soubory/styl/exception.jpg',
    ));
    echo $out;
  }

  /** Uloží výjimku a zobrazí info podle verze ostrá / dev */
  protected function zpracuj(Exception $e) {
    // uložení
    VyjimkovacChyba::zVyjimky($e)->uloz($this->db());
    // hlavičky
    if($e instanceof JsException)       return; // js výjimky nezobrazovat
    elseif($e instanceof UrlException)  header('HTTP/1.1 400 Bad Request'); // nastavení chybových hlaviček
    else                                header('HTTP/1.1 500 Internal Server Error');
    // zobrazení
    if(VETEV == VYVOJOVA) {
      (new Tracy\BlueScreen)->render($e);
      if($e instanceof DbException) echo '<pre>', dbLastQ();
    } else {
      $this->zobrazOmluvu(); // TODO možná nějaké maily / reporting?
    }
    // radši umřít než riskovat nekonzistenci na ostré
    die();
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
