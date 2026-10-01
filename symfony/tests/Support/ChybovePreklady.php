<?php

declare(strict_types=1);

namespace App\Tests\Support;

use Symfony\Component\Translation\Loader\YamlFileLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The real error catalogue for objects a test builds by hand, so the messages it checks are the ones users get.
 */
final class ChybovePreklady
{
    public static function translator(): TranslatorInterface
    {
        static $translator = null;
        if ($translator === null) {
            $translator = new Translator('cs');
            $translator->addLoader('yaml', new YamlFileLoader());
            $translator->addResource('yaml', __DIR__ . '/../../translations/errors.cs.yaml', 'cs', 'errors');
        }

        return $translator;
    }
}
