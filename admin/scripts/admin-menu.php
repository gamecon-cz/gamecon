<?php

/**
 * Třída administračního menu. Načte si stránky z dané složky podle zadaného
 * klíče, sestaví menu.
 */

class AdminMenu
{

    /**
     * Sestavená menu podle složky. Moduly se během požadavku nemění, a stránka, která se ptá na přístup k jiné
     * stránce (@see isPodstrankaPristupna()), tak nečte hlavičky modulů znovu po admin/index.php.
     *
     * @var array<string, array{menu: array, patickaSoubor: ?string}>
     */
    private static array $sestavenaMenu = [];

    /** @var string */
    private readonly string $src;
    private ?array          $menu          = null;
    private ?string         $patickaSoubor = null;

    function __construct(string $src, private readonly bool $isSubmenu = false)
    {
        $this->src = rtrim(trim($src), '/') . '/';
    }

    private function sestavMenu(): void
    {
        $klic = $this->src . ($this->isSubmenu ? '|submenu' : '');
        if (!isset(self::$sestavenaMenu[$klic])) {
            $this->nactiModuly();
            self::$sestavenaMenu[$klic] = ['menu' => $this->menu, 'patickaSoubor' => $this->patickaSoubor];
        }
        $this->menu          = self::$sestavenaMenu[$klic]['menu'];
        $this->patickaSoubor = self::$sestavenaMenu[$klic]['patickaSoubor'] ?? $this->patickaSoubor;
    }

    private function nactiModuly(): void
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
                $this->menu[$url]['prava']  = $this->parsePrava($fc, (int)$m[1]);
                $this->menu[$url]['soubor'] = $this->src . $file;
                $this->menu[$url]['ikona']  = $this->parseIkona($fc);
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
                $this->menu[$url]['prava']   = $this->parsePrava($fc, (int)$m[1]);
                $this->menu[$url]['soubor']  = $this->src . $file . '/' . $file . '.php';
                $this->menu[$url]['submenu'] = 1;
                $this->menu[$url]['ikona']   = $this->parseIkona($fc);
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

    /**
     * Smí uživatel otevřít stránku adminu? Stačí mu kterékoli právo stránky, nebo kterékoli právo vybrané podstránky.
     * Podle toho pouští na stránku admin/index.php.
     *
     * @param array<string, mixed>|null $polozkaStranky položka hlavního menu z @see pole()
     * @param array<string, mixed>|null $polozkaPodstranky položka podmenu stránky z @see pole()
     */
    public static function isPristupna(?\Uzivatel $uzivatel, ?array $polozkaStranky, ?array $polozkaPodstranky = null): bool
    {
        if ($uzivatel === null || $polozkaStranky === null) {
            return false;
        }
        foreach (array_filter([$polozkaStranky, $polozkaPodstranky]) as $polozka) {
            foreach ($polozka['prava'] ?? [$polozka['pravo']] as $pravo) {
                if ($uzivatel->maPravo($pravo)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Totéž co @see isPristupna() pro podstránku zadanou adresou, třeba `aktivity/medailonky`. Pro modul, který nabízí
     * kus jiné stránky a má ho vidět jen ten, kdo by na ni směl vejít: práva tak zůstanou jen v hlavičkách modulů.
     */
    public static function isPodstrankaPristupna(\Uzivatel $uzivatel, string $stranka, string $podstranka): bool
    {
        $adresarModulu = __DIR__ . '/modules/';
        $menu          = (new self($adresarModulu))->pole();
        if (empty($menu[$stranka]['submenu'])) {
            return false;
        }
        $submenu = (new self($adresarModulu . $stranka . '/', true))->pole();

        return isset($submenu[$podstranka])
            && self::isPristupna($uzivatel, $menu[$stranka], $submenu[$podstranka]);
    }

    /**
     * Modul může místo jediného práva (hlavička `pravo:`) vyjmenovat hlavičkou
     * `prava: 100, 102, ...` víc práv — stránku pak vidí a smí otevřít každý,
     * kdo má KTERÉKOLI z nich (typicky Nápověda, viditelná pro všechny části
     * adminu). Bez hlavičky `prava:` zůstává chování jako dřív, tj. jen `pravo:`.
     *
     * @return int[]
     */
    private function parsePrava(string $fc, int $pravo): array
    {
        if (!preg_match('@\* prava: ([\d, ]+)@', $fc, $m)) {
            return [$pravo];
        }
        $prava = array_map('intval', array_filter(array_map('trim', explode(',', $m[1]))));
        return $prava ?: [$pravo];
    }

    private function parseSubMenu(string $fc, string $url)
    {
        preg_match('@\*\s*submenu_group:\s*(.*)@', $fc, $m);
        $this->menu[$url]['group'] = (int)($m[1] ?? 0);
        preg_match('@\*\s*submenu_order:\s*(.*)@', $fc, $m);
        $this->menu[$url]['order'] = (int)($m[1] ?? 0);
        preg_match('@\*\s*submenu_nazev:\s*(.*)@', $fc, $m);
        $this->menu[$url]['nazev'] = ($m[1] ?? $this->menu[$url]['nazev']);
        preg_match('@\*\s*submenu_link_open_in_blank:\s*(.*)@', $fc, $m);
        $this->menu[$url]['link_in_blank'] = (bool)($m[1] ?? false);
        // TODO
        preg_match('@\*\s*submenu_hidden:\s*(.*)@', $fc, $m);
        $this->menu[$url]['hidden'] = filter_var($m[1] ?? false, FILTER_VALIDATE_BOOL);
    }

    /**
     * Ikona z Font Awesome bez předpony (hlavička `ikona:`). Kreslí ji podmenu a menu moderního vzhledu,
     * klasické hlavní menu má vlastní obrázky.
     */
    private function parseIkona(string $fc): ?string
    {
        preg_match('@\*\s*ikona:\s*([a-z0-9-]+)@', $fc, $ikonaMatch);

        return $ikonaMatch[1] ?? null;
    }

    /** Export menu do pole. Iterátory a věci (?) */
    public function pole(): array
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
