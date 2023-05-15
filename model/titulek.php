<?php

/**
 * Třída pro titulek stránky.
 */

class Titulek
{
    //private var $aktivita='';
    var $stranka   = '';
    var $format    = 'GameCon – %1';
    var $default   = 'GameCon';
    var $socNahled = '';

    /**
     *
     */
    public function __construct()
    {
    }

    public function aktivita($aktivita = null)
    {
        $this->stranka   = $aktivita->nazev();
        $this->socNahled = $aktivita->obrazek();
    }

    public function stranka($stranka = null)
    {
        return $stranka ? $this->stranka = $stranka : $this->stranka;
    }

    /** Vrátí komplet titulek stránky jak se vyrobil */
    public function cely()
    {
        if ($this->stranka)
            return strtr($this->format, ['%1' => $this->stranka]);
        else
            return $this->default;
    }

    /** Nastaví obrázek s danou URL jaké náhled pro sociální sítě */
    public function socNahled($url = null)
    {
        return $url ? $this->socNahled = $url : $this->socNahled;
    }

    /**
     * Vrátí html kód (vložitelný do hlavičky) který způsobí zobrazení
     * konkrétního náhledu na sociálních sítích.
     */
    public function socNahledHtml()
    {
        if ($this->socNahled) {
            $url = substr($this->socNahled, 0, 7) == 'http://' ? $this->socNahled : (URL_WEBU . $this->socNahled); //druhé lomítko je na začátku cesty!
            return '<link rel="image_src" href="' . $url . '">' . "\n" .
                '<meta property="og:image" content="' . $url . '">';
        } else
            return '';
    }

    /**
     * V HTML textu daném v $text najde url obrázku
     */
    public function socNahledNajdi($text)
    {
        $rvNahled = '@<img\s+src="([^"]+)"\s+class="socNahled"[^>]*>|<img\s+class="socNahled"\s+src="([^"]+)"[^>]*>@';
        preg_match($rvNahled, substr($text, 0, 2048), $shody);
        if (isset($shody[1]) && $shody[1])
            $this->socNahled($shody[1]);
        else if (isset($shody[2]) && $shody[2])
            $this->socNahled($shody[2]);
    }

}

?>
