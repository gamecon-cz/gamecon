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
  public function zpet() {
    self::setCookie('CHYBY_CLASS', $this->getMessage(), time() + self::COOKIE_ZIVOTNOST);
    back();
  }

  static function nastav($zprava, $typ=null) {
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
  public static function vyzvedni() {
    if (isset($_COOKIE['CHYBY_CLASS']) && $ch = $_COOKIE['CHYBY_CLASS']) {
      self::setCookie('CHYBY_CLASS', '', 0);
      return $ch;
    } else {
      return '';
    }
  }

  /**
   * Vrátí text posledního oznámení
   */
  private static function vyzvedniOznameni() {
    if (isset($_COOKIE['CHYBY_CLASS_OZNAMENI']) && $ch = $_COOKIE['CHYBY_CLASS_OZNAMENI']) {
      self::setCookie('CHYBY_CLASS_OZNAMENI', '', 0);
      return $ch;
    } else {
      return '';
    }
  }

  /**
   * Vrací html zformátovaný boxík s chybou
   */
  public static function vyzvedniHtml(): string {
    $zpravyPodleTypu = [];
    $error = Chyba::vyzvedni();
    if ($error) {
      $zpravyPodleTypu['error'] = [$error];
    }
    $oznameni = Chyba::vyzvedniOznameni();
    if ($oznameni) {
      $zpravyPodleTypu['oznameni'] = [$oznameni];
    }
    if (!$zpravyPodleTypu) {
      return '';
    }
    return self::vytvorHtmlZpravu($zpravyPodleTypu);
  }

  private static function vytvorHtmlZpravu(array $zpravyPodleTypu): string {
    $zpravy = '';
    $chybaBlokId = uniqid('chybaBlokId', true);
    $delkaTextu = 0;
    foreach ($zpravyPodleTypu as $typ => $zpravyJednohoTypu) {
      switch ($typ) {
        case 'oznameni':
          $tridaPodleTypu = 'oznameni';
          break;
        default :
          $tridaPodleTypu = 'errorHlaska';
      }
      $zpravyJednohoTypuHtml = '';
      foreach ($zpravyJednohoTypu as $zprava) {
        $zpravyJednohoTypuHtml .= sprintf('<div class="hlaska %s">%s</div>', $tridaPodleTypu, htmlentities($zprava));
        $delkaTextu += strlen(strip_tags($zprava));
      }
      $zpravy .= sprintf('<div>%s</div>', $zpravyJednohoTypuHtml);
    }
    $zobrazeniSekund = ceil($delkaTextu / 20) + 4.0;
    $mizeniSekund = 2.0;

    return <<<HTML
<div class="chybaBlok" id="{$chybaBlokId}">
  {$zpravy}
  <div class="chybaBlok_zavrit admin_zavrit">❌</div>
  <script>
    // warning! this is both for web (without jQuery) as well a for admin (without LESS)
    (() => {
      let chyba = document.currentScript.parentNode
      setTimeout(() => {
            chyba.style.transition = "opacity "+{$mizeniSekund}+"s"
            chyba.style.opacity = 0.0
            setTimeout(() => chyba.remove(), ({$mizeniSekund} * 1000))
        },
        ({$zobrazeniSekund} * 1000)
      )
      chyba.querySelector(".chybaBlok_zavrit").onclick = () => chyba.remove()
    })()
  </script>
  </div>
HTML
      ;
  }

}
