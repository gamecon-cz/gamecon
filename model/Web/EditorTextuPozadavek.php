<?php

declare(strict_types=1);

namespace Gamecon\Web;

/**
 * Požadavky editoru textu v administraci (ui/src/editorTextu, pole @see \DbffMarkdownDirect). Modul s editorem je
 * obslouží na vlastní adrese s parametrem `editorTextu`:
 * - `nahled` (POST `text`) vrátí `html`, text vykreslený stejně jako náhled vedle editoru při načtení stránky,
 *   tedy stejným Markdownem jako na webu; s `odUzivatele=1` (pole s textem, který si píše uživatel sám, @see
 *   \DbffMarkdownOdUzivatele) jen vyčištěné HTML,
 * - `obrazek` (POST soubor `obrazek`) uloží obrázek vložený do textu ze schránky nebo přetažením do adresáře textu
 *   (stejného jako blok Obrázky pod editorem) pod volným názvem a vrátí `cesta` (jak ji zapsat do textu) a `markdown`.
 * Odpověď je JSON s `ok`; chyba, kterou má vidět člověk, je `ok: false` se `zprava` a kód 422.
 */
final class EditorTextuPozadavek
{
    public const string PARAMETR = 'editorTextu';
    /**
     * Cizí web hlavičku bez povolení od nás poslat nemůže, takže ji nezná, a nezneužije přihlášeného správce
     */
    public const string HLAVICKA = 'HTTP_X_GAMECON_EDITOR';
    public const string POST_TEXT = 'text';
    public const string POST_OD_UZIVATELE = 'odUzivatele';
    public const string POST_OBRAZEK = 'obrazek';

    /**
     * Název obrázku, ze kterého nezbude nic použitelného v adrese (třeba jen emoji)
     */
    private const string NAZEV_BEZ_NAZVU = 'obrazek';

    private readonly NahravaniObrazku $nahravani;

    /**
     * @param \Closure(): ?KontextTextu $kontext text, ke kterému se obrázky ukládají; null, dokud text není uložený
     *                                           nebo když pole obrázky nemá (medailonek)
     */
    public function __construct(
        private readonly \Closure $kontext,
        ?NahravaniObrazku $nahravani = null,
    ) {
        $this->nahravani = $nahravani ?? NahravaniObrazku::proObsahWebu();
    }

    /**
     * Pro začátek modulu s editorem: požadavek editoru obslouží a skončí. Editor čeká jen odpověď, nic
     * z administrace okolo ní, proto `exit` a ne `$BEZ_DEKORACE`.
     *
     * @param (\Closure(): ?KontextTextu)|null $kontext zavolá se, až když editor obrázek opravdu posílá
     */
    public static function obslouzPokudJe(?\Closure $kontext = null): void
    {
        if (! isset($_GET[self::PARAMETR])) {
            return;
        }
        (new self($kontext ?? static fn (): ?KontextTextu => null))->odpovez();

        exit;
    }

    /**
     * Obslouží požadavek podle `$_GET`, `$_POST`, `$_FILES` a `$_SERVER` a vypíše odpověď. Volající skončí `exit`.
     */
    public function odpovez(): void
    {
        $odpoved = NahravaniObrazku::bylPozadavekPrilisVelky()
            ? self::chyba(NahravaniObrazku::zpravaOPrilisVelkemPozadavku())
            : $this->zpracuj(
                (string) ($_GET[self::PARAMETR] ?? ''),
                (string) ($_SERVER['REQUEST_METHOD'] ?? ''),
                isset($_SERVER[self::HLAVICKA]),
                $_POST,
                fn (): NahranyObrazek => $this->nahravani->overNahranySoubor($_FILES[self::POST_OBRAZEK] ?? null),
            );
        http_response_code($odpoved['kod']);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($odpoved['telo'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * @param array<string, mixed>       $post
     * @param \Closure(): NahranyObrazek $nahranyObrazek ověří obrázek z požadavku, zavolá se jen u akce `obrazek`
     *
     * @return array{kod: int, telo: array<string, mixed>}
     */
    public function zpracuj(
        string $akce,
        string $metoda,
        bool $maHlavicku,
        array $post,
        \Closure $nahranyObrazek,
    ): array {
        if ($metoda !== 'POST' || ! $maHlavicku) {
            return [
                'kod'  => 400,
                'telo' => [
                    'ok'     => false,
                    'zprava' => 'Neplatný požadavek.',
                ],
            ];
        }
        try {
            $vysledek = match ($akce) {
                'nahled'  => $this->nahled($post),
                'obrazek' => $this->ulozObrazek($nahranyObrazek),
                default   => throw new NahravaniObrazkuException('Neznámý požadavek editoru.'),
            };
        } catch (NahravaniObrazkuException $chyba) {
            return self::chyba($chyba->getMessage());
        }

        return [
            'kod'  => 200,
            'telo' => [
                'ok' => true,
                ...$vysledek,
            ],
        ];
    }

    /**
     * @param array<string, mixed> $post
     *
     * @return array{html: string}
     */
    private function nahled(array $post): array
    {
        $text = $post[self::POST_TEXT] ?? null;
        if (! is_string($text)) {
            throw new NahravaniObrazkuException('Chybí text k náhledu.');
        }

        // příznak posílá editor podle pole; požadavek bez naší hlavičky server odmítne, takže ho cizí web
        // organizátorovi podstrčit nemůže
        return [
            'html' => ($post[self::POST_OD_UZIVATELE] ?? null) === '1'
                ? NahledMarkdownu::zTextuOdUzivatele($text)
                : NahledMarkdownu::zTextu($text),
        ];
    }

    /**
     * @param \Closure(): NahranyObrazek $nahranyObrazek
     *
     * @return array{cesta: string, markdown: string}
     */
    private function ulozObrazek(\Closure $nahranyObrazek): array
    {
        // před ověřením souboru: obrázek bez místa, kam ho uložit, nemá cenu kontrolovat
        $kontext = ($this->kontext)();
        if ($kontext === null) {
            throw new NahravaniObrazkuException('Obrázek do textu půjde vložit, až text uložíš.');
        }
        $obrazek = $nahranyObrazek();
        try {
            $nazev = NahravaniObrazku::bezpecnyNazev($obrazek->puvodniNazevBezPripony());
        } catch (NahravaniObrazkuException) {
            $nazev = self::NAZEV_BEZ_NAZVU;
        }
        $ulozeny = $this->nahravani->ulozNovyPodVolnymNazvem($obrazek, $kontext->adresarProNove, $nazev);
        $vTextu = ObrazkyObsahu::PREFIX_V_TEXTU
            . ZpracovaneObrazky::zakodujCestu($kontext->adresarProNove . '/' . basename($ulozeny));

        return [
            'cesta'    => $vTextu,
            'markdown' => ZapisObrazkuVTextu::markdown($vTextu),
        ];
    }

    /**
     * @return array{kod: int, telo: array{ok: false, zprava: string}}
     */
    private static function chyba(string $zprava): array
    {
        return [
            'kod'  => 422,
            'telo' => [
                'ok'     => false,
                'zprava' => $zprava,
            ],
        ];
    }
}
