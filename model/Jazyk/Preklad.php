<?php

declare(strict_types=1);

namespace Gamecon\Jazyk;

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\MessageCatalogue;
use Symfony\Component\Translation\Translator;

/**
 * Texty rozhraní v jazyce požadavku.
 *
 * Katalogy jsou ve formátu zpráv ICU (`symfony/translations/messages+intl-icu.{jazyk}.yaml`),
 * takže zvládnou české množné číslo i rod: `{pocet, plural, one {# místo} few {# místa} other {# míst}}`.
 * Legacy kód si skládá vlastní Translator, aby kvůli textům nemusel bootovat Symfony kernel;
 * katalogy jsou ale tytéž, které čte Symfony (`framework.translator.default_path`).
 *
 * Chybějící překlad spadne na češtinu, chybějící klíč vrátí sám sebe. Obojí hlídá PrekladyKatalogTest.
 */
final class Preklad
{
    public const DOMENA = 'messages';

    private static ?Translator $translator = null;

    /** Adresář s katalogy, když ho test přesměroval na fixture. null = katalogy webu. */
    private static ?string $adresarKataloguZTestu = null;

    /**
     * Vrací PROSTÝ TEXT, ne HTML. Parametry jsou taky prostý text a vkládají se bez úprav.
     * Kdo výsledek vkládá do HTML, escapuje ho (`htmlspecialchars()`), a to celý výsledek včetně
     * parametrů; `{t:klic}` v šabloně to dělá sám. HTML proto do katalogu nepatří.
     *
     * @param array<string, mixed> $parametry hodnoty pro `{nazev}` v textu (prostý text, ne HTML)
     */
    public static function t(string $klic, array $parametry = [], ?Jazyk $jazyk = null): string
    {
        return self::translator()->trans(
            $klic,
            $parametry,
            self::DOMENA,
            ($jazyk ?? AktualniJazyk::jazyk())->value,
        );
    }

    public static function adresarKatalogu(): string
    {
        return self::$adresarKataloguZTestu ?? __DIR__ . '/../../symfony/translations';
    }

    public static function souborKatalogu(Jazyk $jazyk): string
    {
        return self::adresarKatalogu() . '/' . self::DOMENA . MessageCatalogue::INTL_DOMAIN_SUFFIX . '.' . $jazyk->value . '.yaml';
    }

    /**
     * Pro testy: katalogy z jiného adresáře (fixture se soubory `messages+intl-icu.{jazyk}.yaml` pro všechny jazyky).
     * null vrátí katalogy webu. Zahodí sestavený Translator, takže platí hned od dalšího `t()`.
     */
    public static function setAdresarKatalogu(?string $adresar): void
    {
        self::$adresarKataloguZTestu = $adresar;
        self::$translator = null;
    }

    private static function translator(): Translator
    {
        if (self::$translator === null) {
            // Změněný YAML pozná Translator podle času změny katalogů v klíči cache. Debug režim by to
            // poznal taky, ale při každém požadavku by načítal a rozbaloval metadata cache.
            $cacheVary = [
                'zmeny_katalogu' => array_map(
                    static fn (Jazyk $jazyk) => filemtime(self::souborKatalogu($jazyk)),
                    Jazyk::cases(),
                ),
            ];
            if (self::$adresarKataloguZTestu !== null) {
                // Fixture z testu nesmí sdílet cache s katalogy webu: git checkout dá souborům stejný čas změny,
                // klíč cache by pak byl stejný. Katalogům webu klíč zůstává, jaký byl.
                $cacheVary['adresar_katalogu'] = self::$adresarKataloguZTestu;
            }
            $translator = new Translator(
                locale: Jazyk::vychozi()->value,
                cacheDir: defined('SPEC')
                    ? SPEC . '/preklady'
                    : null,
                debug: false,
                cacheVary: $cacheVary,
            );
            $translator->setFallbackLocales([Jazyk::vychozi()->value]);
            $translator->addLoader('yaml', new YamlFileLoader());
            foreach (Jazyk::cases() as $jazyk) {
                $translator->addResource(
                    'yaml',
                    self::souborKatalogu($jazyk),
                    $jazyk->value,
                    self::DOMENA . MessageCatalogue::INTL_DOMAIN_SUFFIX,
                );
            }
            self::$translator = $translator;
        }

        return self::$translator;
    }
}
