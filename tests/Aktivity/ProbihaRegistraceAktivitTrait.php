<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

trait ProbihaRegistraceAktivitTrait
{
    private static function vytvorSystemoveNastaveni(): SystemoveNastaveni
    {
        $original = SystemoveNastaveni::zGlobals();
        return new class($original) extends SystemoveNastaveni {

            public function __construct(SystemoveNastaveni $original)
            {
                parent::__construct(
                    $original->rocnik(),
                    $original->ted(),
                    $original->jsmeNaBete(),
                    $original->jsmeNaLocale(),
                    $original->databazoveNastaveni(),
                    $original->rootAdresarProjektu(),
                    $original->cacheDir(),
                );
            }

            public function probihaRegistraceAktivit(): bool
            {
                return true;
            }
        };
    }
}
