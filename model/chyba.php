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
    public const VALIDACE = 4;

    protected const COOKIE_ZIVOTNOST_SEKUND = 3;

    protected const KLIC_CHYBY    = 'CHYBY_CLASS';
    protected const KLIC_VAROVANI = 'CHYBY_CLASS_VAROVANI';
    protected const KLIC_OZNAMENI = 'CHYBY_CLASS_OZNAMENI';
    protected const KLIC_VALIDACE = 'CHYBY_CLASS_VALIDACE';

    /**
     * Vyvolá reload na volající stránku, která si chybu může vyzvednout pomocí
     * static::vyzvedni()
     */
    public function zpet()
    {
        static::setCookie(static::KLIC_CHYBY, $this->getMessage(), time() + static::COOKIE_ZIVOTNOST_SEKUND);
        back();
    }

    public static function nastavZChyb(
        Chyby $chyby,
        int   $typ,
    ) {
        foreach ($chyby->vsechny() as $klic => $text) {
            static::nastav($text, $typ, $klic);
        }
    }

    public static function nastav(
        string  $zprava,
        int     $typ = self::CHYBA,
        ?string $klic = null,
    ) {
        $cookieName = match ($typ) {
            static::VAROVANI => static::KLIC_VAROVANI,
            static::OZNAMENI => static::KLIC_OZNAMENI,
            static::VALIDACE => static::KLIC_VALIDACE,
            default          => static::KLIC_CHYBY,
        };
        $zpravy     = static::vyzvedni($cookieName);
        if ($klic !== null) {
            $zpravy[$klic] = $zprava;
        } else {
            $zpravy[] = $zprava;
        }
        static::setCookie($cookieName, $zpravy, time() + static::COOKIE_ZIVOTNOST_SEKUND);
    }

    protected static function setCookie(
        string $cookieName,
               $value,
        int    $ttl,
    ) {
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
        $chyby = static::vyzvedni(static::KLIC_CHYBY);

        return (string)reset($chyby);
    }

    protected static function vyzvedniVsechnyChyby(): array
    {
        return static::vyzvedni(static::KLIC_CHYBY);
    }

    protected static function vyzvedni(string $cookieName): array
    {
        $hodnotaJson = $_COOKIE[$cookieName] ?? '';
        if ($hodnotaJson === '') {
            return [];
        }
        // vyzvednuto, smažeme
        static::setCookie($cookieName, '', 0);
        $hodnota = json_decode($hodnotaJson, true) ?? $hodnotaJson;
        if ($hodnota === '') {
            return [];
        }

        return (array)$hodnota;
    }

    protected static function vyzvedniVsechnaOznameni(): array
    {
        return static::vyzvedni(static::KLIC_OZNAMENI);
    }

    protected static function vyzvedniVsechnaVarovani(): array
    {
        return static::vyzvedni(static::KLIC_VAROVANI);
    }

    /**
     * Vrací html zformátovaný boxík s chybou
     */
    public static function vyzvedniHtml(): string
    {
        $zpravyPodleTypu = [];
        $vsechnyChyby    = static::vyzvedniVsechnyChyby();
        if ($vsechnyChyby) {
            $zpravyPodleTypu['chyby'] = $vsechnyChyby;
        }
        $vsechnaVarovani = static::vyzvedniVsechnaVarovani();
        if ($vsechnaVarovani) {
            $zpravyPodleTypu['varovani'] = $vsechnaVarovani;
        }
        $vsechnaOznameni = static::vyzvedniVsechnaOznameni();
        if ($vsechnaOznameni) {
            $zpravyPodleTypu['oznameni'] = $vsechnaOznameni;
        }
        if (!$zpravyPodleTypu) {
            return '';
        }

        return static::vytvorHtmlZpravu($zpravyPodleTypu);
    }

    protected static function vytvorHtmlZpravu(array $zpravyPodleTypu): string
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
      chyba.onclick = () => chyba.remove()
    })()
  </script>
  </div>
HTML
            ;
    }

    public static function vyzvedniVsechnyValidace(): array
    {
        return static::vyzvedni(static::KLIC_VALIDACE);
    }

}
