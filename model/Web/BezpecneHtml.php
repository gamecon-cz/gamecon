<?php

declare(strict_types=1);

namespace Gamecon\Web;

use Symfony\Component\Filesystem\Filesystem;

/**
 * HTML z textu, který si na web píše uživatel sám (medailonek vypravěče). Markdown propouští i HTML zapsané v textu,
 * takže by do stránky šel vložit skript; tady z výsledku zůstanou jen značky a atributy, které Markdown sám vytváří
 * a které nic nespustí. Odkaz jen na http(s), e-mail, telefon nebo relativní adresu (ven s `rel="nofollow ugc"`),
 * obrázek jen z webu GameConu. Totéž pro živý náhled v adminu dělá admin/files/bezpecny-nahled-markdownu.js podle
 *
 * @see getPravidlaProNahled()
 *
 * Výstup se skládá znovu ze seznamu povolených značek, ne mazáním zakázaných: co seznam nezná, nepropadne, i když na
 * to nikdo nepomyslel.
 */
final class BezpecneHtml
{
    /**
     * značka => atributy, které si smí nechat
     */
    private const array POVOLENE_ZNACKY = [
        'a'          => ['href', 'title'],
        'abbr'       => ['title'],
        'b'          => [],
        'blockquote' => [],
        'br'         => [],
        'cite'       => [],
        'code'       => [],
        'dd'         => [],
        'del'        => [],
        'div'        => [],
        'dl'         => [],
        'dt'         => [],
        'em'         => [],
        'h1'         => [],
        'h2'         => [],
        'h3'         => [],
        'h4'         => [],
        'h5'         => [],
        'h6'         => [],
        'hr'         => [],
        'i'          => [],
        'img'        => ['src', 'alt', 'title', 'width', 'height'],
        'ins'        => [],
        'kbd'        => [],
        'li'         => [],
        'mark'       => [],
        'ol'         => ['start'],
        'p'          => [],
        'pre'        => [],
        'q'          => [],
        's'          => [],
        'small'      => [],
        'span'       => [],
        'strong'     => [],
        'sub'        => [],
        'sup'        => [],
        'table'      => [],
        'tbody'      => [],
        'td'         => ['align', 'colspan', 'rowspan'],
        'tfoot'      => [],
        'th'         => ['align', 'colspan', 'rowspan'],
        'thead'      => [],
        'tr'         => [],
        'u'          => [],
        'ul'         => [],
    ];

    private const array PRAZDNE_ZNACKY = ['br', 'hr', 'img'];

    /**
     * Neznámá značka zmizí a její obsah zůstane jako text. U těchhle by zbyl kód nebo obsah formuláře, proto zmizí celé.
     */
    private const array ZNACKY_BEZ_OBSAHU = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'template', 'noscript', 'noembed',
        'noframes', 'textarea', 'select', 'title', 'xmp', 'plaintext', 'svg', 'math', 'head',
    ];

    private const array SCHEMATA_ODKAZU = ['http', 'https', 'mailto', 'tel'];
    private const array SCHEMATA_OBRAZKU = ['http', 'https'];

    /**
     * Obrázek jen z webu GameConu (i jeho subdomén a domény, na které web právě běží): cizí server by z obrázku
     * v medailonku viděl IP adresy všech, kdo stránku otevřou.
     */
    private const string DOMENA_GAMECONU = 'gamecon.cz';

    /**
     * Odkaz ven z textu, který píše uživatel: vyhledávače mu nemají věřit jako odkazu od GameConu
     */
    private const string REL_ODKAZU_VEN = 'nofollow ugc';

    private static ?string $otiskPravidel = null;

    /**
     * Převod je pomalý (necelá milisekunda na odstavec, přehled vypravěčů jich má stovky), výsledek se proto pamatuje.
     * Ne ve sdílené keši markdown(): tu klíčuje CRC32 textu, kterou jde dopsáním pár znaků podvrhnout, takže by text
     * psaný uživatelem mohl jinému textu se stejným součtem podstrčit své HTML (a ten se nečistí).
     */
    public static function zMarkdownu(?string $markdown): string
    {
        if ($markdown === null || trim($markdown) === '') {
            return '';
        }
        $adresarKese = SPEC . '/bezpecne-html/' . self::getOtiskPravidel();
        $soubor = $adresarKese . '/' . hash('sha256', $markdown) . '.html';
        $html = is_file($soubor)
            ? file_get_contents($soubor)
            : false;
        if ($html === false) {
            $html = self::vycisti(markdownNoCache($markdown));
            self::ulozDoKese($adresarKese, $soubor, $html);
        }

        return $html;
    }

    public static function vycisti(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }
        $dokument = new \DOMDocument('1.0', 'UTF-8');
        $predchoziChyby = libxml_use_internal_errors(true);
        try {
            // deklarace kódování, jinak libxml čte text jako ISO-8859-1
            $dokument->loadHTML(
                '<?xml encoding="UTF-8"?><html><body>' . $html . '</body></html>',
                LIBXML_NONET | LIBXML_HTML_NODEFDTD,
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($predchoziChyby);
        }
        $telo = $dokument->getElementsByTagName('body')->item(0);

        return $telo === null
            ? ''
            : self::vypisPotomky($telo);
    }

    /**
     * Pravidla pro živý náhled v prohlížeči (admin/files/bezpecny-nahled-markdownu.js), ať je jen jedna jejich verze.
     *
     * @return array{znacky: array<string, list<string>>, prazdne: list<string>, bezObsahu: list<string>, schemataOdkazu: list<string>, schemataObrazku: list<string>, domenaGameconu: string, hostWebu: string, relOdkazuVen: string}
     */
    public static function getPravidlaProNahled(): array
    {
        return [
            'znacky'          => self::POVOLENE_ZNACKY,
            'prazdne'         => self::PRAZDNE_ZNACKY,
            'bezObsahu'       => self::ZNACKY_BEZ_OBSAHU,
            'schemataOdkazu'  => self::SCHEMATA_ODKAZU,
            'schemataObrazku' => self::SCHEMATA_OBRAZKU,
            'domenaGameconu'  => self::DOMENA_GAMECONU,
            'hostWebu'        => self::getHostWebu(),
            'relOdkazuVen'    => self::REL_ODKAZU_VEN,
        ];
    }

    /**
     * Otisk všeho, na čem výsledek závisí: kódu téhle třídy (pravidla), Markdownu, typografie a domény webu (obrázky
     * z ní). Po změně kteréhokoli z nich se keš začne plnit znovu a stará se smaže.
     */
    private static function getOtiskPravidel(): string
    {
        return self::$otiskPravidel ??= hash('sha256', implode("\n", [
            (string) hash_file('sha256', __FILE__),
            (string) hash_file('sha256', (string) (new \ReflectionClass(\Smartyp::class))->getFileName()),
            \Michelf\MarkdownExtra::MARKDOWNLIB_VERSION,
            URL_WEBU,
        ]));
    }

    private static function ulozDoKese(string $adresarKese, string $soubor, string $html): void
    {
        $filesystem = new Filesystem();
        if (! is_dir($adresarKese)) {
            // první zápis podle nových pravidel, keš podle starých už nikdo nepřečte
            $stare = array_filter(
                glob(dirname($adresarKese) . '/*', GLOB_ONLYDIR) ?: [],
                static fn (string $adresar): bool => $adresar !== $adresarKese,
            );
            $filesystem->remove($stare);
        }
        // zapisuje dočasný soubor a přejmenuje ho, souběžný požadavek tak nepřečte napůl zapsaný
        $filesystem->dumpFile($soubor, $html);
    }

    private static function vypisPotomky(\DOMNode $rodic): string
    {
        $html = '';
        foreach ($rodic->childNodes as $uzel) {
            if ($uzel instanceof \DOMText) {
                $html .= self::escapuj($uzel->data);
                continue;
            }
            if (! $uzel instanceof \DOMElement) {
                continue; // komentáře, instrukce
            }
            $znacka = strtolower($uzel->nodeName);
            if (in_array($znacka, self::ZNACKY_BEZ_OBSAHU, true)) {
                continue;
            }
            if (! array_key_exists($znacka, self::POVOLENE_ZNACKY)) {
                $html .= self::vypisPotomky($uzel);
                continue;
            }
            if ($znacka === 'img' && ! self::jePovolenaHodnota('src', $uzel->getAttribute('src'))) {
                continue; // obrázek bez adresy by se ukázal jako rozbitý
            }
            if ($znacka === 'a' && ! self::jePovolenaHodnota('href', $uzel->getAttribute('href'))) {
                // odkaz bez adresy by vypadal jako odkaz, na který nejde kliknout
                $html .= self::vypisPotomky($uzel);
                continue;
            }
            $html .= '<' . $znacka . self::atributy($uzel, $znacka);
            if ($znacka === 'a' && self::jeOdkazVen($uzel->getAttribute('href'))) {
                $html .= ' rel="' . self::REL_ODKAZU_VEN . '"';
            }
            $html .= '>';
            if (! in_array($znacka, self::PRAZDNE_ZNACKY, true)) {
                $html .= self::vypisPotomky($uzel) . '</' . $znacka . '>';
            }
        }

        return $html;
    }

    private static function atributy(\DOMElement $element, string $znacka): string
    {
        $html = '';
        foreach (self::POVOLENE_ZNACKY[$znacka] as $atribut) {
            if (! $element->hasAttribute($atribut)) {
                continue;
            }
            $hodnota = $element->getAttribute($atribut);
            if (self::jePovolenaHodnota($atribut, $hodnota)) {
                $html .= ' ' . $atribut . '="' . self::escapuj($hodnota) . '"';
            }
        }

        return $html;
    }

    private static function jePovolenaHodnota(string $atribut, string $hodnota): bool
    {
        return match ($atribut) {
            'href' => self::jePovolenyOdkaz($hodnota),
            'src'  => self::jePovolenyObrazek($hodnota),
            'width', 'height', 'colspan', 'rowspan', 'start' => preg_match('~^\d{1,4}$~', $hodnota) === 1,
            'align' => in_array($hodnota, ['left', 'right', 'center'], true),
            default => true, // title, alt: text se při výpisu escapuje
        };
    }

    private static function jePovolenyOdkaz(string $adresa): bool
    {
        $rozbor = self::rozeberAdresu($adresa);

        return $rozbor !== null
            && ($rozbor['schema'] === null || in_array($rozbor['schema'], self::SCHEMATA_ODKAZU, true));
    }

    private static function jePovolenyObrazek(string $adresa): bool
    {
        $rozbor = self::rozeberAdresu($adresa);
        if ($rozbor === null
            || ($rozbor['schema'] !== null && ! in_array($rozbor['schema'], self::SCHEMATA_OBRAZKU, true))
        ) {
            return false;
        }

        // bez hostu je adresa relativní, tedy z webu GameConu
        return $rozbor['host'] === null || self::jeHostGameconu($rozbor['host']);
    }

    private static function jeOdkazVen(string $adresa): bool
    {
        $rozbor = self::rozeberAdresu($adresa);

        return $rozbor !== null && $rozbor['host'] !== null && ! self::jeHostGameconu($rozbor['host']);
    }

    /**
     * Prohlížeč ze začátku adresy zahodí mezery a řídicí znaky a zevnitř tabulátory a konce řádků (`java&#9;script:`),
     * tady se zahodí všude, takže schéma nikdo neschová. Zpětné lomítko bere prohlížeč v adrese webu jako lomítko
     * (`/\example.com` je `//example.com`).
     *
     * @return array{schema: string|null, host: string|null}|null host jen u http(s) a adresy začínající `//`, null pro
     *                                                            adresu, kterou nejde použít
     */
    private static function rozeberAdresu(string $adresa): ?array
    {
        $adresa = str_replace('\\', '/', (string) preg_replace('~[\x00-\x20\x7F]+~', '', $adresa));
        if ($adresa === '') {
            return null;
        }
        if (preg_match('~^([a-z][a-z\d+.\-]*):~i', $adresa, $shoda) === 1) {
            $schema = strtolower($shoda[1]);

            return [
                'schema' => $schema,
                'host'   => in_array($schema, ['http', 'https'], true)
                    ? strtolower((string) parse_url($adresa, PHP_URL_HOST))
                    : null,
            ];
        }
        if (str_starts_with($adresa, '//')) {
            return [
                'schema' => null,
                'host'   => strtolower((string) parse_url('https:' . $adresa, PHP_URL_HOST)),
            ];
        }

        return [
            'schema' => null,
            'host'   => null,
        ];
    }

    private static function jeHostGameconu(string $host): bool
    {
        $host = rtrim($host, '.');

        return $host === self::DOMENA_GAMECONU
            || str_ends_with($host, '.' . self::DOMENA_GAMECONU)
            || $host === self::getHostWebu();
    }

    private static function getHostWebu(): string
    {
        return strtolower((string) parse_url(URL_WEBU, PHP_URL_HOST));
    }

    private static function escapuj(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
    }
}
