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

    private function sestavMenu(): void
    {
        $this->menu = [];
        $d          = opendir($this->src);
        while (($file = readdir($d)) !== false) {
            if (strpos($file, '.php') && substr($file, 0, 1) != '_') { //načtení souboru, vyhledání proměnných v hlavičkách
                $this->menu[substr($file, 0, -4)] = $this->nactiPolozku($this->src . $file);
            } else if (strpos($file, '.') === false && strpos($file, '_') === false
                && is_dir($this->src . $file)) {
                $this->menu[$file] = $this->nactiPolozku($this->src . $file . '/' . $file . '.php') + ['submenu' => 1];
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
     * Položka menu z hlavičky modulu, ta je v prvních 2048 bajtech souboru.
     *
     * @return array<string, mixed>
     */
    private function nactiPolozku(string $soubor): array
    {
        $fc = (string)file_get_contents($soubor, false, null, 0, 2048);
        preg_match('@\* nazev: (.*)@', $fc, $m);
        $polozka['nazev'] = $m[1];
        preg_match('@\* pravo: (\d*)@', $fc, $m);
        $polozka['pravo']  = (int)$m[1];
        $polozka['prava']  = $this->parsePrava($fc, (int)$m[1]);
        $polozka['soubor'] = $soubor;
        $polozka['ikona']  = $this->parseIkona($fc);

        return $this->isSubmenu
            ? $this->parseSubMenu($fc, $polozka)
            : $polozka;
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
     * Čte jen hlavičky té stránky a podstránky, ne celé složky jako menu.
     */
    public static function isPodstrankaPristupna(\Uzivatel $uzivatel, string $stranka, string $podstranka): bool
    {
        $adresarModulu  = __DIR__ . '/modules/';
        $adresarStranky = $adresarModulu . $stranka . '/';
        // stránka s podmenu je složka, podstránka v ní soubor (jako Medailonky); názvy, které by menu vynechalo, neplatí,
        // viz sestavMenu()
        $souborStranky    = $adresarStranky . $stranka . '.php';
        $souborPodstranky = $adresarStranky . $podstranka . '.php';
        if (preg_match('~^[^._/]+$~', $stranka) !== 1 || preg_match('~^[^_/][^/]*$~', $podstranka) !== 1
            || !is_file($souborStranky) || !is_file($souborPodstranky)
        ) {
            return false;
        }

        return self::isPristupna(
            $uzivatel,
            (new self($adresarModulu))->nactiPolozku($souborStranky),
            (new self($adresarStranky, true))->nactiPolozku($souborPodstranky),
        );
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

    /**
     * @param array<string, mixed> $polozka
     * @return array<string, mixed>
     */
    private function parseSubMenu(string $fc, array $polozka): array
    {
        preg_match('@\*\s*submenu_group:\s*(.*)@', $fc, $m);
        $polozka['group'] = (int)($m[1] ?? 0);
        preg_match('@\*\s*submenu_order:\s*(.*)@', $fc, $m);
        $polozka['order'] = (int)($m[1] ?? 0);
        preg_match('@\*\s*submenu_nazev:\s*(.*)@', $fc, $m);
        $polozka['nazev'] = ($m[1] ?? $polozka['nazev']);
        preg_match('@\*\s*submenu_link_open_in_blank:\s*(.*)@', $fc, $m);
        $polozka['link_in_blank'] = (bool)($m[1] ?? false);
        // TODO
        preg_match('@\*\s*submenu_hidden:\s*(.*)@', $fc, $m);
        $polozka['hidden'] = filter_var($m[1] ?? false, FILTER_VALIDATE_BOOL);

        return $polozka;
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
