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

    /**
     * @param array<string, mixed> $parametry hodnoty pro `{nazev}` v textu
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
        return __DIR__ . '/../../symfony/translations';
    }

    public static function souborKatalogu(Jazyk $jazyk): string
    {
        return self::adresarKatalogu() . '/' . self::DOMENA . MessageCatalogue::INTL_DOMAIN_SUFFIX . '.' . $jazyk->value . '.yaml';
    }

    private static function translator(): Translator
    {
        if (self::$translator === null) {
            // debug = true: katalog se přegeneruje, když se změní jeho YAML (stačí stat souboru)
            $translator = new Translator(
                locale: Jazyk::vychozi()->value,
                cacheDir: defined('SPEC')
                    ? SPEC . '/preklady'
                    : null,
                debug: true,
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
