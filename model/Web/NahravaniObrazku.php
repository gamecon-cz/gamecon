<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Společné nahrávání obrázků a souborů z administrace: fotky medailonků, obrázky linií, e-shopu a obsahu webu
 * a PDF, zvuk, archivy a tabulky ke stažení. Typ pozná z obsahu souboru, zapisuje a maže jen uvnitř svého kořene
 * a před smazáním zjistí přes @see PouzitiObrazku, jestli na soubor neodkazuje text webu nebo kód.
 * Pravidla: docs/generated/nahravani-obrazku.md
 */
final class NahravaniObrazku
{
    public const JPG  = 'image/jpeg';
    public const PNG  = 'image/png';
    public const GIF  = 'image/gif';
    public const WEBP = 'image/webp';
    public const AVIF = 'image/avif';
    public const SVG  = 'image/svg+xml';
    public const PDF  = 'application/pdf';
    public const MP3  = 'audio/mpeg';
    public const OGG  = 'audio/ogg';
    public const WAV  = 'audio/wav';
    public const M4A  = 'audio/mp4';
    public const ZIP  = 'application/zip';
    public const XLSX = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    public const DOCX = 'application/vnd.openxmlformats-officedocument.wordprocessingml.document';
    public const ODT  = 'application/vnd.oasis.opendocument.text';
    public const ODS  = 'application/vnd.oasis.opendocument.spreadsheet';

    public const RASTROVE_FORMATY = [self::JPG, self::PNG, self::GIF, self::WEBP, self::AVIF];
    /** Soubory, které nejsou obrázky; služba je zná jen tehdy, když je mezi povolenými formáty aspoň jeden z nich */
    public const DOKUMENTY = [
        self::PDF,
        self::MP3,
        self::OGG,
        self::WAV,
        self::M4A,
        self::ZIP,
        self::XLSX,
        self::DOCX,
        self::ODT,
        self::ODS,
    ];

    private const PRIPONA_PODLE_MIME = [
        self::JPG  => 'jpg',
        self::PNG  => 'png',
        self::GIF  => 'gif',
        self::WEBP => 'webp',
        self::AVIF => 'avif',
        self::SVG  => 'svg',
        self::PDF  => 'pdf',
        self::MP3  => 'mp3',
        self::OGG  => 'ogg',
        self::WAV  => 'wav',
        self::M4A  => 'm4a',
        self::ZIP  => 'zip',
        self::XLSX => 'xlsx',
        self::DOCX => 'docx',
        self::ODT  => 'odt',
        self::ODS  => 'ods',
    ];
    /** Přípony, pod kterými leží už nahrané soubory, i ty z doby FTP */
    private const MIME_PODLE_PRIPONY = [
        'jpg'  => self::JPG,
        'jpeg' => self::JPG,
        'png'  => self::PNG,
        'gif'  => self::GIF,
        'webp' => self::WEBP,
        'avif' => self::AVIF,
        'svg'  => self::SVG,
        'pdf'  => self::PDF,
        'mp3'  => self::MP3,
        'ogg'  => self::OGG,
        'wav'  => self::WAV,
        'm4a'  => self::M4A,
        'zip'  => self::ZIP,
        'xlsx' => self::XLSX,
        'docx' => self::DOCX,
        'odt'  => self::ODT,
        'ods'  => self::ODS,
    ];
    private const NAZVY_FORMATU = [
        self::JPG  => 'JPG',
        self::PNG  => 'PNG',
        self::GIF  => 'GIF',
        self::WEBP => 'WebP',
        self::AVIF => 'AVIF',
        self::SVG  => 'SVG',
        self::PDF  => 'PDF',
        self::MP3  => 'MP3',
        self::OGG  => 'OGG',
        self::WAV  => 'WAV',
        self::M4A  => 'M4A',
        self::ZIP  => 'ZIP',
        self::XLSX => 'XLSX',
        self::DOCX => 'DOCX',
        self::ODT  => 'ODT',
        self::ODS  => 'ODS',
    ];
    /** Formáty, do kterých se obrázek při nahrazení souboru převede: umí je zapsat OptimalizaceObrazku */
    private const PREVODITELNE_CILE = [self::JPG, self::PNG, self::WEBP, self::AVIF];
    /** Formáty, ze kterých se obrázek při nahrazení souboru převádí (GIF jen bez animace, AVIF jen kde ho ImageMagick zvládá) */
    private const PREVODITELNE_ZDROJE = [self::JPG, self::PNG, self::WEBP, self::AVIF, self::GIF];
    /** ext4 dovolí 255 bajtů na název, zbytek je rezerva na příponu */
    private const MAX_DELKA_NAZVU_BAJTU = 150;
    /** Výchozí strop pixelů; obrázek, který se při uložení zmenší, se jen jednou dekóduje, takže mu stačí vždy aspoň tenhle */
    public const MAX_PIXELU = 50_000_000;
    /** Tečka na začátku: dočasný soubor nevypíše žádný seznam obrázků */
    private const PREFIX_DOCASNEHO_SOUBORU = '.nahravani-';

    private readonly bool $pracujeSDokumenty;

    public static function proFotkyUzivatelu(): self
    {
        return new self(
            ZpracovaneObrazky::adresarFotekUzivatelu(),
            new PouzitiObrazku(),
            formaty: self::getRastroveFormatyProNahledy(),
            optimalizace: new OptimalizaceObrazku(),
        );
    }

    public static function proFotkyKostymu(): self
    {
        return new self(
            ZpracovaneObrazky::adresarFotekKostymu(),
            new PouzitiObrazku(),
            formaty: self::getRastroveFormatyProNahledy(),
            optimalizace: new OptimalizaceObrazku(),
        );
    }

    public static function proObrazkyLinii(): self
    {
        return new self(
            ZpracovaneObrazky::adresarObrazkuLinii(),
            new PouzitiObrazku(),
            maxVelikostMb: OBRAZEK_LINIE_MAX_MB,
            formaty: self::getRastroveFormatyProNahledy(),
            optimalizace: new OptimalizaceObrazku(),
        );
    }

    /**
     * Obrázky, které web zobrazuje přes @see \Nahled. AVIF mezi nimi je jen tam, kde ImageMagick AVIF zapíše i přečte
     * správně i s průhledností; ImageMagick 6.9.11 (dnešní ostrá) by z AVIF s průhledností udělal náhled s černým pozadím.
     *
     * @return list<string>
     */
    private static function getRastroveFormatyProNahledy(): array
    {
        return ImagickAvif::isAvifWritable(true)
            ? self::RASTROVE_FORMATY
            : array_values(array_diff(self::RASTROVE_FORMATY, [self::AVIF]));
    }

    public static function proObrazkyEshopu(int $rocnik = ROCNIK): self
    {
        return new self(
            ZpracovaneObrazky::adresarObrazkuEshopu($rocnik),
            new PouzitiObrazku(),
            // galerie v přihlášce ukáže jen to, co projde @see \Obrazek::jeToPodporovanyObrazek(), tedy ne WebP ani AVIF
            formaty: [self::JPG, self::PNG, self::GIF],
            // GIF se neupravuje a přihláška převádí každý obrázek celý do AVIF (nebo WebP); ostatní se při uložení zmenší
            maxPixelu: 12_000_000,
            optimalizace: new OptimalizaceObrazku(),
        );
    }

    public static function proObsahWebu(): self
    {
        return new self(
            ZpracovaneObrazky::adresarObsahu(),
            new PouzitiObrazku(),
            formaty: [...self::RASTROVE_FORMATY, self::SVG],
            optimalizace: new OptimalizaceObrazku(),
        );
    }

    /**
     * Soubory ke stažení v textech stránek a novinek. Kořen je celý `soubory/obsah`, aby šel nahradit soubor, na který
     * text odkazuje odkudkoli, ne jen ten z adresáře stránky.
     */
    public static function proDokumentyObsahu(bool $poCastech = false): self
    {
        return new self(
            ZpracovaneObrazky::adresarObsahu(),
            new PouzitiObrazku(),
            maxVelikostMb: SOUBOR_MAX_MB,
            formaty: self::DOKUMENTY,
            poCastech: $poCastech,
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
            'Nahrávaný soubor je příliš velký, server přijme nejvýš %s.',
            self::lidskaVelikost(self::bajtyZNastaveniPhp('post_max_size')),
        );
    }

    /**
     * Pro začátek modulu s uploadem: příliš velký požadavek ohlásí organizátorovi a skončí.
     */
    public static function hlidejPrilisVelkyPozadavek(): void
    {
        if (self::bylPozadavekPrilisVelky()) {
            chyba(self::zpravaOPrilisVelkemPozadavku());
        }
    }

    /**
     * Provede akci modulu. Chybu nahrávání ohlásí organizátorovi a skončí; zpráva výjimky není escapovaná,
     * proto jde vždy přes tuhle metodu.
     *
     * @template T
     * @param callable(): T $akce
     * @return T
     */
    public static function sOhlasenimChyby(callable $akce): mixed
    {
        try {
            return $akce();
        } catch (NahravaniObrazkuException $nahravaniObrazkuException) {
            chyba(htmlspecialchars($nahravaniObrazkuException->getMessage()));

            throw $nahravaniObrazkuException; // nedosažitelné, chyba() skončí skript
        }
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
        $nazev = (string) preg_replace('~\.(jpe?g|png|gif|webp|avif|svg)$~i', '', $nazev);
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
     * Název souboru (bez přípony) z toho, co zadal organizátor: malá písmena bez diakritiky, číslice, podtržítko
     * a pomlčka. Podtržítko zůstává, kód webu odkazuje třeba na `souhlas_rodicu_2026.pdf`.
     */
    public static function bezpecnyNazevDokumentu(string $nazev): string
    {
        $nazev = mb_scrub(trim($nazev), 'UTF-8');
        $nazev = (string) preg_replace('~\.(?:' . implode('|', array_keys(self::MIME_PODLE_PRIPONY)) . ')$~i', '', $nazev);
        $nazev = (string) preg_replace('~[^0-9a-z_-]+~', '_', strtolower(removeDiacritics($nazev)));
        $nazev = trim(mb_strcut($nazev, 0, self::MAX_DELKA_NAZVU_BAJTU, 'UTF-8'), '_-');
        if ($nazev === '') {
            throw new NahravaniObrazkuException('Název souboru musí obsahovat aspoň jedno písmeno nebo číslici.');
        }

        return $nazev;
    }

    /**
     * MIME typ souboru podle přípony (i `.jpeg` nebo `.JPG`), null pro neznámé přípony.
     */
    public static function mimePodlePripony(string $soubor): ?string
    {
        return self::MIME_PODLE_PRIPONY[mb_strtolower(pathinfo($soubor, PATHINFO_EXTENSION))] ?? null;
    }

    /**
     * @param list<string> $formaty MIME typy
     * @return list<string> přípony, pod kterými tyto formáty leží (i `jpeg` vedle `jpg`)
     */
    public static function getPriponyFormatu(array $formaty): array
    {
        return array_keys(array_filter(
            self::MIME_PODLE_PRIPONY,
            static fn (string $mime): bool => in_array($mime, $formaty, true),
        ));
    }

    /**
     * @return string|null například „PDF“, null pro neznámou příponu
     */
    public static function nazevFormatuPodlePripony(string $soubor): ?string
    {
        $mime = self::mimePodlePripony($soubor);

        return $mime === null
            ? null
            : self::NAZVY_FORMATU[$mime];
    }

    public static function lidskaVelikost(int $bajty): string
    {
        if ($bajty < 1024 * 1024) {
            return ceil($bajty / 1024) . ' kB';
        }

        return rtrim(rtrim(number_format($bajty / 1024 / 1024, 1, ',', ''), '0'), ',') . ' MB';
    }

    /**
     * @param string $korenovyAdresar mimo něj služba nic nezapíše ani nesmaže
     * @param PouzitiObrazku|null $pouzitiObrazku bez něj se před smazáním nekontroluje, kde je soubor použitý
     * @param int|float|null $maxVelikostMb null znamená OBRAZEK_MAX_MB, skutečný limit může snížit nastavení PHP
     * @param list<string> $formaty povolené MIME typy, viz konstanty této třídy
     * @param int|float|null $maxVelikostObrazkuMb menší limit jen pro obrázky; null je OBRAZEK_MAX_MB, když služba
     *                                             bere i dokumenty, jinak stejný limit jako pro všechno
     * @param OptimalizaceObrazku|null $optimalizace bez ní se obrázky ukládají tak, jak přišly; s ní se JPEG a PNG
     *                                               převádějí do AVIF (když ho formáty povolují) a velké obrázky zmenšují
     * @param bool $poCastech formulář posílá soubor po částech (@see NahravaniPoCastech), takže ho nesvazují limity PHP
     *                        pro jeden požadavek; ty pak platí jen pro soubor, který přišel najednou
     */
    public function __construct(
        private readonly string $korenovyAdresar,
        private readonly ?PouzitiObrazku $pouzitiObrazku = null,
        private readonly int|float|null $maxVelikostMb = null,
        private readonly array $formaty = self::RASTROVE_FORMATY,
        private readonly int $maxPixelu = self::MAX_PIXELU,
        private readonly int|float|null $maxVelikostObrazkuMb = null,
        private readonly ?OptimalizaceObrazku $optimalizace = null,
        private readonly bool $poCastech = false,
    ) {
        $this->pracujeSDokumenty = array_intersect($formaty, self::DOKUMENTY) !== [];
    }

    /**
     * Největší soubor, který služba přijme. Soubor přijatý po částech omezuje jen nastavení aplikace.
     */
    public function maxVelikostBajtu(): int
    {
        $limit = (int) round(($this->maxVelikostMb ?? OBRAZEK_MAX_MB) * 1024 * 1024);

        return $this->poCastech
            ? $limit
            : min($limit, $this->maxVelikostJednohoPozadavkuBajtu() ?: $limit);
    }

    /**
     * Co PHP přijme v jednom požadavku, tedy menší z `upload_max_filesize` a `post_max_size`; 0, když ho nic neomezuje.
     */
    private function maxVelikostJednohoPozadavkuBajtu(): int
    {
        $limity = array_filter([
            self::bajtyZNastaveniPhp('upload_max_filesize'),
            self::bajtyZNastaveniPhp('post_max_size'),
        ], static fn (int $limit): bool => $limit > 0);

        return $limity === []
            ? 0
            : min($limity);
    }

    /** Například „JPG, PNG, GIF, WebP nebo AVIF, nejvýš 5 MB; JPG a PNG se zmenší na nejvýš 1920 px a uloží jako AVIF“ */
    public function popisLimitu(): string
    {
        $popis = $this->popisFormatu() . ', nejvýš ' . self::lidskaVelikost($this->maxVelikostBajtu());
        $uprava = $this->popisUpravy();

        return $uprava === ''
            ? $popis
            : $popis . '; ' . $uprava;
    }

    /**
     * @return list<string> povolené MIME typy
     */
    public function formaty(): array
    {
        return $this->formaty;
    }

    public function accept(): string
    {
        return implode(',', $this->formaty);
    }

    /**
     * Co nabídnout při nahrazení souboru: jeho formát a u JPG, PNG, WebP a AVIF i ostatní z nich a GIF, které se při uložení převedou.
     */
    public function acceptProNahrazeni(string $mimeNahrazovaneho): string
    {
        $povolene = array_intersect(
            array_unique([$mimeNahrazovaneho, ...$this->getZdrojeProCil($mimeNahrazovaneho)]),
            $this->formaty,
        );

        return implode(',', $povolene);
    }

    /**
     * Formáty, ze kterých se obrázek při nahrazení souboru převede do $mimeCile, v pořadí, v jakém je nabízí okno pro výběr.
     * Pro každý zdroj zvlášť, protože AVIF se z JPEG zapíše i tam, kde se z PNG s průhledností nezapíše.
     *
     * @return list<string>
     */
    private function getZdrojeProCil(string $mimeCile): array
    {
        return array_values(array_filter(
            $this->getPrevoditelneZdroje(),
            fn (string $zdroj): bool => $zdroj !== $mimeCile && $this->isWritableTo($mimeCile, $zdroj),
        ));
    }

    /**
     * Jestli služba umí obrázek zapsat do formátu $mimeCile. AVIF jen tehdy, když ho tenhle ImageMagick zapíše správně
     * (@see ImagickAvif::isAvifWritable()); bez znalosti zdroje se počítá s průhledností, která se zapisuje hůř.
     */
    private function isWritableTo(string $mimeCile, ?string $zdrojovyMime = null): bool
    {
        if ($this->optimalizace === null || !in_array($mimeCile, self::PREVODITELNE_CILE, true)) {
            return false;
        }

        return $mimeCile !== self::AVIF
            || $this->optimalizace->isAvifWritableFrom($zdrojovyMime ?? self::PNG);
    }

    /**
     * Například „JPG, PNG, WebP, AVIF nebo GIF“, v pořadí, v jakém se formáty obvykle jmenují.
     */
    private function getPopisZdrojuProCil(string $mimeCile): string
    {
        $povolene = [$mimeCile, ...$this->getZdrojeProCil($mimeCile)];

        return $this->popisFormatu(array_values(array_intersect(self::PREVODITELNE_ZDROJE, $this->formaty, $povolene)));
    }

    /**
     * @return list<string>
     */
    private function getPrevoditelneZdroje(): array
    {
        return $this->isAvifProcessable()
            ? self::PREVODITELNE_ZDROJE
            : array_values(array_diff(self::PREVODITELNE_ZDROJE, [self::AVIF]));
    }

    /**
     * AVIF se zpracovává (zmenšuje, převádí) jen tam, kde ho ImageMagick přečte i zapíše správně; jinak zůstane tak, jak
     * přišel, jako před AVIF.
     */
    private function isAvifProcessable(): bool
    {
        return $this->optimalizace?->isAvifWritableFrom(self::AVIF) ?? false;
    }

    /**
     * Do čeho se uloží nový JPEG nebo PNG: do AVIF, když ho služba povoluje a umí zapsat, jinak do WebP, jako před AVIF.
     */
    private function getVychoziCil(string $zdrojovyMime): string
    {
        if (!in_array($zdrojovyMime, [self::JPG, self::PNG], true)) {
            return $zdrojovyMime;
        }
        foreach ([self::AVIF, self::WEBP] as $cil) {
            if (in_array($cil, $this->formaty, true) && $this->isWritableTo($cil, $zdrojovyMime)) {
                return $cil;
            }
        }

        return $zdrojovyMime;
    }

    /**
     * Obrázek jiného formátu, než je cíl, se do cíle převede, když je cílem JPG, PNG, WebP nebo AVIF a obrázek je JPG, PNG,
     * WebP, AVIF nebo GIF a není animovaný.
     */
    private function jeVhodnyKPrevoduDo(NahranyObrazek $obrazek, string $mimeCile): bool
    {
        return $this->isWritableTo($mimeCile, $obrazek->nahranyMime())
            && in_array($obrazek->nahranyMime(), $this->getPrevoditelneZdroje(), true)
            && !$this->optimalizace?->jeAnimovany($obrazek->cesta, $obrazek->nahranyMime());
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
            // PHP odmítlo soubor kvůli limitu jednoho požadavku, i kdyby ho služba po částech přijala
            throw $this->chybaVelikosti(min($this->maxVelikostBajtu(), $this->maxVelikostJednohoPozadavkuBajtu() ?: PHP_INT_MAX));
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
        if ($obrazek->zdrojovyMime !== null
            && $obrazek->zdrojovyMime !== $obrazek->mime
            && $this->jeUzPouzitaJinaPripona($adresar, $nazevBezPripony, $obrazek->pripona)
        ) {
            // převod by smazal soubor, na který odkazuje text, a text by pak vedl nikam
            $obrazek = $this->vFormatu($obrazek, $obrazek->zdrojovyMime);
        }
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

        $this->ulozPresDocasny($obrazek, $adresar, $cil);
        // až po uložení nového, jinak by neúspěšný zápis nechal uživatele bez jakéhokoli obrázku
        foreach ($ostatniVarianty as $varianta) {
            if (!@unlink($varianta)) {
                throw new NahravaniObrazkuException(sprintf(
                    'Nový obrázek je uložený, ale starší %s se nepodařilo odstranit. Zkus to prosím znovu.',
                    basename($varianta),
                ));
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
        $existujici = file_exists($cil)
            ? $cil
            : $this->obrazekStejnehoNazvu($adresar, $nazevBezPripony, $obrazek->mime);
        if ($existujici !== null) {
            throw new NahravaniObrazkuException(sprintf(
                'Soubor %s už existuje. Zvol jiný název, nebo nahraď předchozí.',
                basename($existujici),
            ));
        }
        $this->ulozPresDocasny($obrazek, $adresar, $cil);

        return $cil;
    }

    /**
     * Nahradí soubor na dané cestě, pod jeho názvem, ať odkazy dál fungují. Soubor nemusí existovat, text na něj může
     * odkazovat dřív, než je nahraný. Formát musí odpovídat příponě, jinak by se pod odkazem na `.jpg` schovávalo PNG:
     * obrázek v jiném formátu se převede, když to jde (@see self::jeVhodnyKPrevoduDo()), ostatní soubory musí být stejného typu.
     *
     * @return string absolutní cesta k uloženému souboru
     */
    public function nahrad(NahranyObrazek $obrazek, string $relativniCesta): string
    {
        $cil = $this->cestaUvnitrKorene($relativniCesta, true);
        if (is_dir($cil)) {
            throw new NahravaniObrazkuException('Na tomhle místě je adresář, ne soubor.');
        }
        $mimeCile = self::mimePodlePripony($cil);
        if ($mimeCile === null || !in_array($mimeCile, $this->formaty, true)) {
            throw new NahravaniObrazkuException(
                sprintf('Soubor %s tu nejde nahradit, jeho typ tu není povolený.', basename($cil)),
            );
        }
        // soubor ve formátu cíle, takový, který se do něj při uložení převede (JPEG a PNG do AVIF), nebo jiný převoditelný obrázek
        if ($mimeCile !== $obrazek->nahranyMime() && $mimeCile !== $obrazek->mime && !$this->jeVhodnyKPrevoduDo($obrazek, $mimeCile)) {
            throw new NahravaniObrazkuException(sprintf(
                '%s je ve formátu %s a nahraný soubor ve formátu %s. Nahraj prosím %s.',
                basename($cil),
                self::NAZVY_FORMATU[$mimeCile],
                self::NAZVY_FORMATU[$obrazek->nahranyMime()],
                $this->getZdrojeProCil($mimeCile) !== []
                    ? 'neanimovaný ' . $this->getPopisZdrojuProCil($mimeCile) . ', převede se na ' . self::NAZVY_FORMATU[$mimeCile]
                    : 'soubor ve stejném formátu, jinak by přestaly fungovat odkazy na něj',
            ));
        }
        // odkazy míří na příponu, takže se obrázek převede do formátu souboru, který nahrazuje
        $this->ulozPresDocasny($this->vFormatu($obrazek, $mimeCile), dirname($cil), $cil);

        return $cil;
    }

    public function smaz(string $relativniCesta): void
    {
        $cesta = $this->cestaUvnitrKorene($relativniCesta, false);
        if (!is_file($cesta) || $this->znamyTyp($cesta) === null) {
            throw new NahravaniObrazkuException('Soubor neexistuje.');
        }
        $this->overZeNeniPouzity($cesta, 'Nejdřív prosím odkaz odstraň.');
        if (!@unlink($cesta)) {
            throw new NahravaniObrazkuException(sprintf('Soubor %s se nepodařilo smazat.', basename($cesta)));
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
     * Názvy souborů známých typů přímo v adresáři (bez podadresářů a skrytých souborů), v pořadí podle názvu.
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
            // zpětné lomítko je pro službu oddělovač adresářů, takový soubor by neuměla smazat ani nahradit
            if (!str_starts_with($soubor, '.')
                && !str_contains($soubor, '\\')
                && $this->znamyTyp($soubor) !== null
                && is_file($adresar . '/' . $soubor)
            ) {
                $obrazky[] = $soubor;
            }
        }

        return $obrazky;
    }

    /**
     * Soubory jakéhokoli typu v adresáři i podadresářích, jako cesty vůči kořeni služby v pořadí podle názvu.
     * Vynechá skryté soubory, názvy se zpětným lomítkem a symlinkované podadresáře.
     *
     * @return list<string>
     */
    public function souboryVeStromu(string $relativniAdresar = '', int $maxHloubka = 3): array
    {
        if (!$this->existujeAdresar($relativniAdresar)) {
            return [];
        }
        $soubory = [];
        $this->projdiStrom(
            $this->adresarUvnitrKorene($relativniAdresar, false),
            self::segmentyCesty($relativniAdresar),
            $maxHloubka,
            $soubory,
        );
        sort($soubory, SORT_NATURAL | SORT_FLAG_CASE);

        return $soubory;
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
        return $this->pouzitiObrazku?->kdeJePouzity($this->cestaPodKorenem($absolutniCesta)) ?? [];
    }

    /**
     * Totéž pro víc souborů najednou, texty webu se prohledají jednou.
     *
     * @param list<string> $absolutniCesty
     * @return array<string, list<array{popis: string, url: string}>> podle zadané cesty
     */
    public function kdeJsouPouzity(array $absolutniCesty): array
    {
        if ($this->pouzitiObrazku === null) {
            return array_fill_keys($absolutniCesty, []);
        }
        $podKorenem = array_combine($absolutniCesty, array_map($this->cestaPodKorenem(...), $absolutniCesty));
        $pouziti = $this->pouzitiObrazku->kdeJsouPouzity(array_values($podKorenem));

        return array_map(
            static fn (string $cestaPodKorenem): array => $pouziti[$cestaPodKorenem] ?? [],
            $podKorenem,
        );
    }

    /**
     * Veřejná adresa souboru, null pro soubor mimo web.
     */
    public function urlSouboru(string $absolutniCesta): ?string
    {
        return ZpracovaneObrazky::urlNaWebu($this->cestaPodKorenem($absolutniCesta));
    }

    /**
     * @return int bajty nastavení PHP typu `8M`, 0 když je prázdné
     */
    public static function bajtyZNastaveniPhp(string $nazev): int
    {
        $hodnota = trim((string) ini_get($nazev));

        return $hodnota === ''
            ? 0
            : (int) @ini_parse_quantity($hodnota);
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

        // dokumenty dřív než obrázky: getimagesize() při neúspěchu zkouší i XBM a WBMP a čte soubor po řádcích,
        // takže velký soubor bez zalomení řádku vyčerpá paměť
        $dokument = $this->pracujeSDokumenty
            ? RozpoznaniDokumentu::mime($cesta)
            : null;
        if ($dokument !== null) {
            if (!in_array($dokument, $this->formaty, true)) {
                throw new NahravaniObrazkuException(
                    sprintf('Formát %s tu nejde použít, nahraj prosím %s.', self::NAZVY_FORMATU[$dokument], $this->popisFormatu()),
                );
            }

            return $this->nahranyBezRozmeru($cesta, $puvodniNazev, $dokument, $velikost, $zHttpUploadu);
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
            $cilUpravy = $this->cilUpravy($mime, $sirka, $vyska, $cesta);
            if ($cilUpravy !== null && $this->optimalizace?->jeDelsiNezServerPrecte($sirka, $vyska)) {
                $limity = $this->optimalizace->limityServeru();
                throw new NahravaniObrazkuException(sprintf(
                    'Obrázek má %d × %d px a server zvládne nejvýš %d × %d px. Zmenši ho prosím.',
                    $sirka,
                    $vyska,
                    $limity['sirka'],
                    $limity['vyska'],
                ));
            }
            $maxPixelu = $cilUpravy === null
                ? $this->maxPixelu
                : max($this->maxPixelu, self::MAX_PIXELU);
            if ($sirka * $vyska > $maxPixelu) {
                throw new NahravaniObrazkuException(sprintf(
                    'Obrázek má %d × %d px, to je na web zbytečně moc. Zmenši ho prosím nejvýš na %s Mpx.',
                    $sirka,
                    $vyska,
                    rtrim(rtrim(number_format($maxPixelu / 1_000_000, 1, ',', ''), '0'), ','),
                ));
            }
            if ($velikost > $this->maxVelikostObrazkuBajtu()) {
                throw new NahravaniObrazkuException(sprintf(
                    'Obrázek je příliš velký, maximum pro obrázky je %s.',
                    self::lidskaVelikost($this->maxVelikostObrazkuBajtu()),
                ));
            }

            return new NahranyObrazek(
                cesta: $cesta,
                puvodniNazev: $puvodniNazev,
                mime: $cilUpravy ?? $mime,
                pripona: self::PRIPONA_PODLE_MIME[$cilUpravy ?? $mime],
                sirka: $sirka,
                vyska: $vyska,
                velikostBajtu: $velikost,
                zHttpUploadu: $zHttpUploadu,
                zdrojovyMime: $cilUpravy === null
                    ? null
                    : $mime,
            );
        }

        if (in_array(self::SVG, $this->formaty, true) && $this->vypadaJakoSvg($cesta, $puvodniNazev)) {
            $chybaSvg = LogoUpload::validujSvgSoubor($cesta);
            if ($chybaSvg !== null) {
                throw new NahravaniObrazkuException($chybaSvg);
            }

            return $this->nahranyBezRozmeru($cesta, $puvodniNazev, self::SVG, $velikost, $zHttpUploadu);
        }

        throw new NahravaniObrazkuException(match (true) {
            !$this->pracujeSDokumenty => sprintf('Soubor není obrázek, nahraj prosím %s.', $this->popisFormatu())
                . $this->tipProHeic($puvodniNazev),
            RozpoznaniDokumentu::jeBalikOffice($cesta) => $this->zpravaOBalikuOffice(),
            default => sprintf('Soubor tohoto typu tu nejde nahrát, povolené jsou %s.', $this->popisFormatu())
                . $this->tipProHeic($puvodniNazev),
        });
    }

    /**
     * Nový GIF se neupravuje, animace by se ztratila a paleta zhoršila; animovaný PNG, WebP a AVIF taky ne. GIF bez animace se
     * převede jen při nahrazení souboru jiného formátu. Nový JPEG a PNG se převedou do AVIF, WebP a AVIF se jen zmenší.
     *
     * @param string|null $vynucenyCil formát, ve kterém se soubor musí uložit, třeba protože nahrazuje soubor s touhle příponou
     * @return string|null formát, ve kterém se soubor při uložení zapíše znovu; null, když se uloží tak, jak přišel
     */
    private function cilUpravy(string $zdrojovyMime, int $sirka, int $vyska, string $cesta, ?string $vynucenyCil = null): ?string
    {
        $umiSePrecist = in_array($zdrojovyMime, [self::JPG, self::PNG, self::WEBP], true)
            || ($zdrojovyMime === self::AVIF && $this->isAvifProcessable())
            || ($zdrojovyMime === self::GIF && $vynucenyCil !== null);
        if ($this->optimalizace === null || !$umiSePrecist) {
            return null;
        }
        $cil = $vynucenyCil ?? $this->getVychoziCil($zdrojovyMime);
        if ($cil === $zdrojovyMime && !$this->optimalizace->jeVetsiNezPotrebuje($sirka, $vyska)) {
            return null;
        }
        if ($cil === self::AVIF && !$this->isWritableTo(self::AVIF, $zdrojovyMime)) {
            return null;
        }
        if ($this->optimalizace->jeAnimovany($cesta, $zdrojovyMime)) {
            return null;
        }

        return $cil;
    }

    /**
     * Stejný soubor s jiným cílovým formátem; soubor, který se ukládá tak, jak přišel, zůstane.
     */
    private function vFormatu(NahranyObrazek $obrazek, string $mime): NahranyObrazek
    {
        $zdrojovyMime = $obrazek->nahranyMime();
        if ($obrazek->zdrojovyMime === null && $zdrojovyMime === $mime) {
            return $obrazek;
        }
        $cilUpravy = $this->cilUpravy($zdrojovyMime, (int) $obrazek->sirka, (int) $obrazek->vyska, $obrazek->cesta, $mime);
        if ($cilUpravy === null && $zdrojovyMime !== $mime) {
            // soubor jiného formátu by se uložil s příponou $mime
            throw new NahravaniObrazkuException(sprintf(
                'Soubor ve formátu %s se nepodařilo převést do formátu %s.',
                self::NAZVY_FORMATU[$zdrojovyMime],
                self::NAZVY_FORMATU[$mime],
            ));
        }

        return $obrazek->sFormatem(
            $mime,
            self::PRIPONA_PODLE_MIME[$mime],
            $cilUpravy === null
                ? null
                : $zdrojovyMime,
        );
    }

    private function zpravaOBalikuOffice(): string
    {
        if (array_intersect($this->formaty, [self::DOCX, self::ODT, self::ODS]) !== []) {
            return sprintf(
                'Tenhle dokument tu nejde nahrát. Prezentace, dokumenty s makry nebo vloženými objekty a jiné balíky Office se nepřijímají.%s Povolené jsou %s.',
                in_array(self::PDF, $this->formaty, true) ? ' Ulož ho prosím jako PDF nebo bez maker a objektů.' : '',
                $this->popisFormatu(),
            );
        }
        $rada = in_array(self::PDF, $this->formaty, true)
            ? ' Ulož ho prosím jako PDF' . (in_array(self::XLSX, $this->formaty, true) ? ', tabulku jako XLSX bez maker' : '') . '.'
            : '';

        return sprintf(
            'Dokument z Wordu, PowerPointu, Excelu s makry nebo LibreOffice tu nejde nahrát.%s Povolené jsou %s.',
            $rada,
            $this->popisFormatu(),
        );
    }

    private function nahranyBezRozmeru(
        string $cesta,
        string $puvodniNazev,
        string $mime,
        int $velikost,
        bool $zHttpUploadu,
    ): NahranyObrazek {
        return new NahranyObrazek(
            cesta: $cesta,
            puvodniNazev: $puvodniNazev,
            mime: $mime,
            pripona: self::PRIPONA_PODLE_MIME[$mime],
            sirka: null,
            vyska: null,
            velikostBajtu: $velikost,
            zHttpUploadu: $zHttpUploadu,
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

    private function popisUpravy(): string
    {
        if ($this->optimalizace === null || array_intersect($this->formaty, self::RASTROVE_FORMATY) === []) {
            return '';
        }
        $rozmer = $this->optimalizace->maxRozmerPx() . ' px';

        $cilJpg = $this->getVychoziCil(self::JPG);
        if (!in_array($cilJpg, [self::AVIF, self::WEBP], true)) {
            return sprintf('větší obrázky se zmenší na nejvýš %s', $rozmer);
        }
        $cilPng = $this->getVychoziCil(self::PNG);
        if ($cilPng === $cilJpg) {
            return sprintf('JPG a PNG se zmenší na nejvýš %s a uloží jako %s', $rozmer, self::NAZVY_FORMATU[$cilJpg]);
        }

        // AVIF z PNG s průhledností se na starším ImageMagicku nezapíše, PNG pak jde do WebP, a když ho služba nemá, zůstane PNG
        return sprintf(
            'JPG se zmenší na nejvýš %s a uloží jako %s, PNG se %s',
            $rozmer,
            self::NAZVY_FORMATU[$cilJpg],
            $cilPng === self::PNG
                ? 'jen zmenší'
                : 'uloží jako ' . self::NAZVY_FORMATU[$cilPng],
        );
    }

    /**
     * @param list<string>|null $formaty MIME typy, služba bez nich popíše své povolené formáty
     */
    private function popisFormatu(?array $formaty = null): string
    {
        $nazvy = array_map(static fn (string $mime): string => self::NAZVY_FORMATU[$mime], $formaty ?? $this->formaty);
        $posledni = array_pop($nazvy);

        return $nazvy === []
            ? (string) $posledni
            : implode(', ', $nazvy) . ' nebo ' . $posledni;
    }

    private function chybaVelikosti(?int $limitBajtu = null): NahravaniObrazkuException
    {
        return new NahravaniObrazkuException(
            sprintf('Soubor je příliš velký, maximum je %s.', self::lidskaVelikost($limitBajtu ?? $this->maxVelikostBajtu())),
        );
    }

    /**
     * Fotka nebo plakát nemá mít stovky megabajtů jen proto, že složka bere i MP3 a archivy.
     */
    private function maxVelikostObrazkuBajtu(): int
    {
        if ($this->maxVelikostObrazkuMb === null && !$this->pracujeSDokumenty) {
            return $this->maxVelikostBajtu();
        }

        return min(
            (int) round(($this->maxVelikostObrazkuMb ?? OBRAZEK_MAX_MB) * 1024 * 1024),
            $this->maxVelikostBajtu(),
        );
    }

    private function overNazevSouboru(string $nazev): void
    {
        if ($nazev === ''
            || str_starts_with($nazev, '.')
            || strpbrk($nazev, "/\\\0") !== false
            || strlen($nazev) > self::MAX_DELKA_NAZVU_BAJTU
        ) {
            throw new NahravaniObrazkuException('Neplatný název souboru.');
        }
    }

    /**
     * Typ podle přípony, ale jen z těch, se kterými tahle služba pracuje. Služba pro obrázky nesmí vidět PDF,
     * které leží ve stejném adresáři, třeba jako „variantu“ fotky.
     */
    private function znamyTyp(string $soubor): ?string
    {
        $mime = self::mimePodlePripony($soubor);

        return $mime !== null && ($this->pracujeSDokumenty || !in_array($mime, self::DOKUMENTY, true))
            ? $mime
            : null;
    }

    /**
     * @param list<string> $segmenty cesta k $adresar vůči kořeni služby
     * @param list<string> $soubory sem se přidávají cesty souborů
     */
    private function projdiStrom(string $adresar, array $segmenty, int $zbyvaPater, array &$soubory): void
    {
        foreach (scandir($adresar) ?: [] as $nazev) {
            if (str_starts_with($nazev, '.') || str_contains($nazev, '\\')) {
                continue;
            }
            $cesta = $adresar . '/' . $nazev;
            if (is_dir($cesta)) {
                // symlinkovaný podadresář vede ven z kořene, nebo i dovnitř do smyčky, soubory v něm by se vypsaly víckrát
                if ($zbyvaPater > 0 && !is_link($cesta)) {
                    $this->projdiStrom($cesta, [...$segmenty, $nazev], $zbyvaPater - 1, $soubory);
                }
            } elseif (is_file($cesta)) {
                $soubory[] = implode('/', [...$segmenty, $nazev]);
            }
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
                throw new NahravaniObrazkuException('Nepodařilo se vytvořit adresář.');
            }
        }
        if (!is_dir($cesta)) {
            throw new NahravaniObrazkuException('Adresář neexistuje.');
        }

        return $this->overZeJeUvnitrKorene($cesta);
    }

    /**
     * Cesta souboru tak, jak vede pod kořenem zadaným při vytvoření služby. Služba pracuje se skutečnými cestami,
     * jenže když je kořen (nebo adresář nad ním) symlink na jiné úložiště, skutečná cesta vede mimo web
     * a texty na ni neodkazují.
     */
    private function cestaPodKorenem(string $absolutniCesta): string
    {
        $realnyKoren = realpath($this->korenovyAdresar);
        $realnyAdresar = realpath(dirname($absolutniCesta));
        if ($realnyKoren === false || $realnyAdresar === false) {
            return $absolutniCesta;
        }
        $predponaKorene = rtrim($realnyKoren, '/') . '/';
        $realnaCesta = $realnyAdresar . '/' . basename($absolutniCesta);

        return str_starts_with($realnaCesta, $predponaKorene)
            ? rtrim($this->korenovyAdresar, '/') . '/' . substr($realnaCesta, strlen($predponaKorene))
            : $absolutniCesta;
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
                && $this->znamyTyp($soubor) !== null
                && is_file($adresar . '/' . $soubor)
            ) {
                $varianty[] = $adresar . '/' . $soubor;
            }
        }

        return $varianty;
    }

    /**
     * Obrázek se může uložit s jinou příponou, než měl nahraný, takže `foto.jpg` nahrané k existujícímu `foto.png`
     * by vedle něj vytvořilo `foto.webp`. Dokumenty a SVG se nepřevádějí, mají příponu podle formátu a s rastrem se nepletou.
     *
     * @return string|null absolutní cesta k obrázku stejného názvu
     */
    private function obrazekStejnehoNazvu(string $adresar, string $nazevBezPripony, string $mime): ?string
    {
        if (!in_array($mime, self::RASTROVE_FORMATY, true)) {
            return null;
        }
        foreach ($this->variantyObrazku($adresar, $nazevBezPripony) as $varianta) {
            if (in_array(self::mimePodlePripony($varianta), self::RASTROVE_FORMATY, true)) {
                return $varianta;
            }
        }

        return null;
    }

    private function jeUzPouzitaJinaPripona(string $adresar, string $nazevBezPripony, string $pripona): bool
    {
        $cil = $adresar . '/' . $nazevBezPripony . '.' . $pripona;
        foreach ($this->variantyObrazku($adresar, $nazevBezPripony) as $varianta) {
            if ($varianta !== $cil && $this->kdeJePouzity($varianta) !== []) {
                return true;
            }
        }

        return false;
    }

    private function overZeNeniPouzity(string $cesta, string $coDal): void
    {
        $pouziti = $this->kdeJePouzity($cesta);
        if ($pouziti !== []) {
            // odkaz bez adresy do administrace je v kódu webu, ten z administrace odstranit nejde
            $jeVKodu = in_array('', array_column($pouziti, 'url'), true);
            throw new NahravaniObrazkuException(sprintf(
                'Na soubor %s odkazuje %s. %s',
                basename($cesta),
                PouzitiObrazku::popis($pouziti),
                $jeVKodu
                    ? 'Odkaz je v kódu webu, smazat ho jde jen jeho úpravou.'
                    : $coDal,
            ));
        }
    }

    private function ulozPresDocasny(NahranyObrazek $obrazek, string $adresar, string $cil): void
    {
        $docasny = $adresar . '/' . self::PREFIX_DOCASNEHO_SOUBORU . bin2hex(random_bytes(8));
        try {
            $this->zapisDoDocasneho($obrazek, $docasny);
            $this->presunNaMisto($docasny, $cil);
        } finally {
            // úprava i kopírování mohly soubor začít psát a nedopsat
            if (is_file($docasny)) {
                @unlink($docasny);
            }
        }
    }

    private function zapisDoDocasneho(NahranyObrazek $obrazek, string $docasny): void
    {
        if ($obrazek->zdrojovyMime !== null) {
            ($this->optimalizace ?? throw new \LogicException('Obrázek čeká na úpravu, ale služba ji nemá nastavenou.'))
                ->zapis(
                    $obrazek->cesta,
                    $obrazek->zdrojovyMime,
                    $docasny,
                    $obrazek->mime,
                    (int) $obrazek->sirka,
                    (int) $obrazek->vyska,
                );

            return;
        }
        $presunuto = $obrazek->zHttpUploadu
            ? @move_uploaded_file($obrazek->cesta, $docasny)
            : @copy($obrazek->cesta, $docasny);
        if (!$presunuto) {
            throw new NahravaniObrazkuException('Soubor se nepodařilo uložit.');
        }
    }

    private function presunNaMisto(string $docasny, string $cil): void
    {
        if (!@rename($docasny, $cil)) {
            throw new NahravaniObrazkuException('Soubor se nepodařilo uložit.');
        }
        clearstatcache(true, $cil);
    }
}
