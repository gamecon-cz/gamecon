<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Jazyk;

use Gamecon\Jazyk\Jazyk;
use Gamecon\Jazyk\Preklad;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Translation\Formatter\IntlFormatter;
use Symfony\Component\Yaml\Yaml;

/**
 * Chybějící překlad nikde nespadne: web prostě ukáže češtinu nebo samotný klíč. Hlídáme to tady.
 * Chování samotného překladu (záloha na češtinu, escapování) je v PrekladTest nad fixture katalogy.
 */
class PrekladyKatalogTest extends TestCase
{
    /**
     * Kde se volá t('klic') nebo píše {t:klic}
     */
    private const array ADRESARE_S_KODEM = ['model', 'web', 'admin', 'nastaveni', 'symfony/src'];

    public function testVsechnyJazykyMajiStejneKlice(): void
    {
        $cesky = array_keys($this->katalog(Jazyk::CESTINA));
        foreach (Jazyk::cases() as $jazyk) {
            $klice = array_keys($this->katalog($jazyk));
            self::assertSame([], array_values(array_diff($cesky, $klice)), "V katalogu '{$jazyk->value}' chybí klíče.");
            self::assertSame([], array_values(array_diff($klice, $cesky)), "Katalog '{$jazyk->value}' má klíče navíc.");
        }
    }

    public function testKazdyPouzityKlicJeVKatalogu(): void
    {
        $chybejici = array_diff(array_keys($this->pouziteKlice()['klice']), array_keys($this->katalog(Jazyk::CESTINA)));

        self::assertSame([], array_values($chybejici));
    }

    public function testVKataloguNeniNicNepouziteho(): void
    {
        $nepouzite = array_diff(array_keys($this->katalog(Jazyk::CESTINA)), array_keys($this->pouziteKlice()['klice']));

        self::assertSame([], array_values($nepouzite));
    }

    /**
     * Klíč skládaný za běhu (`t('menu.' . $x)`) by hledání klíčů nenašlo a hlídání katalogu by nad ním bylo slepé.
     */
    public function testKlicPrekladuNeniSkladanyZaBehu(): void
    {
        self::assertSame([], $this->pouziteKlice()['skladane']);
    }

    public function testHledaniKlicuVPhpPoznaVolaniAPreskociKomentare(): void
    {
        $kod = <<<'PHP'
        <?php
        t('menu.program');
        \t("menu.odhlasit", [], $jazyk);
        Preklad::t('paticka.kontakt');
        \Gamecon\Jazyk\Preklad::t( 'paticka.sponzori' );
        t(klic: 'jazyk.prepnout');
        // t('v.komentari');
        /* Preklad::t('v.komentari') */
        $sablona->t('metoda.objektu');
        Jinak::t('jina.trida');
        function t(string $klic) {}
        echo "t('v.retezci')";
        t('menu.' . $polozka);
        t("menu.$polozka");
        t($klic);
        PHP;

        $nalez = self::klicePhp($kod);

        self::assertSame(
            ['menu.program', 'menu.odhlasit', 'paticka.kontakt', 'paticka.sponzori', 'jazyk.prepnout'],
            $nalez['klice'],
        );
        self::assertSame([13, 14], $nalez['skladane'], 'Řádky s klíčem skládaným za běhu.');
    }

    public function testPrekladyMajiStejneArgumentyAJsouPlatneZpravyIcu(): void
    {
        $cesky = $this->katalog(Jazyk::CESTINA);
        foreach (Jazyk::cases() as $jazyk) {
            foreach ($this->katalog($jazyk) as $klic => $text) {
                self::assertInstanceOf(
                    \MessageFormatter::class,
                    \MessageFormatter::create($jazyk->value, $text),
                    "Text '{$klic}' v '{$jazyk->value}' není platná zpráva ICU.",
                );
                self::assertSame(
                    array_keys(self::argumenty($cesky[$klic] ?? '')),
                    array_keys(self::argumenty($text)),
                    "Text '{$klic}' v '{$jazyk->value}' má jiné argumenty než česky.",
                );
            }
        }
    }

    /**
     * Každou zprávu zformátuje s ukázkovými hodnotami argumentů (u plural a select i pro každou větev).
     * Složená závorka ve výstupu znamená argument, který se nedosadil: typicky apostrof v ICU,
     * `'{den}'` je doslovný text „{den}“, ne den v uvozovkách.
     */
    public function testZformatovanyTextNemaSlozeneZavorky(): void
    {
        $formatter = new IntlFormatter();
        foreach (Jazyk::cases() as $jazyk) {
            foreach ($this->katalog($jazyk) as $klic => $text) {
                foreach (self::sadyParametru(self::argumenty($text)) as $parametry) {
                    self::assertDoesNotMatchRegularExpression(
                        '~[{}]~',
                        $formatter->formatIntl($text, $jazyk->value, $parametry),
                        "Text '{$klic}' v '{$jazyk->value}' má po zformátování složenou závorku. Apostrof před `{` dělá z argumentu doslovný text, uvozovky piš jako „…“ nebo ''.",
                    );
                }
            }
        }
    }

    public function testArgumentyZpravyJsouJenNazvyNeTextVetvi(): void
    {
        self::assertSame(['pohlavi'], array_keys(self::argumenty('{pohlavi, select, f {ona} other {on}}')));
        self::assertSame(['pohlavi'], array_keys(self::argumenty('{pohlavi, select, f {she} other {he}}')));
        self::assertSame(
            ['den', 'pocet'],
            array_keys(self::argumenty('{pocet, plural, offset:1 one {# místo v {den}} other {# míst v {den}}}')),
        );
        self::assertSame(['cena'], array_keys(self::argumenty('{cena, number, integer} Kč')));
        self::assertSame([], array_keys(self::argumenty("Rock'n'roll '{den}'")), 'Apostrof před `{` je doslovný text.');
        self::assertSame(['den'], array_keys(self::argumenty("Den ''{den}''")), "`''` je samotný apostrof.");
    }

    public function testVKataloguNeniHtml(): void
    {
        foreach (Jazyk::cases() as $jazyk) {
            foreach ($this->katalog($jazyk) as $klic => $text) {
                self::assertDoesNotMatchRegularExpression(
                    '~<[a-z/!]|&[a-z#][a-z0-9]*;~i',
                    $text,
                    "Text '{$klic}' v '{$jazyk->value}' obsahuje HTML. t() vrací prostý text, kdo ho vkládá do HTML, escapuje ho, ukázalo by se doslova.",
                );
            }
        }
    }

    /**
     * Preact má vlastní slovník (ui/src/i18n). Klíč, který je v obou, musí mít stejný text:
     * titulek programu skládá po F5 PHP, po přepnutí dne Preact.
     */
    public function testTextySdileneSPreactemSeShoduji(): void
    {
        foreach (Jazyk::cases() as $jazyk) {
            $preact = $this->slovnikPreactu($jazyk);
            $sdilene = array_intersect_key($this->katalog($jazyk), $preact);
            self::assertNotSame([], $sdilene, "Ve slovníku Preactu '{$jazyk->value}' se nenašel žádný sdílený klíč, test by nic nehlídal.");
            foreach ($sdilene as $klic => $text) {
                self::assertSame($text, $preact[$klic], "Text '{$klic}' v '{$jazyk->value}' se v PHP a v Preactu liší.");
            }
        }
    }

    /**
     * @return array<string, string> plochý klíč => text
     */
    private function katalog(Jazyk $jazyk): array
    {
        $ploche = [];
        $zplosti = static function (array $uzel, string $prefix) use (&$zplosti, &$ploche): void {
            foreach ($uzel as $klic => $hodnota) {
                if (is_array($hodnota)) {
                    $zplosti($hodnota, $prefix . $klic . '.');
                } else {
                    $ploche[$prefix . $klic] = (string) $hodnota;
                }
            }
        };
        $zplosti(Yaml::parseFile(Preklad::souborKatalogu($jazyk)) ?? [], '');

        return $ploche;
    }

    /**
     * @return array<string, string> klíč => text ze `ui/src/i18n/{jazyk}.ts`
     */
    private function slovnikPreactu(Jazyk $jazyk): array
    {
        $kod = (string) file_get_contents(__DIR__ . '/../../../ui/src/i18n/' . $jazyk->value . '.ts');
        preg_match_all('~^\s*"([^"]+)":\s*("(?:[^"\\\\]|\\\\.)*"),?\s*$~m', $kod, $nalezy, PREG_SET_ORDER);
        $slovnik = [];
        foreach ($nalezy as [, $klic, $text]) {
            $slovnik[$klic] = (string) json_decode($text, flags: JSON_THROW_ON_ERROR);
        }
        self::assertNotSame([], $slovnik, "Slovník Preactu '{$jazyk->value}' se nepodařilo přečíst.");

        return $slovnik;
    }

    /**
     * Klíče z volání t() v PHP a z {t:…} v šablonách XTemplate.
     *
     * @return array{klice: array<string, true>, skladane: list<string>} skladane = `soubor:řádek` s klíčem skládaným za běhu
     */
    private function pouziteKlice(): array
    {
        $koren = realpath(__DIR__ . '/../../..');
        $klice = [];
        $skladane = [];
        $soubory = (new Finder())->files()
            ->in(array_map(static fn (string $adresar) => $koren . '/' . $adresar, self::ADRESARE_S_KODEM))
            ->name(['*.php', '*.xtpl'])
            ->exclude(['files', 'soubory']);
        foreach ($soubory as $soubor) {
            $kod = $soubor->getContents();
            if ($soubor->getExtension() === 'xtpl') {
                preg_match_all('~\{t:([a-z][a-z0-9_]*(?:\.[a-z][a-z0-9_]*)+)\}~', $kod, $sablony);
                $nalezene = $sablony[1];
            } else {
                $nalez = self::klicePhp($kod);
                $nalezene = $nalez['klice'];
                foreach ($nalez['skladane'] as $radek) {
                    $skladane[] = $soubor->getRelativePathname() . ':' . $radek;
                }
            }
            foreach ($nalezene as $klic) {
                $klice[$klic] = true;
            }
        }
        self::assertNotSame([], $klice, 'Hledání klíčů v kódu nic nenašlo, test by nic nehlídal.');

        return [
            'klice'    => $klice,
            'skladane' => $skladane,
        ];
    }

    /**
     * Klíče z volání `t('…')`, `\t("…")` a `Preklad::t('…')` podle tokenů PHP, takže komentáře
     * a texty v řetězcích se nepočítají a metody jiných tříd (`$x->t()`, `Jinak::t()`) taky ne.
     *
     * @return array{klice: list<string>, skladane: list<int>} skladane = řádky, kde klíč začíná literálem, ale není jen literál
     */
    private static function klicePhp(string $kod): array
    {
        $tokeny = array_values(array_filter(
            \PhpToken::tokenize($kod),
            static fn (\PhpToken $token) => ! $token->isIgnorable(),
        ));
        $klice = [];
        $skladane = [];
        foreach ($tokeny as $index => $token) {
            if (! self::jeVolaniPrekladu($tokeny, $index)) {
                continue;
            }
            $indexArgumentu = $index + 2;
            // pojmenovaný argument `t(klic: '…')`
            if (($tokeny[$indexArgumentu] ?? null)?->is(T_STRING) && ($tokeny[$indexArgumentu + 1] ?? null)?->text === ':') {
                $indexArgumentu += 2;
            }
            $argument = $tokeny[$indexArgumentu] ?? null;
            if ($argument === null) {
                continue;
            }
            $zaArgumentem = $tokeny[$indexArgumentu + 1] ?? null;
            if ($argument->is(T_CONSTANT_ENCAPSED_STRING) && in_array($zaArgumentem?->text, [',', ')'], true)) {
                $klice[] = substr($argument->text, 1, -1);
            } elseif ($argument->is(T_CONSTANT_ENCAPSED_STRING) || $argument->text === '"') {
                $skladane[] = $argument->line;
            }
        }

        return [
            'klice'    => $klice,
            'skladane' => $skladane,
        ];
    }

    /**
     * @param list<\PhpToken> $tokeny bez mezer a komentářů
     */
    private static function jeVolaniPrekladu(array $tokeny, int $index): bool
    {
        $token = $tokeny[$index];
        if (($tokeny[$index + 1] ?? null)?->text !== '(') {
            return false;
        }
        if ($token->is(T_NAME_FULLY_QUALIFIED)) {
            return $token->text === '\t';
        }
        if (! $token->is(T_STRING) || $token->text !== 't') {
            return false;
        }
        $predchozi = $tokeny[$index - 1] ?? null;
        if ($predchozi === null) {
            return true;
        }
        if ($predchozi->is(T_DOUBLE_COLON)) {
            $trida = $tokeny[$index - 2] ?? null;

            return $trida !== null
                && $trida->is([T_STRING, T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED])
                && preg_match('~(^|\\\\)Preklad$~', $trida->text) === 1;
        }

        // deklarace funkce nebo metody a volání metody objektu
        return ! $predchozi->is([T_FUNCTION, T_NEW, T_OBJECT_OPERATOR, T_NULLSAFE_OBJECT_OPERATOR]);
    }

    /**
     * Argumenty zprávy ICU s ukázkovými hodnotami. Prochází i těla větví plural a select,
     * jejich text (`{ona}`) ale argument není.
     *
     * @return array<string, list<mixed>> název argumentu => hodnoty, se kterými zprávu zkusit zformátovat
     */
    private static function argumenty(string $zprava): array
    {
        $argumenty = [];
        $pozice = 0;
        self::projdiZpravu($zprava, $pozice, $argumenty);
        ksort($argumenty);

        return $argumenty;
    }

    /**
     * Projde text zprávy od $pozice po konec, nebo po `}`, která uzavírá větev (na té skončí).
     *
     * @param array<string, list<mixed>> $argumenty
     */
    private static function projdiZpravu(string $zprava, int &$pozice, array &$argumenty): void
    {
        $delka = strlen($zprava);
        while ($pozice < $delka && $zprava[$pozice] !== '}') {
            if ($zprava[$pozice] === "'") {
                $pozice = self::zaApostrofem($zprava, $pozice);
            } elseif ($zprava[$pozice] === '{') {
                ++$pozice;
                self::projdiArgument($zprava, $pozice, $argumenty);
            } else {
                ++$pozice;
            }
        }
    }

    /**
     * Pozice za `{nazev}`, `{nazev, typ, styl}` nebo `{nazev, plural|select, klic {zprava} …}`.
     *
     * @param array<string, list<mixed>> $argumenty
     */
    private static function projdiArgument(string $zprava, int &$pozice, array &$argumenty): void
    {
        $delka = strlen($zprava);
        $nazev = trim(self::doZnaku($zprava, $pozice, [',', '}']));
        $typ = '';
        if (($zprava[$pozice] ?? '') === ',') {
            ++$pozice;
            $typ = trim(self::doZnaku($zprava, $pozice, [',', '}']));
        }
        $vetve = [];
        if (($zprava[$pozice] ?? '') === ',') {
            ++$pozice;
            if (in_array($typ, ['plural', 'select', 'selectordinal'], true)) {
                while ($pozice < $delka && $zprava[$pozice] !== '}') {
                    // `offset:1 one` – klíč větve je poslední slovo
                    $selektor = preg_split('~\s+~', trim(self::doZnaku($zprava, $pozice, ['{', '}'])));
                    if (($zprava[$pozice] ?? '') !== '{') {
                        break;
                    }
                    $vetve[] = (string) end($selektor);
                    ++$pozice;
                    self::projdiZpravu($zprava, $pozice, $argumenty);
                    ++$pozice; // `}` větve
                }
            } else {
                // styl (`{cena, number, integer}`) může mít vlastní závorky
                $hloubka = 0;
                while ($pozice < $delka && ($zprava[$pozice] !== '}' || $hloubka > 0)) {
                    if ($zprava[$pozice] === "'") {
                        $pozice = self::zaApostrofem($zprava, $pozice);
                        continue;
                    }
                    $hloubka += match ($zprava[$pozice]) {
                        '{'     => 1,
                        '}'     => -1,
                        default => 0,
                    };
                    ++$pozice;
                }
            }
        }
        ++$pozice; // `}` argumentu
        $argumenty[$nazev] ??= match ($typ) {
            'plural', 'selectordinal' => [0, 1, 2, 5, 1.5],
            'select' => $vetve,
            'date', 'time' => [new \DateTimeImmutable('2026-07-17 14:00')],
            ''      => ['X'],
            default => [3],
        };
    }

    /**
     * Apostrof uvozuje doslovný text, jen když za ním je syntaktický znak; `''` je apostrof sám.
     */
    private static function zaApostrofem(string $zprava, int $pozice): int
    {
        $dalsi = $zprava[$pozice + 1] ?? '';
        if ($dalsi === "'") {
            return $pozice + 2;
        }
        if (! in_array($dalsi, ['{', '}', '#', '|'], true)) {
            return $pozice + 1;
        }
        $delka = strlen($zprava);
        for ($pozice += 2; $pozice < $delka; ++$pozice) {
            if ($zprava[$pozice] !== "'") {
                continue;
            }
            if (($zprava[$pozice + 1] ?? '') !== "'") {
                return $pozice + 1;
            }
            ++$pozice; // `''` uvnitř doslovného textu
        }

        return $pozice;
    }

    /**
     * Text od $pozice po první ze znaků, $pozice skončí na tom znaku.
     *
     * @param list<string> $znaky
     */
    private static function doZnaku(string $zprava, int &$pozice, array $znaky): string
    {
        $zacatek = $pozice;
        $delka = strlen($zprava);
        while ($pozice < $delka && ! in_array($zprava[$pozice], $znaky, true)) {
            ++$pozice;
        }

        return substr($zprava, $zacatek, $pozice - $zacatek);
    }

    /**
     * Výchozí sada (první ukázková hodnota každého argumentu) a k ní sady, které mění vždy jeden argument.
     *
     * @param array<string, list<mixed>> $argumenty
     *
     * @return list<array<string, mixed>>
     */
    private static function sadyParametru(array $argumenty): array
    {
        $vychozi = array_map(static fn (array $hodnoty) => $hodnoty[0] ?? 'X', $argumenty);
        $sady = [$vychozi];
        foreach ($argumenty as $nazev => $hodnoty) {
            foreach ($hodnoty as $hodnota) {
                $sady[] = [
                    ...$vychozi,
                    $nazev => $hodnota,
                ];
            }
        }

        return $sady;
    }
}
