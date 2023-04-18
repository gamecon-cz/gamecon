<?php

/**
 * Třída pro chyby, které je možné (a smysluplné) zobrazit uživateli (tj. typic-
 * ky chyby, které způsobil uživatel např. vícenásobným pokusem o registraci a
 * podobně)
 */
class Chyba extends Exception
{

    public const CHYBA    = 1;
    public const VAROVANI = 2;
    public const OZNAMENI = 3;

    private const COOKIE_ZIVOTNOST_SEKUND = 3;

    private const KLIC_CHYBY    = 'CHYBY_CLASS';
    private const KLIC_VAROVANI = 'CHYBY_CLASS_VAROVANI';
    private const KLIC_OZNAMENI = 'CHYBY_CLASS_OZNAMENI';

    /**
     * Vyvolá reload na volající stránku, která si chybu může vyzvednout pomocí
     * self::vyzvedni()
     */
    public function zpet()
    {
        self::setCookie(self::KLIC_CHYBY, $this->getMessage(), time() + self::COOKIE_ZIVOTNOST_SEKUND);
        back();
    }

    public static function nastav(string $zprava, int $typ = self::CHYBA)
    {
        $cookieName = match ($typ) {
            self::VAROVANI => self::KLIC_VAROVANI,
            self::OZNAMENI => self::KLIC_OZNAMENI,
            default => self::KLIC_CHYBY,
        };
        $zpravy     = self::vyzvedni($cookieName);
        $zpravy[]   = $zprava;
        self::setCookie($cookieName, $zpravy, time() + self::COOKIE_ZIVOTNOST_SEKUND);
    }

    private static function setCookie(string $cookieName, $value, int $ttl)
    {
        if ($value === '') {
            setcookie($cookieName, '', $ttl);
            unset($_COOKIE[$cookieName]);
            return;
        }
        $jsonValue = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        setcookie($cookieName, $jsonValue, $ttl);
        $_COOKIE[$cookieName] = $jsonValue;
    }

    /**
     * Vrátí text poslední chyby
     */
    public static function vyzvedniChybu(): string
    {
        $chyby = self::vyzvedni(self::KLIC_CHYBY);
        return (string)reset($chyby);
    }

    private static function vyzvedniVsechnyChyby(): array
    {
        return self::vyzvedni(self::KLIC_CHYBY);
    }

    private static function vyzvedni(string $cookieName): array
    {
        $hodnotaJson = $_COOKIE[$cookieName] ?? '';
        if ($hodnotaJson === '') {
            return [];
        }
        // vyzvednuto, smažeme
        self::setCookie($cookieName, '', 0);
        $hodnota = json_decode($hodnotaJson, true) ?? $hodnotaJson;
        if ($hodnota === '') {
            return [];
        }
        return (array)$hodnota;
    }

    private static function vyzvedniVsechnaOznameni(): array
    {
        return self::vyzvedni(self::KLIC_OZNAMENI);
    }

    private static function vyzvedniVsechnaVarovani(): array
    {
        return self::vyzvedni(self::KLIC_VAROVANI);
    }

    /**
     * Vrací html zformátovaný boxík s chybou
     */
    public static function vyzvedniHtml(): string
    {
        $zpravyPodleTypu = [];
        $vsechnyChyby    = self::vyzvedniVsechnyChyby();
        if ($vsechnyChyby) {
            $zpravyPodleTypu['chyby'] = $vsechnyChyby;
        }
        $vsechnaVarovani = self::vyzvedniVsechnaVarovani();
        if ($vsechnaVarovani) {
            $zpravyPodleTypu['varovani'] = $vsechnaVarovani;
        }
        $vsechnaOznameni = self::vyzvedniVsechnaOznameni();
        if ($vsechnaOznameni) {
            $zpravyPodleTypu['oznameni'] = $vsechnaOznameni;
        }
        if (!$zpravyPodleTypu) {
            return '';
        }
        return self::vytvorHtmlZpravu($zpravyPodleTypu);
    }

    private static function vytvorHtmlZpravu(array $zpravyPodleTypu): string
    {
        $zpravy                 = '';
        $chybaBlokId            = uniqid('chybaBlokId', true);
        $delkaTextu             = 0;
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
                    $tridaPodleTypu         = 'errorHlaska';
                    $tridaPodleHlavnihoTypu = 'errorHlaska';
            }
            $zpravyJednohoTypuHtml = '';
            foreach ($zpravyJednohoTypu as $zprava) {
                $zpravyJednohoTypuHtml .= sprintf('<div class="hlaska %s">%s</div>', $tridaPodleTypu, $zprava);
                $delkaTextu            += strlen(strip_tags($zprava));
            }
            $zpravy .= sprintf('<div>%s</div>', $zpravyJednohoTypuHtml);
        }
        $zobrazeniSekund = ceil($delkaTextu / 20) + 4.0;
        $mizeniSekund    = 2.0;

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
