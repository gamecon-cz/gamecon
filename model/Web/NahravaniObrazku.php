<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Společné nahrávání obrázků z administrace: fotky k medailonkům, obrázky linií, e-shopu a obsahu webu.
 *
 * - Typ pozná z obsahu souboru, ne z přípony od prohlížeče, a příponu podle něj určí sám.
 * - Hlídá velikost souboru i počet pixelů. Malý soubor s obřími rozměry by při výrobě náhledu
 *   (@see \Nahled) zabral gigabajty paměti.
 * - SVG pustí jen tam, kde je povolené, a jen přes kontrolu z @see LogoUpload::validujSvgSoubor().
 * - Zapisuje a maže jen uvnitř svého kořenového adresáře. Nový soubor uloží nejdřív pod dočasným
 *   názvem vedle cíle a teprve pak ho přejmenuje, takže web nikdy nenačte napůl zapsaný obrázek.
 * - Před smazáním (i před nahrazením obrázkem s jinou příponou) zjistí přes @see PouzitiObrazku,
 *   jestli na soubor neodkazuje některý text webu.
 */
final class NahravaniObrazku
{
    public const JPG  = 'image/jpeg';
    public const PNG  = 'image/png';
    public const GIF  = 'image/gif';
    public const WEBP = 'image/webp';
    public const SVG  = 'image/svg+xml';

    public const RASTROVE_FORMATY = [self::JPG, self::PNG, self::GIF, self::WEBP];

    /** Přípona, pod kterou se uloží nově nahraný obrázek */
    private const PRIPONA_PODLE_MIME = [
        self::JPG  => 'jpg',
        self::PNG  => 'png',
        self::GIF  => 'gif',
        self::WEBP => 'webp',
        self::SVG  => 'svg',
    ];
    /** Přípony, pod kterými leží už nahrané obrázky, i ty z doby FTP */
    private const MIME_PODLE_PRIPONY = [
        'jpg'  => self::JPG,
        'jpeg' => self::JPG,
        'png'  => self::PNG,
        'gif'  => self::GIF,
        'webp' => self::WEBP,
        'svg'  => self::SVG,
    ];
    private const NAZVY_FORMATU = [
        self::JPG  => 'JPG',
        self::PNG  => 'PNG',
        self::GIF  => 'GIF',
        self::WEBP => 'WebP',
        self::SVG  => 'SVG',
    ];
    /** ext4 dovolí 255 bajtů na název, zbytek je rezerva na příponu */
    private const MAX_DELKA_NAZVU_BAJTU = 150;
    /** Tečka na začátku: dočasný soubor nevypíše žádný seznam obrázků */
    private const PREFIX_DOCASNEHO_SOUBORU = '.nahravani-';

    public static function proFotkyUzivatelu(): self
    {
        return new self(ZpracovaneObrazky::adresarFotekUzivatelu(), new PouzitiObrazku());
    }

    public static function proObrazkyLinii(): self
    {
        return new self(
            ZpracovaneObrazky::adresarObrazkuLinii(),
            new PouzitiObrazku(),
            maxVelikostMb: OBRAZEK_LINIE_MAX_MB,
        );
    }

    public static function proObrazkyEshopu(int $rocnik = ROCNIK): self
    {
        return new self(
            ZpracovaneObrazky::adresarObrazkuEshopu($rocnik),
            new PouzitiObrazku(),
            // galerie v přihlášce ukáže jen to, co umí načíst GD (@see \Obrazek::zUrl), tedy ne WebP
            formaty: [self::JPG, self::PNG, self::GIF],
        );
    }

    public static function proObsahWebu(): self
    {
        return new self(
            ZpracovaneObrazky::adresarObsahu(),
            new PouzitiObrazku(),
            formaty: [...self::RASTROVE_FORMATY, self::SVG],
        );
    }

    /**
     * Požadavek byl větší než post_max_size, takže PHP zahodilo celý formulář i se souborem.
     * Modul by pak nevěděl, že se něco nahrávalo, a nic by neřekl.
     */
    public static function bylPozadavekPrilisVelky(): bool
    {
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || $_POST !== [] || $_FILES !== []) {
            return false;
        }
        $limit = self::bajtyZNastaveniPhp('post_max_size');

        return $limit > 0 && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > $limit;
    }

    public static function zpravaOPrilisVelkemPozadavku(): string
    {
        return sprintf(
            'Obrázek je příliš velký, server přijme nejvýš %s.',
            self::lidskaVelikost(self::bajtyZNastaveniPhp('post_max_size')),
        );
    }

    /**
     * Název souboru (bez přípony) z toho, co zadal organizátor. Výsledek nikdy neobsahuje lomítko,
     * tečku ani jiný znak, se kterým by šlo vylézt z adresáře.
     *
     * @param bool $zachovatDiakritikuAMezery pro e-shop, kde se z názvu souboru skládá popisek v přihlášce;
     *                                        jinak vznikne název vhodný do URL (malá písmena bez diakritiky)
     */
    public static function bezpecnyNazev(string $nazev, bool $zachovatDiakritikuAMezery = false): string
    {
        $nazev = mb_scrub(trim($nazev), 'UTF-8');
        $nazev = (string) preg_replace('~\.(jpe?g|png|gif|webp|svg)$~i', '', $nazev);
        if ($zachovatDiakritikuAMezery) {
            if (class_exists(\Normalizer::class)) {
                $nazev = (string) (\Normalizer::normalize($nazev, \Normalizer::FORM_C) ?: $nazev);
            }
            $nazev = (string) preg_replace('~[^\p{L}\p{N} _-]+~u', ' ', $nazev);
            $nazev = (string) preg_replace('~\s+~u', ' ', $nazev);
        } else {
            $nazev = slugify($nazev);
        }
        $nazev = trim(mb_strcut($nazev, 0, self::MAX_DELKA_NAZVU_BAJTU, 'UTF-8'), ' -_');
        if ($nazev === '') {
            throw new NahravaniObrazkuException('Název obrázku musí obsahovat aspoň jedno písmeno nebo číslici.');
        }

        return $nazev;
    }

    /**
     * MIME typ obrázku podle přípony souboru (i `.jpeg` nebo `.JPG`), null pro jiné soubory.
     */
    public static function mimePodlePripony(string $soubor): ?string
    {
        return self::MIME_PODLE_PRIPONY[mb_strtolower(pathinfo($soubor, PATHINFO_EXTENSION))] ?? null;
    }

    /**
     * @param string $korenovyAdresar mimo něj služba nic nezapíše ani nesmaže
     * @param PouzitiObrazku|null $pouzitiObrazku bez něj se před smazáním nekontroluje, kde je soubor použitý
     * @param int|float|null $maxVelikostMb null znamená OBRAZEK_MAX_MB, skutečný limit může snížit nastavení PHP
     * @param list<string> $formaty povolené MIME typy, viz konstanty této třídy
     */
    public function __construct(
        private readonly string $korenovyAdresar,
        private readonly ?PouzitiObrazku $pouzitiObrazku = null,
        private readonly int|float|null $maxVelikostMb = null,
        private readonly array $formaty = self::RASTROVE_FORMATY,
        private readonly int $maxPixelu = 50_000_000,
    ) {
    }

    public function maxVelikostBajtu(): int
    {
        $limit = (int) round(($this->maxVelikostMb ?? OBRAZEK_MAX_MB) * 1024 * 1024);
        foreach (['upload_max_filesize', 'post_max_size'] as $nastaveniPhp) {
            $limitPhp = self::bajtyZNastaveniPhp($nastaveniPhp);
            if ($limitPhp > 0) {
                $limit = min($limit, $limitPhp);
            }
        }

        return $limit;
    }

    /** Například „JPG, PNG, GIF nebo WebP, nejvýš 5 MB“ */
    public function popisLimitu(): string
    {
        return $this->popisFormatu() . ', nejvýš ' . self::lidskaVelikost($this->maxVelikostBajtu());
    }

    /**
     * @return list<string> povolené MIME typy
     */
    public function formaty(): array
    {
        return $this->formaty;
    }

    /** Hodnota atributu accept pro <input type="file"> */
    public function accept(): string
    {
        return implode(',', $this->formaty);
    }

    /**
     * @param mixed $polozkaFiles položka z $_FILES, například $_FILES['fotka'] ?? null
     */
    public function overNahranySoubor(mixed $polozkaFiles): NahranyObrazek
    {
        $kodChyby = is_array($polozkaFiles) ? ($polozkaFiles['error'] ?? null) : null;
        if (!is_int($kodChyby) || $kodChyby === UPLOAD_ERR_NO_FILE) {
            throw new NahravaniObrazkuException('Není vybraný žádný soubor.');
        }
        if ($kodChyby === UPLOAD_ERR_INI_SIZE || $kodChyby === UPLOAD_ERR_FORM_SIZE) {
            throw $this->chybaVelikosti();
        }
        if ($kodChyby !== UPLOAD_ERR_OK) {
            throw new NahravaniObrazkuException(
                sprintf('Soubor se nepodařilo nahrát (chyba %d), zkus to prosím znovu.', $kodChyby),
            );
        }
        $cesta = (string) ($polozkaFiles['tmp_name'] ?? '');
        if ($cesta === '' || !is_uploaded_file($cesta)) {
            throw new NahravaniObrazkuException('Soubor se nepodařilo nahrát, zkus to prosím znovu.');
        }

        return $this->over($cesta, (string) ($polozkaFiles['name'] ?? ''), true);
    }

    /**
     * Soubor, který už leží na disku (skripty, testy). Při uložení se zkopíruje, originál zůstane.
     */
    public function overSoubor(string $cesta, ?string $puvodniNazev = null): NahranyObrazek
    {
        if (!is_file($cesta)) {
            throw new NahravaniObrazkuException('Soubor neexistuje.');
        }

        return $this->over($cesta, $puvodniNazev ?? basename($cesta), false);
    }

    /**
     * Uloží obrázek jako jediný obrázek daného názvu v adresáři (fotka uživatele, obrázek linie,
     * obrázek e-shopu). Stejný název se stejnou příponou přepíše, s jinou příponou smaže.
     *
     * @return string absolutní cesta k uloženému souboru
     */
    public function ulozJakoJediny(NahranyObrazek $obrazek, string $relativniAdresar, string $nazevBezPripony): string
    {
        $this->overNazevSouboru($nazevBezPripony);
        $adresar = $this->adresarUvnitrKorene($relativniAdresar, true);
        $cil = $adresar . '/' . $nazevBezPripony . '.' . $obrazek->pripona;
        $ostatniVarianty = array_values(array_filter(
            $this->variantyObrazku($adresar, $nazevBezPripony),
            static fn (string $varianta): bool => $varianta !== $cil,
        ));
        foreach ($ostatniVarianty as $varianta) {
            $this->overZeNeniPouzity(
                $varianta,
                sprintf(
                    'Nahraj ho prosím znovu ve formátu %s, nebo nejdřív uprav odkaz.',
                    self::NAZVY_FORMATU[self::mimePodlePripony($varianta)] ?? '',
                ),
            );
        }

        $docasny = $this->presunDoDocasnehoSouboru($obrazek, $adresar);
        try {
            foreach ($ostatniVarianty as $varianta) {
                if (!@unlink($varianta)) {
                    throw new NahravaniObrazkuException(
                        sprintf('Nepodařilo se odstranit starší obrázek %s.', basename($varianta)),
                    );
                }
            }
            $this->presunNaMisto($docasny, $cil);
        } finally {
            if (is_file($docasny)) {
                @unlink($docasny);
            }
        }

        return $cil;
    }

    /**
     * Uloží nový obrázek, existující soubor stejného názvu nepřepíše.
     *
     * @return string absolutní cesta k uloženému souboru
     */
    public function ulozNovy(NahranyObrazek $obrazek, string $relativniAdresar, string $nazevBezPripony): string
    {
        $this->overNazevSouboru($nazevBezPripony);
        $adresar = $this->adresarUvnitrKorene($relativniAdresar, true);
        $cil = $adresar . '/' . $nazevBezPripony . '.' . $obrazek->pripona;
        if (file_exists($cil)) {
            throw new NahravaniObrazkuException(sprintf(
                'Obrázek %s už existuje. Zvol jiný název, nebo ho nahraď u něj v seznamu.',
                basename($cil),
            ));
        }
        $this->ulozPresDocasny($obrazek, $adresar, $cil);

        return $cil;
    }

    /**
     * Nahradí obrázek na dané cestě. Soubor nemusí existovat, text na něj může odkazovat dřív, než je nahraný.
     * Formát musí odpovídat příponě, jinak by se pod odkazem na `.jpg` schovávalo PNG.
     *
     * @return string absolutní cesta k uloženému souboru
     */
    public function nahrad(NahranyObrazek $obrazek, string $relativniCesta): string
    {
        $cil = $this->cestaUvnitrKorene($relativniCesta, true);
        if (is_dir($cil)) {
            throw new NahravaniObrazkuException('Na tomhle místě je adresář, ne obrázek.');
        }
        $mimeCile = self::mimePodlePripony($cil);
        if ($mimeCile === null || !in_array($mimeCile, $this->formaty, true)) {
            throw new NahravaniObrazkuException(
                sprintf('%s není obrázek, který by tu šlo nahradit.', basename($cil)),
            );
        }
        if ($mimeCile !== $obrazek->mime) {
            throw new NahravaniObrazkuException(sprintf(
                '%s je ve formátu %s a nahraný soubor ve formátu %s. Nahraj prosím obrázek ve stejném formátu, jinak by přestaly fungovat odkazy na něj.',
                basename($cil),
                self::NAZVY_FORMATU[$mimeCile],
                self::NAZVY_FORMATU[$obrazek->mime],
            ));
        }
        $this->ulozPresDocasny($obrazek, dirname($cil), $cil);

        return $cil;
    }

    public function smaz(string $relativniCesta): void
    {
        $cesta = $this->cestaUvnitrKorene($relativniCesta, false);
        if (!is_file($cesta) || self::mimePodlePripony($cesta) === null) {
            throw new NahravaniObrazkuException('Obrázek neexistuje.');
        }
        $this->overZeNeniPouzity($cesta, 'Nejdřív prosím odkaz odstraň.');
        if (!@unlink($cesta)) {
            throw new NahravaniObrazkuException(sprintf('Obrázek %s se nepodařilo smazat.', basename($cesta)));
        }
    }

    /**
     * Smaže obrázek daného názvu se všemi příponami.
     *
     * @return bool jestli nějaký obrázek byl
     */
    public function smazVsechnyVarianty(string $relativniAdresar, string $nazevBezPripony): bool
    {
        $this->overNazevSouboru($nazevBezPripony);
        if (!$this->existujeAdresar($relativniAdresar)) {
            return false;
        }
        $varianty = $this->variantyObrazku($this->adresarUvnitrKorene($relativniAdresar, false), $nazevBezPripony);
        foreach ($varianty as $varianta) {
            $this->overZeNeniPouzity($varianta, 'Nejdřív prosím odkaz odstraň.');
        }
        foreach ($varianty as $varianta) {
            if (!@unlink($varianta)) {
                throw new NahravaniObrazkuException(
                    sprintf('Obrázek %s se nepodařilo smazat.', basename($varianta)),
                );
            }
        }

        return $varianty !== [];
    }

    /**
     * Názvy obrázků přímo v adresáři (bez podadresářů a skrytých souborů), v pořadí podle názvu.
     *
     * @return list<string>
     */
    public function obrazkyVAdresari(string $relativniAdresar = ''): array
    {
        if (!$this->existujeAdresar($relativniAdresar)) {
            return [];
        }
        $adresar = $this->adresarUvnitrKorene($relativniAdresar, false);
        $obrazky = [];
        foreach (scandir($adresar) ?: [] as $soubor) {
            if (!str_starts_with($soubor, '.')
                && self::mimePodlePripony($soubor) !== null
                && is_file($adresar . '/' . $soubor)
            ) {
                $obrazky[] = $soubor;
            }
        }

        return $obrazky;
    }

    /**
     * Absolutní cesta k souboru uvnitř kořenového adresáře. Odmítne `..`, skryté soubory
     * a adresáře, které přes symlink vedou ven. Soubor existovat nemusí, adresář ano.
     */
    public function cestaKSouboru(string $relativniCesta): string
    {
        return $this->cestaUvnitrKorene($relativniCesta, false);
    }

    /**
     * Kde texty webu odkazují na soubor; bez @see PouzitiObrazku vždy prázdné.
     *
     * @return list<array{popis: string, url: string}>
     */
    public function kdeJePouzity(string $absolutniCesta): array
    {
        return $this->pouzitiObrazku?->kdeJePouzity($absolutniCesta) ?? [];
    }

    private static function bajtyZNastaveniPhp(string $nazev): int
    {
        $hodnota = trim((string) ini_get($nazev));

        return $hodnota === ''
            ? 0
            : (int) @ini_parse_quantity($hodnota);
    }

    private static function lidskaVelikost(int $bajty): string
    {
        if ($bajty < 1024 * 1024) {
            return ceil($bajty / 1024) . ' kB';
        }

        return rtrim(rtrim(number_format($bajty / 1024 / 1024, 1, ',', ''), '0'), ',') . ' MB';
    }

    /**
     * @return list<string>
     */
    private static function segmentyCesty(string $cesta): array
    {
        if (str_contains($cesta, "\0")) {
            throw new NahravaniObrazkuException('Neplatná cesta k souboru.');
        }
        $segmenty = [];
        foreach (explode('/', str_replace('\\', '/', $cesta)) as $segment) {
            if ($segment === '') {
                continue;
            }
            // pokrývá `.`, `..` i skryté soubory jako `.htaccess`
            if (str_starts_with($segment, '.')) {
                throw new NahravaniObrazkuException('Neplatná cesta k souboru.');
            }
            $segmenty[] = $segment;
        }

        return $segmenty;
    }

    private function over(string $cesta, string $puvodniNazev, bool $zHttpUploadu): NahranyObrazek
    {
        clearstatcache(true, $cesta);
        $velikost = (int) @filesize($cesta);
        if ($velikost <= 0) {
            throw new NahravaniObrazkuException('Soubor je prázdný.');
        }
        if ($velikost > $this->maxVelikostBajtu()) {
            throw $this->chybaVelikosti();
        }

        $info = @getimagesize($cesta);
        if (is_array($info)) {
            $mime = $info['mime'];
            if (!in_array($mime, $this->formaty, true)) {
                $nazevFormatu = self::NAZVY_FORMATU[$mime]
                    ?? mb_strtoupper((string) image_type_to_extension((int) $info[2], false));
                throw new NahravaniObrazkuException(
                    sprintf('Formát %s tu nejde použít, nahraj prosím %s.', $nazevFormatu, $this->popisFormatu())
                    . $this->tipProHeic($puvodniNazev),
                );
            }
            $sirka = (int) $info[0];
            $vyska = (int) $info[1];
            if ($sirka <= 0 || $vyska <= 0) {
                throw new NahravaniObrazkuException('Obrázek nemá platné rozměry, nejspíš je poškozený.');
            }
            if ($sirka * $vyska > $this->maxPixelu) {
                throw new NahravaniObrazkuException(sprintf(
                    'Obrázek má %d × %d px, to je na web zbytečně moc. Zmenši ho prosím nejvýš na %s Mpx.',
                    $sirka,
                    $vyska,
                    rtrim(rtrim(number_format($this->maxPixelu / 1_000_000, 1, ',', ''), '0'), ','),
                ));
            }

            return new NahranyObrazek(
                cesta: $cesta,
                puvodniNazev: $puvodniNazev,
                mime: $mime,
                pripona: self::PRIPONA_PODLE_MIME[$mime],
                sirka: $sirka,
                vyska: $vyska,
                velikostBajtu: $velikost,
                zHttpUploadu: $zHttpUploadu,
            );
        }

        if (in_array(self::SVG, $this->formaty, true) && $this->vypadaJakoSvg($cesta, $puvodniNazev)) {
            $chybaSvg = LogoUpload::validujSvgSoubor($cesta);
            if ($chybaSvg !== null) {
                throw new NahravaniObrazkuException($chybaSvg);
            }

            return new NahranyObrazek(
                cesta: $cesta,
                puvodniNazev: $puvodniNazev,
                mime: self::SVG,
                pripona: self::PRIPONA_PODLE_MIME[self::SVG],
                sirka: null,
                vyska: null,
                velikostBajtu: $velikost,
                zHttpUploadu: $zHttpUploadu,
            );
        }

        throw new NahravaniObrazkuException(
            sprintf('Soubor není obrázek, nahraj prosím %s.', $this->popisFormatu())
            . $this->tipProHeic($puvodniNazev),
        );
    }

    /**
     * Novější PHP pozná HEIF jako obrázek, starší vůbec, rada je potřeba v obou případech
     */
    private function tipProHeic(string $puvodniNazev): string
    {
        return preg_match('~\.hei[cf]$~i', $puvodniNazev)
            ? ' Fotku z iPhonu (HEIC) je potřeba nejdřív uložit jako JPG.'
            : '';
    }

    private function vypadaJakoSvg(string $cesta, string $puvodniNazev): bool
    {
        if (preg_match('~\.svgz?$~i', $puvodniNazev)) {
            return true;
        }

        return stripos((string) @file_get_contents($cesta, false, null, 0, 4096), '<svg') !== false;
    }

    private function popisFormatu(): string
    {
        $nazvy = array_map(static fn (string $mime): string => self::NAZVY_FORMATU[$mime], $this->formaty);
        $posledni = array_pop($nazvy);

        return $nazvy === []
            ? (string) $posledni
            : implode(', ', $nazvy) . ' nebo ' . $posledni;
    }

    private function chybaVelikosti(): NahravaniObrazkuException
    {
        return new NahravaniObrazkuException(
            sprintf('Obrázek je příliš velký, maximum je %s.', self::lidskaVelikost($this->maxVelikostBajtu())),
        );
    }

    private function overNazevSouboru(string $nazev): void
    {
        if ($nazev === ''
            || str_starts_with($nazev, '.')
            || strpbrk($nazev, "/\\\0") !== false
            || strlen($nazev) > self::MAX_DELKA_NAZVU_BAJTU
        ) {
            throw new NahravaniObrazkuException('Neplatný název obrázku.');
        }
    }

    private function existujeAdresar(string $relativniAdresar): bool
    {
        $segmenty = self::segmentyCesty($relativniAdresar);

        return is_dir(rtrim($this->korenovyAdresar, '/') . ($segmenty === [] ? '' : '/' . implode('/', $segmenty)));
    }

    private function cestaUvnitrKorene(string $relativniCesta, bool $vytvoritAdresar): string
    {
        $segmenty = self::segmentyCesty($relativniCesta);
        if ($segmenty === []) {
            throw new NahravaniObrazkuException('Chybí název souboru.');
        }
        $nazevSouboru = array_pop($segmenty);

        return $this->adresarUvnitrKorene(implode('/', $segmenty), $vytvoritAdresar) . '/' . $nazevSouboru;
    }

    /**
     * @return string skutečná cesta (bez symlinků) k adresáři uvnitř kořene
     */
    private function adresarUvnitrKorene(string $relativniAdresar, bool $vytvorit): string
    {
        $segmenty = self::segmentyCesty($relativniAdresar);
        $koren = rtrim($this->korenovyAdresar, '/');
        $cesta = $segmenty === [] ? $koren : $koren . '/' . implode('/', $segmenty);
        if ($vytvorit && !is_dir($cesta)) {
            if (is_dir($koren)) {
                // mkdir přes symlink uvnitř kořene by tvořil adresáře jinde, proto nejdřív ověříme
                // nejbližší existující nadřazený adresář
                $existujici = $cesta;
                while (!file_exists($existujici)) {
                    $existujici = dirname($existujici);
                }
                $this->overZeJeUvnitrKorene($existujici);
            }
            try {
                (new Filesystem())->mkdir($cesta, 0775);
            } catch (IOException) {
                throw new NahravaniObrazkuException('Nepodařilo se vytvořit adresář pro obrázky.');
            }
        }
        if (!is_dir($cesta)) {
            throw new NahravaniObrazkuException('Adresář pro obrázky neexistuje.');
        }

        return $this->overZeJeUvnitrKorene($cesta);
    }

    private function overZeJeUvnitrKorene(string $cesta): string
    {
        $realnaCesta = realpath($cesta);
        $realnyKoren = realpath($this->korenovyAdresar);
        if ($realnaCesta === false
            || $realnyKoren === false
            || ($realnaCesta !== $realnyKoren && !str_starts_with($realnaCesta, rtrim($realnyKoren, '/') . '/'))
        ) {
            throw new NahravaniObrazkuException('Neplatná cesta k souboru.');
        }

        return $realnaCesta;
    }

    /**
     * Obrázky se stejným názvem a libovolnou známou příponou (i `.JPG` z doby FTP), v pořadí podle názvu
     * jako `glob()` v @see \Uzivatel::fotka()
     *
     * @return list<string> absolutní cesty
     */
    private function variantyObrazku(string $adresar, string $nazevBezPripony): array
    {
        $varianty = [];
        foreach (scandir($adresar) ?: [] as $soubor) {
            if ($soubor === $nazevBezPripony . '.' . pathinfo($soubor, PATHINFO_EXTENSION)
                && self::mimePodlePripony($soubor) !== null
                && is_file($adresar . '/' . $soubor)
            ) {
                $varianty[] = $adresar . '/' . $soubor;
            }
        }

        return $varianty;
    }

    private function overZeNeniPouzity(string $cesta, string $coDal): void
    {
        $pouziti = $this->kdeJePouzity($cesta);
        if ($pouziti !== []) {
            throw new NahravaniObrazkuException(sprintf(
                'Na obrázek %s odkazuje %s. %s',
                basename($cesta),
                PouzitiObrazku::popis($pouziti),
                $coDal,
            ));
        }
    }

    private function ulozPresDocasny(NahranyObrazek $obrazek, string $adresar, string $cil): void
    {
        $docasny = $this->presunDoDocasnehoSouboru($obrazek, $adresar);
        try {
            $this->presunNaMisto($docasny, $cil);
        } finally {
            if (is_file($docasny)) {
                @unlink($docasny);
            }
        }
    }

    private function presunDoDocasnehoSouboru(NahranyObrazek $obrazek, string $adresar): string
    {
        $docasny = $adresar . '/' . self::PREFIX_DOCASNEHO_SOUBORU . bin2hex(random_bytes(8));
        $presunuto = $obrazek->zHttpUploadu
            ? @move_uploaded_file($obrazek->cesta, $docasny)
            : @copy($obrazek->cesta, $docasny);
        if (!$presunuto) {
            throw new NahravaniObrazkuException('Obrázek se nepodařilo uložit.');
        }

        return $docasny;
    }

    private function presunNaMisto(string $docasny, string $cil): void
    {
        if (!@rename($docasny, $cil)) {
            throw new NahravaniObrazkuException('Obrázek se nepodařilo uložit.');
        }
        clearstatcache(true, $cil);
    }
}
