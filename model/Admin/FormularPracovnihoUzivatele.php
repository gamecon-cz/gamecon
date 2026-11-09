<?php

declare(strict_types=1);

namespace Gamecon\Admin;

/**
 * Hlídá, že formulář pracovního uživatele (záložka Uživatel, Infopult) uloží změnu tomu, u koho ho organizátor viděl.
 * Pracovní uživatel je jeden na celou session: kdo ho mezitím přepne v jiné záložce prohlížeče, tomu by dříve načtený
 * formulář připsal platbu, ubytování, jídlo, prodej, osobní údaje nebo fotku jinému člověku.
 *
 * Stránka, která s pracovním uživatelem pracuje, prožene svůj výstup přes @see doplnDoFormularu(), a hlídaný je tak
 * každý její POST formulář, i ten, který na ni přibude později. Formulář bez skrytého pole se nehlídá: ostatním
 * stránkám adminu by přepnutí pracovního uživatele jen zahodilo rozepsaný formulář, který s ním nesouvisí. Ze stejného
 * důvodu se vynechá formulář hlídané stránky s atributem @see BEZ_PRACOVNIHO_UZIVATELE (třeba rychloregistrace).
 */
final class FormularPracovnihoUzivatele
{
    public const string POST_ID = 'idPracovnihoUzivatele';

    /**
     * Atribut formuláře, který s pracovním uživatelem nepracuje, a skryté pole proto nedostane
     */
    public const string BEZ_PRACOVNIHO_UZIVATELE = 'data-bez-pracovniho-uzivatele';

    /**
     * Atributy značky tak, jak je čte prohlížeč (HTML Living Standard, tokenizace značky): uvozovka otevírá hodnotu
     * jen hned za `=`, jinde patří do názvu atributu. Jinak by jediná uvozovka navíc, třeba v textu potvrzovacího
     * dialogu, posunula konec značky jinam, než ho vidí prohlížeč, a s ním i všechny další formuláře stránky.
     */
    private const string ATRIBUTY = <<<'REGEX'
        (?:
            [\s/]++
          | [^\s/>][^\s/>=]*+
            (?:\s*+=\s*+(?:"[^"]*+"|'[^']*+'|[^\s>]*+))?
        )*+
        REGEX;

    /**
     * Obsah, který prohlížeč nečte jako HTML: značka v něm není značka (komentář, skript, styl, textové pole …).
     */
    private const string BEZ_ZNACEK = 'script|style|textarea|title|xmp|iframe|noembed|noframes';

    /**
     * Skryté pole s ID pracovního uživatele, 0 bez pracovního uživatele (třeba anonymní prodej).
     *
     * @param string $idFormulare pole mimo formulář, k němuž patří (atribut `form`), pro formulář, jehož obsah
     *                            přepisuje skript
     */
    public static function getSkrytePole(?\Uzivatel $pracovniUzivatel, string $idFormulare = ''): string
    {
        return sprintf(
            '<input type="hidden" name="%s" value="%d"%s>',
            self::POST_ID,
            $pracovniUzivatel?->id() ?? 0,
            $idFormulare !== ''
                ? ' form="' . htmlspecialchars($idFormulare) . '"'
                : '',
        );
    }

    /**
     * Doplní skryté pole z @see getSkrytePole() do každého formuláře v HTML stránky, který se odešle jako POST:
     * na začátek formuláře s `method="post"` a před tlačítko s `formmethod="post"` (to pošle jako POST i formulář,
     * který by jinak šel přes GET).
     */
    public static function doplnDoFormularu(string $html, ?\Uzivatel $pracovniUzivatel): string
    {
        $atributy = self::ATRIBUTY;
        $bezZnacek = self::BEZ_ZNACEK;

        return preg_replace_callback(
            // komentář, obsah bez značek, hledaná značka, a ostatní značky celé, aby se nehledalo v jejich atributech;
            // neukončený komentář nebo skript sahá do konce stránky. Vše posesivně: dlouhý skript by jinak vyčerpal
            // limit backtrackingu PCRE
            <<<REGEX
            ~
                <!--(?:-?>|(?:[^-]++|-(?!-!?>))*+(?:--!?>)?)
              | <(?<bezZnacek>{$bezZnacek})(?=[\s/>]){$atributy}>(?:[^<]++|<(?!/(?P=bezZnacek)[\s/>]))*+
              | <(?<znacka>form|button|input)(?=[\s/>])(?<atributy>{$atributy})>
              | </?[a-z][^\s/>]*+{$atributy}>
            ~ix
            REGEX,
            static function (array $shoda) use ($pracovniUzivatel): string {
                $znacka = strtolower($shoda['znacka'] ?? '');
                if ($znacka === '') {
                    return $shoda[0];
                }
                $hodnoty = self::getAtributy($shoda['atributy']);
                if ($znacka === 'form') {
                    return self::isPost($hodnoty['method'] ?? null)
                           && !array_key_exists(self::BEZ_PRACOVNIHO_UZIVATELE, $hodnoty)
                        ? $shoda[0] . self::getSkrytePole($pracovniUzivatel)
                        : $shoda[0];
                }

                // tlačítko patří formuláři, ve kterém leží, nebo tomu z atributu form; pole před ním patří témuž
                return self::isPost($hodnoty['formmethod'] ?? null)
                    ? self::getSkrytePole($pracovniUzivatel, $hodnoty['form'] ?? '') . $shoda[0]
                    : $shoda[0];
            },
            $html,
        ) ?? throw new \RuntimeException('Formuláře stránky se nepodařilo projít: ' . preg_last_error_msg());
    }

    /**
     * Odmítne POST, který nese ID jiného pracovního uživatele než současného: ohlásí chybu a skončí.
     *
     * @param array<string, mixed> $post
     */
    public static function hlidej(array $post, ?\Uzivatel $pracovniUzivatel): void
    {
        if (!array_key_exists(self::POST_ID, $post)) {
            return;
        }
        if ((int) $post[self::POST_ID] === ($pracovniUzivatel?->id() ?? 0)) {
            return;
        }
        chyba('Nic se nezměnilo, formulář patřil jinému uživateli, než se kterým teď pracuješ. Pracovní uživatel se nejspíš mezitím přepnul v jiné záložce prohlížeče. Zkontroluj, s kým pracuješ, a pošli formulář znovu.');
    }

    /**
     * Atributy značky jako prohlížeč: názvy malými písmeny, hodnota bez entit, z opakovaného atributu platí první.
     *
     * @return array<string, string>
     */
    private static function getAtributy(string $atributy): array
    {
        preg_match_all(
            <<<'REGEX'
            ~
                (?<nazev>[^\s/>][^\s/>=]*+)
                (?:\s*+=\s*+(?:"(?<dvojite>[^"]*+)"|'(?<jednoduche>[^']*+)'|(?<bez>[^\s>]*+)))?
            ~x
            REGEX,
            $atributy,
            $shody,
            PREG_SET_ORDER | PREG_UNMATCHED_AS_NULL,
        );
        $hodnoty = [];
        foreach ($shody as $shoda) {
            $hodnoty[strtolower($shoda['nazev'])] ??= html_entity_decode(
                $shoda['dvojite'] ?? $shoda['jednoduche'] ?? $shoda['bez'] ?? '',
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            );
        }

        return $hodnoty;
    }

    /**
     * Hodnota `method` a `formmethod` je výčet, prohlížeč ji porovná bez ohledu na velikost písmen, ale celou.
     */
    private static function isPost(?string $metoda): bool
    {
        return $metoda !== null && strtolower($metoda) === 'post';
    }
}
