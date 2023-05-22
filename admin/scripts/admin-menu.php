<?php

/**
 * Třída administračního menu. Načte si stránky z dané složky podle zadaného
 * klíče, sestaví menu.
 */

class AdminMenu
{

    /** @var string */
    private readonly string $src;
    private ?array          $menu          = null;
    private ?string         $patickaSoubor = null;

    function __construct(string $src, private readonly bool $isSubmenu = false)
    {
        $this->src = rtrim(trim($src), '/') . '/';
    }

    private function sestavMenu()
    {
        $this->menu = [];
        $d          = opendir($this->src);
        while (($file = readdir($d)) !== false) {
            //echo($file.' '.is_dir($this->src.$file).'<br />');
            if (strpos($file, '.php') && substr($file, 0, 1) != '_') { //načtení souboru, vyhledání proměnných v hlavičkách
                $url = substr($file, 0, -4);
                $fc  = (string)file_get_contents($this->src . $file, false, null, 0, 2048);
                preg_match('@\* nazev: (.*)@', $fc, $m);
                $this->menu[$url]['nazev'] = $m[1];
                preg_match('@\* pravo: (\d*)@', $fc, $m);
                $this->menu[$url]['pravo']  = (int)$m[1];
                $this->menu[$url]['soubor'] = $this->src . $file;
                if ($this->isSubmenu) {
                    $this->parseSubMenu($fc, $url);
                }
            } else if (strpos($file, '.') === false && strpos($file, '_') === false
                && is_dir($this->src . $file)) {
                $url = $file;
                $fc  = (string)file_get_contents($this->src . $file . '/' . $file . '.php', false, null, 0, 2048);
                preg_match('@\* nazev: (.*)@', $fc, $m);
                $this->menu[$url]['nazev'] = $m[1];
                preg_match('@\* pravo: (.*)@', $fc, $m);
                $this->menu[$url]['pravo']   = (int)$m[1];
                $this->menu[$url]['soubor']  = $this->src . $file . '/' . $file . '.php';
                $this->menu[$url]['submenu'] = 1;
                if ($this->isSubmenu) {
                    $this->parseSubMenu($fc, $url);
                }
                /*while(($sf=readdir($sd))!==FALSE)
                {
                  if(strpos($sf,'.php'))
                  { //načtení souboru, vyhledání proměnných v hlavičkách
                    $url=substr($sf,0,-4);
                    $fc=file_get_contents($this->src.$file.$sf,false,null,-1,2048);
                    preg_match('@\* nazev: (.*)@',$fc,$m);
                    $this->menu[$url]['nazev']=$m[1];
                    preg_match('@\* pravo: (.*)@',$fc,$m);
                    $this->menu[$url]['pravo']=(int)$m[1];
                  }
                }*/
            } else if ($file === '_paticka.php') {
                $this->patickaSoubor = $this->src . $file;
            }
        }
        closedir($d);
        uasort($this->menu, function ($a, $b) { //třídění dle práva, pak názvu
            $diff = $a['pravo'] - $b['pravo'];
            if ($diff <> 0) {
                return $diff;
            }
            return strcmp($a['nazev'], $b['nazev']);
        });
    }

    private function parseSubMenu(string $fc, string $url)
    {
        preg_match('@\* submenu_group: (.*)@', $fc, $m);
        $this->menu[$url]['group'] = (int)($m[1] ?? 0);
        preg_match('@\* submenu_order: (.*)@', $fc, $m);
        $this->menu[$url]['order'] = (int)($m[1] ?? 0);
        preg_match('@\* submenu_nazev: (.*)@', $fc, $m);
        $this->menu[$url]['nazev'] = ($m[1] ?? $this->menu[$url]['nazev']);
        preg_match('@\* submenu_link_open_in_blank: (.*)@', $fc, $m);
        $this->menu[$url]['link_in_blank'] = (bool)($m[1] ?? false);
    }

    /** Export menu do pole. Iterátory a věci (?) */
    public function pole()
    {
        if (!isset($this->menu)) {
            $this->sestavMenu();
        }
        return $this->menu;
    }

    public function patickaSoubor(): ?string
    {
        if (!isset($this->patickaSoubor)) {
            $this->patickaSoubor = '';
            $this->sestavMenu();
        }
        return $this->patickaSoubor;
    }
}
