<?php

declare(strict_types=1);

namespace Gamecon\Tests\Aktivity;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

trait ProbihaRegistraceAktivitTrait
{
    private static function vytvorSystemoveNastaveni(): SystemoveNastaveni
    {
        $original = SystemoveNastaveni::zGlobals();

        return new class($original) extends SystemoveNastaveni
        {

            public function __construct(SystemoveNastaveni $original)
            {
                parent::__construct(
                    rocnik: $original->rocnik(),
                    ted: $original->ted(),
                    jsmeNaBete: $original->jsmeNaBete(),
                    jsmeNaLocale: $original->jsmeNaLocale(),
                    databazoveNastaveni: $original->databazoveNastaveni(),
                    rootAdresarProjektu: $original->rootAdresarProjektu(),
                    cacheDir: $original->cacheDir(),
                );
            }

            public function probihaRegistraceAktivit(): bool
            {
                return true;
            }
        };
    }
}
