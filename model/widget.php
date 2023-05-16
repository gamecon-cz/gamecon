<?php

use Gamecon\XTemplate\XTemplate;

/**
 * Widget je html komponenta (kus html kódu), který se dá použít na více
 * místech napříč stránkami (např. výpis orgů).
 *
 * Widgety fungují podobně jako moduly, jenom jde o kousky kódu, které se
 * vkládají do jiných stránek (možno statických i dynamických, viz třída
 * Stranka). Kód widgetů se nachází v web/widgety. Widget může (a nemusí)
 * mít vlastní .xtpl případně i .css soubory.
 */
class Widget
{

    private
        $nazev,
        $sablona,
        $skript,
        $styl;

    protected function __construct($nazev)
    {
        $this->nazev  = $nazev;
        $prefix       = WWW . '/widgety/' . $nazev;
        $this->skript = $prefix . '.php';
        if (!is_file($this->skript)) throw new WidgetException;
        $sablona = $prefix . '.xtpl';
        if (is_file($sablona)) $this->sablona = new XTemplate($sablona);
        $styl = $prefix . '.css';
        if (is_file($styl)) $this->styl = $styl;
    }

    /**
     * @return string html kód widgetu
     */
    function html()
    {
        $t = $this->sablona;
        ob_start();
        if ($this->styl) { // TODO lépe mít metodu na získání a přesunout do hlavičky
            echo "<style>";
            readfile($this->styl);
            echo "</style>\n\n";
        }
        include $this->skript;
        if ($t) {
            $t->parse(snakeToCamel($this->nazev));
            $t->out(snakeToCamel($this->nazev));
        }
        return ob_get_clean();
    }

    /**
     * @return self|null
     */
    static function zNazvu($nazev)
    {
        try {
            return new self($nazev);
        } catch (WidgetException $e) {
            return null;
        }
    }

}

class WidgetException extends Exception
{
}
