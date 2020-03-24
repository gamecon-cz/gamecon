<?php

/**
 * Třída pro chyby, které je možné (a smysluplné) zobrazit uživateli (tj. typic-
 * ky chyby, které způsobil uživatel např. vícenásobným pokusem o registraci a
 * podobně)
 */
class Chyba extends Exception
{

  const CHYBA = 1;
  const OZNAMENI = 2;
  const COOKIE_ZIVOTNOST = 3;

  /**
   * Vyvolá reload na volající stránku, která si chybu může vyzvednout pomocí
   * Chyba::vyzvedni()
   */
  public function zpet()
  {
    self::setCookie('CHYBY_CLASS', $this->getMessage(), time() + self::COOKIE_ZIVOTNOST);
    back();
  }

  static function nastav($zprava, $typ) {
    $postname = $typ == self::OZNAMENI ? 'CHYBY_CLASS_OZNAMENI' : 'CHYBY_CLASS';
    self::setCookie($postname, $zprava, time() + self::COOKIE_ZIVOTNOST);
  }

  private static function setCookie(string $postname, $zprava, int $ttl) {
    setcookie($postname, $zprava, $ttl);
    $_COOKIE[$postname] = $zprava;
  }

  /**
   * Vrátí text poslední chyby
   */
  public static function vyzvedni()
  {
    if(isset($_COOKIE['CHYBY_CLASS']) && $ch=$_COOKIE['CHYBY_CLASS'])
    {
      self::setCookie('CHYBY_CLASS', '', 0);
      return $ch;
    }
    else
    {
      return '';
    }
  }

  /**
   * Vrátí text posledního oznámení
   */
  private static function vyzvedniOznameni()
  {
    if(isset($_COOKIE['CHYBY_CLASS_OZNAMENI']) && $ch=$_COOKIE['CHYBY_CLASS_OZNAMENI'])
    {
      self::setCookie('CHYBY_CLASS_OZNAMENI', '', 0);
      return $ch;
    }
    else
    {
      return '';
    }
  }

  /**
   * Vrací html zformátovaný boxík s chybou
   */
  public static function vyzvedniHtml(): string
  {
    $zpravy= [];
    $error = Chyba::vyzvedni();
    if ($error) {
      $zpravy[] = self::vytvorHtmlZpravu($error, '');
    }
    $oznameni = Chyba::vyzvedniOznameni();
    if ($oznameni) {
      $zpravy[] = self::vytvorHtmlZpravu($oznameni, 'oznameni');
    }
    return implode($zpravy);
  }

  private static function vytvorHtmlZpravu(string $zprava, string $typ): string {
    return '<div class="chybaBlok"><div class="chyba '.$typ.'" id="chybovaZprava">'.$zprava.'</div></div>
        <script>
          var len=$("#chybovaZprava").html().length;
          $("#chybovaZprava").delay(2000+len*30).fadeOut(1500);
        </script>';
  }

}
