<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Gamecon\Kanaly\EmailovaSablona\SqlStruktura\EmailovaSablonaSqlStruktura;
use Gamecon\Uzivatel\SqlStruktura\UpominkaVlastniZneniSqlStruktura;

/**
 * Převede JPEG a PNG vložené do textů webu (`soubory/obsah`) do AVIF a přepíše odkazy na ně, stejně jako to dělá nové
 * nahrání. Obrázek se převede, jen když ho texty vkládají (ne když na něj odkazují ke stažení) a nezabírá ho jiný modul
 * ani e-mail. Originál se při převodu nemaže, to je až @see self::smazPuvodni().
 *
 * Totéž dělá pro fotky uživatelů (@see self::proFotkyUzivatelu()), jen bez textů: fotku web najde podle id uživatele,
 * takže se nic nepřepisuje a AVIF, který leží vedle originálu, najde `Uzivatel::fotka()` jako první.
 */
final class PrevodObrazkuNaAvif
{
    private const string PRIPONA_CILE = 'avif';
    /** Co se hledá v textech: zdroje, a AVIF, který tam může být z databáze zkopírované z prostředí, kde už se převádělo */
    private const array PRIPONY_ODKAZU = ['jpg', 'jpeg', 'png', 'avif'];
    /** @var list<array{string, string}> tabulka a sloupec s textem e-mailu nebo upomínky */
    private const array TEXTY_E_MAILU = [
        [EmailovaSablonaSqlStruktura::EMAILOVA_SABLONA_TABULKA, EmailovaSablonaSqlStruktura::TEXT],
        [UpominkaVlastniZneniSqlStruktura::UPOMINKA_VLASTNI_ZNENI_TABULKA, UpominkaVlastniZneniSqlStruktura::TEXT],
    ];
    /** Složky, které stahují lidé, takže zůstávají, jak jsou */
    private const array NEPREVADENE_SLOZKY = ['presskit'];
    /** Ztrátový AVIF se od originálu liší o desítky dB, rozbitý (ImageMagick 6, YCbCr) o víc než deset */
    private const float MIN_PSNR_DB = 25.0;
    private const float MAX_ODCHYLKA_POMERU_STRAN = 0.02;
    private const string PREFIX_DOCASNEHO_SOUBORU = '.prevod-avif-';
    /** Kolik souborů se najednou ptá, jestli je na ně odkaz: každý přidává dvě podmínky do dotazu nad všemi texty */
    private const int DAVKA_PRI_KONTROLE_POUZITI = 100;
    /** Cest najednou v jednom dotazu na sloupec: každá nese až pět vzorů pro LIKE, parametrů je nejvýš 151 */
    private const int DAVKA_PRI_KONTROLE_SLOUPCE = 30;
    private const array ZNAKOVE_SADY_S_DIAKRITIKOU = ['utf8mb4', 'utf8mb3', 'utf8'];

    /**
     * @param string $adresarObsahu kořen, ve kterém se převádí: `soubory/obsah` webu, u fotek uživatelů jejich složka
     * @param string|null $podslozka složka v něm, na kterou se běh omezí (zkouška), třeba `blog`
     */
    public function __construct(
        private readonly OptimalizaceObrazku $optimalizace,
        private readonly PouzitiObrazku $pouziti,
        private readonly string $adresarObsahu,
        private readonly ?string $podslozka = null,
        private readonly OblastPrevoduObrazkuEnum $oblast = OblastPrevoduObrazkuEnum::TEXTY,
    ) {
    }

    /**
     * @param string|null $podslozka složka ve složce fotek, na kterou se běh omezí (testy)
     */
    public static function proFotkyUzivatelu(OptimalizaceObrazku $optimalizace, PouzitiObrazku $pouziti, ?string $podslozka = null): self
    {
        return new self(
            $optimalizace,
            $pouziti,
            ZpracovaneObrazky::adresarFotekUzivatelu(),
            $podslozka,
            OblastPrevoduObrazkuEnum::FOTKY_UZIVATELU,
        );
    }

    /**
     * Zakóduje všechny obrázky, které se převádějí, a pokud je $naostro, uloží AVIF vedle originálu a přepíše texty.
     * Bez $naostro se nic nezapíše (AVIF vznikne jen dočasně, aby se zjistila úspora), ale výsledek je tentýž, dokud
     * zápis nějakého AVIF neselže.
     *
     * @param int|null $limit nejvýš tolik nově převedených obrázků, pro zkoušku
     */
    public function proved(bool $naostro, ?int $limit = null): VysledekPrevoduObrazku
    {
        $odkazy = $this->oblast->isZTextu()
            ? $this->getOdkazyVTextech()
            : [];

        $kandidati = [];
        $chybejicich = 0;
        foreach ($odkazy as $cesta => $souhrn) {
            if (!$this->isVRozsahuBehu($cesta) || ($this->isAvif($cesta) && is_file($this->getAbsolutniCesta($cesta)))) {
                continue;
            }
            $originaly = $this->getOriginalyKOdkazu($cesta);
            if ($originaly === []) {
                ++$chybejicich;
                continue;
            }
            foreach ($originaly as $original) {
                $kandidati[$original] = $this->sloucOdkazy(
                    $kandidati[$original] ?? ['vlozeno' => 0, 'ke_stazeni' => 0],
                    $souhrn,
                );
            }
        }
        if (!$this->oblast->isZTextu()) {
            // fotku web najde podle id uživatele, žádný text na ni neodkazuje
            foreach ($this->getObrazky() as $cesta) {
                $kandidati[$cesta] = ['vlozeno' => 1, 'ke_stazeni' => 0];
            }
        }
        ksort($kandidati);
        $cestyKKontrole = [];
        foreach (array_keys($kandidati) as $cesta) {
            $cesta = (string) $cesta;
            $cestyKKontrole[] = $cesta;
            $cestyKKontrole[] = self::getCestaAvif($cesta);
        }
        $zEmailu = $kandidati === [] || !$this->oblast->isZTextu()
            ? []
            : $this->najdiOdkazyVSloupcich($cestyKKontrole, $this->getSloupceKeKontrole(false));

        $polozky = [];
        $nahrady = [];
        $obsazeneCile = [];
        $prevedeno = 0;
        foreach ($kandidati as $cesta => $souhrn) {
            if ($limit !== null && $prevedeno >= $limit) {
                break;
            }
            $polozka = $this->zpracuj($cesta, $souhrn['ke_stazeni'] > 0, $zEmailu, $obsazeneCile, $naostro);
            $polozky[] = $polozka;
            if ($polozka->stav->isPrevedeny()) {
                $nahrady[$cesta] = self::PRIPONA_CILE;
                $obsazeneCile[self::getCestaAvif($cesta)] = $cesta;
            }
            if ($polozka->stav === StavPrevoduObrazkuEnum::PREVEDENO) {
                ++$prevedeno;
            }
        }

        $texty = $this->prepisTexty($nahrady, $naostro);

        return new VysledekPrevoduObrazku(
            polozky: $polozky,
            nepouzitychObrazku: $this->spoctiNepouzite($odkazy),
            odkazuNaChybejiciSoubor: $chybejicich,
            prepsanoRadku: $texty['prepsano'],
            radkuZmenenychSoubezne: $texty['soubezne'],
        );
    }

    /**
     * Smaže originály, ke kterým je vedle stejný AVIF a na které už neodkazuje žádný text ani kód. Samostatný krok:
     * po převodu se na staré adresy ještě může odkazovat zvenku (vyhledávače, odeslané e-maily).
     */
    public function smazPuvodni(bool $naostro): VysledekPrevoduObrazku
    {
        $kandidati = [];
        foreach ($this->getObrazky() as $cesta) {
            $avif = $this->getAbsolutniCesta(self::getCestaAvif($cesta));
            // AVIF, který je symlink, může zmizet spolu s cílem, a pak by po smazaném originálu nezbylo nic
            if (!$this->isSpravovanyJinde($cesta) && is_file($avif) && !is_link($avif) && !$this->maVicFotekUzivatele($cesta)) {
                $kandidati[] = $cesta;
            }
        }
        if ($kandidati === []) {
            return new VysledekPrevoduObrazku([]);
        }
        $odkazyJinde = $this->najdiOdkazyVSloupcich($kandidati, $this->getSloupceKeKontrole(true));

        $pouziti = [];
        foreach (array_chunk($kandidati, self::DAVKA_PRI_KONTROLE_POUZITI) as $davka) {
            $pouziti += $this->pouziti->kdeJsouPouzity(array_map($this->getAbsolutniCesta(...), $davka));
        }

        $polozky = [];
        foreach ($kandidati as $cesta) {
            $zdroj = $this->getAbsolutniCesta($cesta);
            $velikost = (int) @filesize($zdroj);
            $kdeSePouziva = $pouziti[$zdroj][0]['popis'] ?? null;
            $mistoVJinemTextu = $odkazyJinde[$cesta] ?? null;
            if ($kdeSePouziva === null && $mistoVJinemTextu !== null) {
                $kdeSePouziva = 'sloupec ' . $mistoVJinemTextu;
            }
            if ($kdeSePouziva !== null) {
                $polozky[] = new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::PUVODNI_JESTE_POUZIVANY, $kdeSePouziva, $velikost);
                continue;
            }
            if (!$this->isStejnyObrazek($zdroj, $this->getAbsolutniCesta(self::getCestaAvif($cesta)))) {
                $polozky[] = new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::PUVODNI_BEZ_STEJNEHO_AVIF, '', $velikost);
                continue;
            }
            if ($naostro && !@unlink($zdroj)) {
                $polozky[] = new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::SELHALO, 'originál se nepodařilo smazat', $velikost);
                continue;
            }
            $polozky[] = new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::PUVODNI_SMAZAN, '', $velikost);
        }

        return new VysledekPrevoduObrazku($polozky);
    }

    /**
     * @param array<string, string> $zEmailu cesta => sloupec e-mailu, ve kterém na ni něco odkazuje
     * @param array<string, string> $obsazeneCile cesta AVIF => originál, ze kterého se v tomhle běhu dělá
     */
    private function zpracuj(string $cesta, bool $odkazujeKeStazeni, array $zEmailu, array $obsazeneCile, bool $naostro): PolozkaPrevoduObrazku
    {
        if ($this->isSpravovanyJinde($cesta)) {
            return new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::MIMO_ROZSAH);
        }
        if ($odkazujeKeStazeni) {
            return new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::ODKAZ_KE_STAZENI);
        }
        if ($this->maVicFotekUzivatele($cesta)) {
            return new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::NELZE_ZPRACOVAT, 'uživatel má víc souborů fotky, nech jen jeden');
        }
        if (isset($zEmailu[$cesta]) || isset($zEmailu[self::getCestaAvif($cesta)])) {
            return new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::ODKAZ_Z_EMAILU);
        }
        $cilAvif = self::getCestaAvif($cesta);
        if (isset($obsazeneCile[$cilAvif])) {
            // foto.jpg a foto.png mají jeden cíl; při ostrém běhu by druhý narazil na AVIF prvního, suchý běh to musí říct taky
            return new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::JINY_AVIF_UZ_EXISTUJE, 'stejný název AVIF má ' . $obsazeneCile[$cilAvif]);
        }

        return $this->prevedSoubor($cesta, $naostro);
    }

    private function prevedSoubor(string $cesta, bool $naostro): PolozkaPrevoduObrazku
    {
        $zdroj = $this->getAbsolutniCesta($cesta);
        $cil = $this->getAbsolutniCesta(self::getCestaAvif($cesta));
        $velikostZdroje = (int) @filesize($zdroj);

        // cestu určuje text, který může psát kdokoli s právem na stránky, a zápis by šel za symlinkem ven z adresáře
        if (is_link($zdroj) || !$this->isAdresarUvnitrObsahu(dirname($zdroj))) {
            return new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::NELZE_ZPRACOVAT, 'je symlink nebo leží za symlinkem mimo adresář obsahu', $velikostZdroje);
        }
        $info = @getimagesize($zdroj);
        $mime = is_array($info)
            ? $info['mime']
            : '';
        if (!isset($this->oblast->getKodeky()[$mime])) {
            return new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::NELZE_ZPRACOVAT, 'není ' . $this->oblast->getPopisZdroju(), $velikostZdroje);
        }
        [$sirka, $vyska] = [(int) $info[0], (int) $info[1]];
        if ($sirka <= 0 || $vyska <= 0 || $sirka * $vyska > NahravaniObrazku::MAX_PIXELU) {
            return new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::NELZE_ZPRACOVAT, 'rozměry obrázku jsou nepoužitelné nebo příliš velké', $velikostZdroje);
        }
        if ($this->optimalizace->jeDelsiNezServerPrecte($sirka, $vyska)) {
            return new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::NELZE_ZPRACOVAT, 'server obrázek takových rozměrů nepřečte', $velikostZdroje);
        }
        if ($this->optimalizace->jeAnimovany($zdroj, $mime)) {
            return new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::NELZE_ZPRACOVAT, 'je animovaný', $velikostZdroje);
        }

        if (is_file($cil)) {
            return $this->isStejnyObrazek($zdroj, $cil)
                ? new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::UZ_PREVEDENO, '', $velikostZdroje, (int) @filesize($cil))
                : new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::JINY_AVIF_UZ_EXISTUJE, '', $velikostZdroje);
        }

        // při ostrém běhu vedle cíle, aby se přejmenování nemuselo dělat napříč disky; tečka na začátku skryje soubor ze seznamů
        $docasny = $naostro
            ? dirname($cil) . '/' . self::PREFIX_DOCASNEHO_SOUBORU . bin2hex(random_bytes(8))
            : (string) tempnam(sys_get_temp_dir(), 'prevod-avif-');
        try {
            try {
                $this->optimalizace->zapis($zdroj, $mime, $docasny, NahravaniObrazku::AVIF, $sirka, $vyska);
            } catch (NahravaniObrazkuException $nahravaniObrazkuException) {
                return new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::SELHALO, $nahravaniObrazkuException->getMessage(), $velikostZdroje);
            }
            clearstatcache(true, $docasny);
            $velikostAvif = (int) @filesize($docasny);
            if (!$this->isStejnyObrazek($zdroj, $docasny)) {
                return new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::NEPODOBNY_VYSLEDEK, '', $velikostZdroje, $velikostAvif);
            }
            if ($naostro) {
                $this->prevezmiVlastnikaAPrava($zdroj, $docasny);
                if (!$this->ulozBezPrepsani($docasny, $cil)) {
                    return new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::SELHALO, 'AVIF se nepodařilo uložit: stejnojmenný soubor nebo symlink mezitím vznikl, nebo zápis do adresáře selhal', $velikostZdroje, $velikostAvif);
                }
            }

            return new PolozkaPrevoduObrazku($cesta, StavPrevoduObrazkuEnum::PREVEDENO, '', $velikostZdroje, $velikostAvif);
        } finally {
            if (is_file($docasny)) {
                @unlink($docasny);
            }
        }
    }

    /**
     * Běh trvá minuty a správce mezitím může nahrát stejnojmenný obrázek (nově se ukládá jako AVIF); `rename()` by ho
     * přepsal, pevný odkaz na existující cíl selže. Dočasný soubor se smaže až po něm. Na úložišti bez pevných odkazů
     * se zkusí `rename()` aspoň těsně po kontrole, takže okno pro přepsání jsou milisekundy místo minut.
     */
    private function ulozBezPrepsani(string $docasny, string $cil): bool
    {
        return @link($docasny, $cil) || (!file_exists($cil) && !is_link($cil) && @rename($docasny, $cil));
    }

    /**
     * @param string $adresar adresář, který se čte nebo do kterého se píše
     */
    private function isAdresarUvnitrObsahu(string $adresar): bool
    {
        $koren = realpath($this->adresarObsahu);
        $skutecny = realpath($adresar);

        return $koren !== false
            && $skutecny !== false
            && ($skutecny === $koren || str_starts_with($skutecny, $koren . '/'));
    }

    /**
     * Soubor, který vznikne jako root, by administrace (www-data) nemohla nahradit ani smazat.
     */
    private function prevezmiVlastnikaAPrava(string $vzor, string $soubor): void
    {
        @chmod($soubor, fileperms($vzor) & 0777);
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            @chown($soubor, (int) fileowner($vzor));
            @chgrp($soubor, (int) filegroup($vzor));
        }
    }

    /**
     * Jestli je AVIF skutečně tentýž obrázek jako originál: stejné proporce a barvy po zmenšení originálu na rozměr AVIF
     * (původní zmenšení na `OBRAZEK_MAX_ROZMER_PX` a otočení podle EXIFu se tím vyrovná). Průhlednost se zalévá bílou
     * na obou stranách, takže AVIF, který o ni přišel, se pozná.
     */
    private function isStejnyObrazek(string $puvodni, string $avif): bool
    {
        $info = @getimagesize($puvodni);
        $kodek = is_array($info)
            ? ($this->oblast->getKodeky()[$info['mime']] ?? null)
            : null;
        if ($kodek === null) {
            return false;
        }
        $original = new \Imagick();
        $zAvif = new \Imagick();
        try {
            $original->readImage($kodek . ':' . $puvodni);
            $original->autoOrient();
            $zAvif->readImage('avif:' . $avif);
            ImagickAvif::pripravPoNacteni($zAvif, $avif);
            if ($original->getImageColorspace() === \Imagick::COLORSPACE_CMYK) {
                $original->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
            }

            $sirka = $zAvif->getImageWidth();
            $vyska = $zAvif->getImageHeight();
            if ($sirka <= 0 || $vyska <= 0 || $original->getImageHeight() <= 0) {
                return false;
            }
            $pomerOriginalu = $original->getImageWidth() / $original->getImageHeight();
            if (abs($pomerOriginalu - $sirka / $vyska) > self::MAX_ODCHYLKA_POMERU_STRAN) {
                return false;
            }
            if ($original->getImageWidth() !== $sirka || $original->getImageHeight() !== $vyska) {
                $original->resizeImage($sirka, $vyska, \Imagick::FILTER_LANCZOS, 1);
            }

            [$rozdil, $odchylka] = $this->naBilem($original)->compareImages($this->naBilem($zAvif), \Imagick::METRIC_MEANSQUAREERROR);
            $rozdil->clear();

            return $odchylka <= 10 ** (-self::MIN_PSNR_DB / 10);
        } catch (\ImagickException) {
            return false;
        } finally {
            $original->clear();
            $zAvif->clear();
        }
    }

    private function naBilem(\Imagick $obrazek): \Imagick
    {
        $obrazek->setImageBackgroundColor(new \ImagickPixel('white'));

        return $obrazek->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);
    }

    /**
     * @param array<string, array{vlozeno: int, ke_stazeni: int}> $odkazy
     * @return int obrázky v adresáři, na které žádný text neodkazuje
     */
    private function spoctiNepouzite(array $odkazy): int
    {
        if (!$this->oblast->isZTextu()) {
            return 0;
        }
        $nepouzitych = 0;
        foreach ($this->getObrazky() as $cesta) {
            if (!isset($odkazy[$cesta]) && !isset($odkazy[self::getCestaAvif($cesta)]) && !$this->isSpravovanyJinde($cesta)) {
                ++$nepouzitych;
            }
        }

        return $nepouzitych;
    }

    /**
     * Přepíše příponu odkazů na převedené obrázky ve všech textech. Řádek se zapíše jen tehdy, když se jeho text od
     * načtení nezměnil, jinak se nechá a započítá do `soubezne`.
     *
     * @param array<string, string> $nahrady cesta vůči adresáři obsahu => nová přípona
     * @return array{prepsano: array<string, int>, soubezne: int} bez $naostro se jen spočítá, co by se přepsalo
     */
    private function prepisTexty(array $nahrady, bool $naostro): array
    {
        $prepsano = [];
        $soubezne = 0;
        if ($nahrady === [] || !$this->oblast->isZTextu()) {
            return ['prepsano' => $prepsano, 'soubezne' => $soubezne];
        }
        foreach ($this->pouziti->getTextoveSloupce() as $zdroj) {
            foreach ($this->getRadkyTextu($zdroj['tabulka'], $zdroj['klic'], $zdroj['sloupce']) as $radek) {
                $zmeny = [];
                foreach ($radek['texty'] as $sloupec => $text) {
                    $novyText = OdkazyVTextu::nahradPripony($text, self::PRIPONY_ODKAZU, $nahrady, jenVlozene: true);
                    if ($novyText !== $text) {
                        $zmeny[$sloupec] = ['puvodni' => $text, 'novy' => $novyText];
                    }
                }
                if ($zmeny === []) {
                    continue;
                }
                if ($naostro && !$this->zapisZmenuRadku($zdroj['tabulka'], $zdroj['klic'], $radek['klic'], $zmeny)) {
                    ++$soubezne;
                    continue;
                }
                $prepsano[$zdroj['tabulka']] = ($prepsano[$zdroj['tabulka']] ?? 0) + 1;
            }
        }

        return ['prepsano' => $prepsano, 'soubezne' => $soubezne];
    }

    /**
     * @param array<string, array{puvodni: string, novy: string}> $zmeny
     * @return bool false, když se řádek mezitím změnil (nebo zmizel) a nic se nezapsalo
     */
    private function zapisZmenuRadku(string $tabulka, string $klicSloupec, string $klic, array $zmeny): bool
    {
        $parametry = [0 => $klic];
        $nastaveni = [];
        $podminky = [];
        foreach ($zmeny as $sloupec => $text) {
            $nastaveni[] = dbQi($sloupec) . ' = $' . count($parametry);
            $parametry[] = $text['novy'];
            // BINARY: kolace tabulky bere velká a malá písmena i diakritiku za shodu, ale změna právě těchhle písmen je změna textu
            $podminky[] = 'BINARY ' . dbQi($sloupec) . ' = BINARY $' . count($parametry);
            $parametry[] = $text['puvodni'];
        }

        $vysledek = dbQuery(
            sprintf(
                <<<'SQL'
UPDATE %1$s
SET %2$s
WHERE %3$s = $0
    AND %4$s
SQL,
                dbQi($tabulka),
                implode(', ', $nastaveni),
                dbQi($klicSloupec),
                implode(' AND ', $podminky),
            ),
            $parametry,
        );

        return dbAffectedOrNumRows($vysledek) > 0;
    }

    /**
     * @return array<string, array{vlozeno: int, ke_stazeni: int}> cesta vůči adresáři obsahu, jak ji text píše
     */
    private function getOdkazyVTextech(): array
    {
        $odkazy = [];
        foreach ($this->pouziti->getTextoveSloupce() as $zdroj) {
            foreach ($this->getRadkyTextu($zdroj['tabulka'], $zdroj['klic'], $zdroj['sloupce']) as $radek) {
                foreach ($radek['texty'] as $text) {
                    foreach (OdkazyVTextu::getOdkazy($text, self::PRIPONY_ODKAZU) as $odkaz) {
                        $odkazy[$odkaz->cesta] ??= ['vlozeno' => 0, 'ke_stazeni' => 0];
                        ++$odkazy[$odkaz->cesta][$odkaz->jeVlozeny ? 'vlozeno' : 'ke_stazeni'];
                    }
                }
            }
        }

        return $odkazy;
    }

    /**
     * Textové sloupce, ve kterých se kontroluje, jestli se na obrázek ještě odkazuje: texty e-mailů (@see self::TEXTY_E_MAILU),
     * které převod nepřepisuje, a při mazání všechny textové sloupce databáze, i ty, které převod přepisuje, protože
     * odkaz mohl být zapsaný způsobem, který `PouzitiObrazku` nezná. Binární sloupce se nečtou.
     *
     * @return list<array{tabulka: string, sloupec: string, znakova_sada: string|null, kolace: string|null}>
     */
    private function getSloupceKeKontrole(bool $vsechnyTextoveSloupce): array
    {
        if (!$vsechnyTextoveSloupce) {
            return array_map(
                static fn (array $sloupec): array => ['tabulka' => $sloupec[0], 'sloupec' => $sloupec[1], 'znakova_sada' => null, 'kolace' => null],
                self::TEXTY_E_MAILU,
            );
        }

        return array_map(
            static fn (array $umisteni): array => [
                'tabulka'      => $umisteni['tabulka'],
                'sloupec'      => $umisteni['sloupec'],
                'znakova_sada' => $umisteni['znakova_sada'],
                'kolace'       => $umisteni['kolace'],
            ],
            dbFetchAll(<<<'SQL'
SELECT
    sloupce_schematu.table_name AS tabulka,
    sloupce_schematu.column_name AS sloupec,
    sloupce_schematu.character_set_name AS znakova_sada,
    sloupce_schematu.collation_name AS kolace
FROM information_schema.columns AS sloupce_schematu
INNER JOIN information_schema.tables AS tabulky_schematu
    ON tabulky_schematu.table_schema = sloupce_schematu.table_schema
    AND tabulky_schematu.table_name = sloupce_schematu.table_name
WHERE sloupce_schematu.table_schema = DATABASE()
    AND tabulky_schematu.table_type = 'BASE TABLE'
    AND sloupce_schematu.data_type IN ('char', 'varchar', 'tinytext', 'text', 'mediumtext', 'longtext')
SQL),
        );
    }

    /**
     * Zeptá se databáze, jestli některý ze sloupců odkazuje na některou z cest; platí kolace sloupce (velikost písmen,
     * diakritika) a do paměti se nečte žádný text. Sloupec bez předpony oblasti se přeskočí jedním dotazem. Sloupec
     * s odkazy, ve kterém by vzor nemusel najít diakritiku nebo velká písmena (jiná znaková sada, kolace `_bin`), hlásí
     * chybu, nic se pak nemaže.
     *
     * @see self::getZapisyCesty()
     * @param list<string> $cesty vůči adresáři obsahu
     * @param list<array{tabulka: string, sloupec: string, znakova_sada: string|null, kolace: string|null}> $sloupce
     * @return array<string, string> cesta => `tabulka.sloupec`, ve kterém na ni něco odkazuje
     */
    private function najdiOdkazyVSloupcich(array $cesty, array $sloupce): array
    {
        $nalezene = [];
        foreach ($sloupce as ['tabulka' => $tabulka, 'sloupec' => $sloupec, 'znakova_sada' => $znakovaSada, 'kolace' => $kolace]) {
            $mistoSOdkazy = dbFetchColumn(
                sprintf(
                    <<<'SQL'
SELECT 1
FROM %1$s
WHERE %2$s LIKE $0
LIMIT 1
SQL,
                    dbQi($tabulka),
                    dbQi($sloupec),
                ),
                ['%' . $this->oblast->getPredponaVuciWebu() . '%'],
            );
            if ($mistoSOdkazy === []) {
                continue;
            }
            if (
                $znakovaSada !== null
                && (!in_array(strtolower($znakovaSada), self::ZNAKOVE_SADY_S_DIAKRITIKOU, true) || !str_ends_with(strtolower((string) $kolace), '_ci'))
            ) {
                throw new \RuntimeException(sprintf(
                    'Sloupec %s.%s (znaková sada %s, kolace %s) odkazuje na soubory, ale hledání v něm nemusí najít diakritiku ani jiná velká a malá písmena, nic se nemaže.',
                    $tabulka,
                    $sloupec,
                    $znakovaSada,
                    $kolace,
                ));
            }
            foreach (array_chunk($cesty, self::DAVKA_PRI_KONTROLE_SLOUPCE) as $davka) {
                $vzory = [];
                $priznaky = [];
                foreach ($davka as $poradi => $cesta) {
                    $cestaVuciWebu = $this->oblast->getPredponaVuciWebu() . $cesta;
                    $zapisy = self::getZapisyCesty($cestaVuciWebu);
                    $podminky = [];
                    foreach ($zapisy as $zapis) {
                        $podminky[] = dbQi($sloupec) . ' LIKE $' . count($vzory);
                        $vzory[] = PouzitiObrazku::vzorProLike($zapis);
                    }
                    $priznaky[] = sprintf('MAX(%s) AS pouziti_%d', implode(' OR ', $podminky), $poradi);
                }
                $radek = dbOneLine(
                    sprintf(
                        <<<'SQL'
SELECT %1$s
FROM %2$s
WHERE %3$s LIKE $%4$d
SQL,
                        implode(', ', $priznaky),
                        dbQi($tabulka),
                        dbQi($sloupec),
                        count($vzory),
                    ),
                    [...$vzory, '%' . $this->oblast->getPredponaVuciWebu() . '%'],
                );
                foreach ($davka as $poradi => $cesta) {
                    if ((int) ($radek['pouziti_' . $poradi] ?? 0) === 1) {
                        $nalezene[$cesta] ??= $tabulka . '.' . $sloupec;
                    }
                }
            }
        }

        return $nalezene;
    }

    /**
     * @param list<string> $sloupce
     * @return list<array{klic: string, texty: array<string, string>}>
     */
    private function getRadkyTextu(string $tabulka, string $klic, array $sloupce): array
    {
        $podminky = implode(' OR ', array_map(
            static fn (string $sloupec): string => dbQi($sloupec) . ' LIKE $0',
            $sloupce,
        ));
        $radky = dbFetchAll(
            sprintf(
                <<<'SQL'
SELECT %1$s, %2$s
FROM %3$s
WHERE %4$s
SQL,
                dbQi($klic),
                implode(', ', array_map(dbQi(...), $sloupce)),
                dbQi($tabulka),
                $podminky,
            ),
            ['%soubory/obsah/%'],
        );

        return array_map(
            static function (array $radek) use ($klic, $sloupce): array {
                $texty = [];
                foreach ($sloupce as $sloupec) {
                    $texty[$sloupec] = (string) $radek[$sloupec];
                }

                return ['klic' => (string) $radek[$klic], 'texty' => $texty];
            },
            $radky,
        );
    }

    /**
     * Soubory, na které míří odkaz v textu: sám zdroj (JPEG, PNG), nebo originály k odkazu na AVIF, který tu ještě není
     * (databáze zkopírovaná z prostředí, kde už se převádělo). Prázdný seznam: odkaz vede na soubor, který neexistuje.
     *
     * @return list<string> cesty vůči adresáři obsahu
     */
    private function getOriginalyKOdkazu(string $cestaZTextu): array
    {
        $pripona = strtolower(pathinfo($cestaZTextu, PATHINFO_EXTENSION));
        if (in_array($pripona, $this->oblast->getPripony(), true)) {
            return is_file($this->getAbsolutniCesta($cestaZTextu))
                ? [$cestaZTextu]
                : [];
        }
        $podadresar = dirname($cestaZTextu);
        $nazev = pathinfo($cestaZTextu, PATHINFO_FILENAME);
        $originaly = [];
        foreach (@scandir(dirname($this->getAbsolutniCesta($cestaZTextu))) ?: [] as $soubor) {
            if (
                pathinfo($soubor, PATHINFO_FILENAME) === $nazev
                && in_array(strtolower(pathinfo($soubor, PATHINFO_EXTENSION)), $this->oblast->getPripony(), true)
            ) {
                $originaly[] = $podadresar === '.'
                    ? $soubor
                    : $podadresar . '/' . $soubor;
            }
        }

        return $originaly;
    }

    private function isAvif(string $cesta): bool
    {
        return strtolower(pathinfo($cesta, PATHINFO_EXTENSION)) === self::PRIPONA_CILE;
    }

    /**
     * @param array{vlozeno: int, ke_stazeni: int} $souhrn
     * @param array{vlozeno: int, ke_stazeni: int} $dalsi
     * @return array{vlozeno: int, ke_stazeni: int}
     */
    private function sloucOdkazy(array $souhrn, array $dalsi): array
    {
        return [
            'vlozeno'    => $souhrn['vlozeno'] + $dalsi['vlozeno'],
            'ke_stazeni' => $souhrn['ke_stazeni'] + $dalsi['ke_stazeni'],
        ];
    }

    private function isVRozsahuBehu(string $cesta): bool
    {
        return $this->podslozka === null
            || str_starts_with($cesta, trim($this->podslozka, '/') . '/');
    }

    /**
     * AVIF z jednoho z několika souborů téhož uživatele by zakryl ten, který web ukazoval (první podle abecedy), takže
     * se žádný nepřevádí ani nemaže.
     *
     * @see \Uzivatel::fotka()
     */
    private function maVicFotekUzivatele(string $cesta): bool
    {
        if ($this->oblast->isZTextu()) {
            return false;
        }
        $uzivatel = pathinfo($cesta, PATHINFO_FILENAME);
        $pocet = 0;
        foreach (@scandir(dirname($this->getAbsolutniCesta($cesta))) ?: [] as $soubor) {
            $pripona = strtolower(pathinfo($soubor, PATHINFO_EXTENSION));
            if (
                pathinfo($soubor, PATHINFO_FILENAME) === $uzivatel
                && $pripona !== self::PRIPONA_CILE
                && in_array($pripona, ZpracovaneObrazky::PRIPONY_FOTEK_UZIVATELU, true)
            ) {
                ++$pocet;
            }
        }

        return $pocet > 1;
    }

    /**
     * Loga a galerii e-shopu spravuje jiný modul, press kit stahují novináři; převod se jich netýká.
     */
    private function isSpravovanyJinde(string $cesta): bool
    {
        return $this->oblast->isZTextu()
            && (ZpracovaneObrazky::jeAdresarJinehoModulu($cesta)
            || in_array(strtolower(explode('/', $cesta)[0]), self::NEPREVADENE_SLOZKY, true));
    }

    /**
     * @return list<string> zdroje v adresáři (nebo v podsložce běhu) jako cesty vůči adresáři obsahu, bez skrytých;
     *                      fotky jen přímo ve složce fotek, kam je `Uzivatel::fotka()` hledá
     */
    private function getObrazky(): array
    {
        $koren = $this->podslozka === null
            ? $this->adresarObsahu
            : $this->adresarObsahu . '/' . trim($this->podslozka, '/');
        // --slozka může být symlink ven z adresáře obsahu, a mazání by pak sahalo na cizí soubory
        if (!is_dir($koren) || !$this->isAdresarUvnitrObsahu($koren)) {
            return [];
        }
        $cesty = [];
        $soubory = $this->oblast->isZTextu()
            ? new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($koren, \FilesystemIterator::SKIP_DOTS))
            : new \FilesystemIterator($koren, \FilesystemIterator::SKIP_DOTS);
        foreach ($soubory as $soubor) {
            $cesta = substr($soubor->getPathname(), strlen($this->adresarObsahu) + 1);
            if (
                $soubor->isFile()
                && !$soubor->isLink()
                && !str_contains('/' . $cesta, '/.')
                && $this->oblast->isNazevZdroje($soubor->getFilename())
            ) {
                $cesty[] = $cesta;
            }
        }
        sort($cesty);

        return $cesty;
    }

    /**
     * Jak se cesta píše do textu: obyčejně, celá zakódovaná pro URL, jen s mezerami jako `%20`, jen s diakritikou
     * zakódovanou a s obojím, tak jak to dělají prohlížeče a editory Markdownu (závorky a čárky zůstávají). Nepozná se
     * HTML entita v názvu (`&amp;`), JSON s `\/` ani jiná směs, to už je na člověku, který mazání spouští.
     *
     * @return list<string>
     */
    private static function getZapisyCesty(string $cestaVuciWebu): array
    {
        $sDiakritikou = (string) preg_replace_callback(
            '~[^\x00-\x7F]+~',
            static fn (array $znaky): string => rawurlencode($znaky[0]),
            $cestaVuciWebu,
        );

        return array_values(array_unique([
            $cestaVuciWebu,
            ZpracovaneObrazky::zakodujCestu($cestaVuciWebu),
            str_replace(' ', '%20', $cestaVuciWebu),
            $sDiakritikou,
            str_replace(' ', '%20', $sDiakritikou),
        ]));
    }

    private function getAbsolutniCesta(string $cestaVuciObsahu): string
    {
        return $this->adresarObsahu . '/' . $cestaVuciObsahu;
    }

    private static function getCestaAvif(string $cesta): string
    {
        return substr($cesta, 0, -strlen(pathinfo($cesta, PATHINFO_EXTENSION))) . self::PRIPONA_CILE;
    }
}
