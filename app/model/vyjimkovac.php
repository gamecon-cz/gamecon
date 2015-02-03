<?php

/**
 * Třída starající se o zpracování, zobrazení a zaznamenávání výjimek a chyb
 */

class Vyjimkovac
{

  /** Zapne zpracování výjimek */
  static function aktivuj()
  {
    // fatal errory
    register_shutdown_function(function(){
      $e = error_get_last();
      if($e["type"] == E_ERROR) {
        self::zpracuj($e);
      }
    });
    // typicky notice, warningy a stricty
    set_error_handler(function($typ, $msg, $file, $line, $context){
      if(error_reporting() & $typ)
        self::zpracuj(array(
          'type' => $typ,
          'message' => $msg,
          'file' => $file,
          'line' => $line
        ));
    });
    // standardní výjimky
    set_exception_handler(function($e){
      if($e instanceof Chyba)
        $e->zpet(); // u zobrazitelných chyb ignorovat a jen zobrazit upo
      elseif($e instanceof XTemplateRecompilationException)
        back($_SERVER['REQUEST_URI']);
      else
        self::zpracuj($e);
    });
  }

  /** Vrátí HTML skript element s kódem aktivujícím js výjimkovač */
  static function js()
  {
    ob_start();
    ?><script>
      window.onerror = function(msg, url, line) {
        $.post('<?=self::jsUrl()?>', {
          msg: msg,
          url: url,
          line: line
        });
      };
    </script><?php
    return ob_get_clean();
  }

  /** Vrací url koncového bodu zpracovávajícího js výjimky */
  static function jsUrl()
  {
    return URL_WEBU.'/ajax-vyjimkovac';
  }

  /** Zavoláno ze stránky zpracovávající ajaxové info z výjimkovače */
  static function jsZpracuj()
  {
    $e = new JsException(post('msg'), post('url'), post('line'));
    self::zpracuj($e);
  }

  /** Vrátí čitelný formát typu chyby */
  private static function typHr($type)
  {
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

  /**
   * Uloží chybu nebo výjimku do db. Chyby jsou pole z error_get_last()
   * @todo výjimky obsahující návratové kódy by si mohly nést informaci o
   * kódu v sobě a tady by se (pokud výjimka implementuje rozhraní "řeknu
   * extra http kód") jen zobrazil. Lepší/horší jak explicitně určit zde?
   */
  private static function zpracuj($e)
  {
    // inicializace pole a uložení
    $r = array(
      'vznikla' => (new DateTimeCz)->formatDb(),
      'url'     => 'http://'.$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI'],
      'zdroj'   => @$_SERVER['HTTP_REFERER'] ?: null,
    );
    if($u = Uzivatel::zSession()) {
      $r['uzivatel'] = $u->id();
    }
    if($e instanceof Exception) {
      $r['zprava'] = $e->getMessage();
      $r['soubor'] = $e->getFile();
      $r['radek'] = $e->getLine();
      $r['zasobnik'] = serialize($e->getTrace());
      $r['jazyk'] = 'php';
      $r['zavaznost'] = 3;
      if($e instanceof DbException) {
        $r['data'] = trim($e->getTrace()[0]['args'][0]);
      }
      if($e instanceof JsException) {
        $r['url'] = @$_SERVER['HTTP_REFERER'] ?: null;
        $r['zavaznost'] = 2;
        $r['zasobnik'] = null;
        $r['jazyk'] = 'js';
      }
    } elseif(is_array($e)) {
      $r['zprava'] = $e['message'];
      $r['soubor'] = $e['file'];
      $r['radek'] = $e['line'];
      $r['jazyk'] = 'php';
      $r['typ'] = self::typHr($e['type']);
      $r['zavaznost'] = 1;
      if($e['type'] & E_WARNING) $r['zavaznost'] = 2;
      if($e['type'] & E_ERROR) $r['zavaznost'] = 4;
    }
    dbInsert('chyby', $r);
    // zobrazení
    if($e instanceof JsException) return; // js výjimky nezobrazovat
    if($e instanceof UrlException) header('HTTP/1.1 400 Bad Request'); // nastavení chybových hlaviček (příp. dle typu výjimky)
    else header('HTTP/1.1 500 Internal Server Error');
    if(VETEV == VYVOJOVA) {
      echo '<pre style="font-size:14px;background-color:#fff; padding: 5px; border: solid #f00 5px;">';
      if($e instanceof DbException) echo "\n\n      ".$r['data']."\n\n\n";
      echo '<b style="font-size:1.2em">'.$r['zprava'].'</b><br>';
      echo "AT $r[soubor]($r[radek])\n";
      if($e instanceof Exception) echo $e->getTraceAsString();
      echo '</pre>';
    } elseif($r['zavaznost'] > 2) {
      $out = file_get_contents(__DIR__.'/vyjimkovac-chyba.xtpl');
      $out = strtr($out, array(
        '{picard}'  => URL_WEBU.'/files/styly/styl-aktualni/exception.jpg',
        '{chyba}'   => $r['zprava']
      ));
      echo $out;
    }
  }

}

/**
 * Speciální výjimka, která se nevyhazuje, ale pouze slouží jako reprezentace
 * javascriptové výjimky obdržené ajaxem (aby mohla být dále zpracována).
 */
class JsException extends Exception
{

  function __construct($zprava, $soubor, $radek)
  {
    $this->message = $zprava;
    $this->file = $soubor;
    $this->line = $radek;
  }

}
