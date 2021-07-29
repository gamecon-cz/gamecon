<?php

/**
 * Třída pro chyby, které je možné (a smysluplné) zobrazit uživateli (tj. typic-
 * ky chyby, které způsobil uživatel např. vícenásobným pokusem o registraci a
 * podobně)
 */
class Chyba extends Exception
{

    public const CHYBA = 1;
    public const VAROVANI = 2;
    public const OZNAMENI = 3;

    private const COOKIE_ZIVOTNOST_SEKUND = 3;

    /**
     * Vyvolá reload na volající stránku, která si chybu může vyzvednout pomocí
     * Chyba::vyzvedni()
     */
    public function zpet() {
        self::setCookie('CHYBY_CLASS', $this->getMessage(), time() + self::COOKIE_ZIVOTNOST_SEKUND);
        back();
    }

    static function nastav($zprava, $typ = null) {
        $postname = 'CHYBY_CLASS';
        if ($typ == self::VAROVANI) {
            $postname = 'CHYBY_CLASS_VAROVANI';
        } elseif ($typ == self::OZNAMENI) {
            $postname = 'CHYBY_CLASS_OZNAMENI';
        }
        self::setCookie($postname, $zprava, time() + self::COOKIE_ZIVOTNOST_SEKUND);
    }

    private static function setCookie(string $postname, $zprava, int $ttl) {
        setcookie($postname, $zprava, $ttl);
        $_COOKIE[$postname] = $zprava;
    }

    /**
     * Vrátí text poslední chyby
     */
    public static function vyzvedniChybu() {
        if (isset($_COOKIE['CHYBY_CLASS']) && $chyba = $_COOKIE['CHYBY_CLASS']) {
            self::setCookie('CHYBY_CLASS', '', 0);
            return $chyba;
        }
        return '';
    }

    /**
     * Vrátí text posledního oznámení
     */
    private static function vyzvedniOznameni() {
        if (isset($_COOKIE['CHYBY_CLASS_OZNAMENI']) && $oznameni = $_COOKIE['CHYBY_CLASS_OZNAMENI']) {
            self::setCookie('CHYBY_CLASS_OZNAMENI', '', 0);
            return $oznameni;
        }
        return '';
    }

    /**
     * Vrátí text posledního oznámení
     */
    private static function vyzvedniVarovani() {
        if (isset($_COOKIE['CHYBY_CLASS_VAROVANI']) && $varovani = $_COOKIE['CHYBY_CLASS_VAROVANI']) {
            self::setCookie('CHYBY_CLASS_VAROVANI', '', 0);
            return $varovani;
        }
        return '';
    }

    /**
     * Vrací html zformátovaný boxík s chybou
     */
    public static function vyzvedniHtml(): string {
        $zpravyPodleTypu = [];
        $error = Chyba::vyzvedniChybu();
        if ($error) {
            $zpravyPodleTypu['error'] = [$error];
        }
        $varovani = Chyba::vyzvedniVarovani();
        if ($varovani) {
            $zpravyPodleTypu['varovani'] = [$varovani];
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
        $tridaPodleHlavnihoTypu = 'oznameni';
        foreach ($zpravyPodleTypu as $typ => $zpravyJednohoTypu) {
            switch ($typ) {
                case 'oznameni':
                    $tridaPodleTypu = 'oznameni';
                    break;
                case 'varovani':
                    $tridaPodleTypu = 'varovani';
                    break;
                default :
                    $tridaPodleTypu = 'errorHlaska';
                    $tridaPodleHlavnihoTypu = 'errorHlaska';
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
<div class="chybaBlok chybaBlok-{$tridaPodleHlavnihoTypu}" id="{$chybaBlokId}">
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
