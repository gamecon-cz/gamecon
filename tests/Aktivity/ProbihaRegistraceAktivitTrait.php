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
                    rocnik: $original->rocnik(),
                    ted: $original->ted(),
                    prostredi: $original->prostredi(),
                    databazoveNastaveni: $original->databazoveNastaveni(),
                    rootAdresarProjektu: $original->rootAdresarProjektu(),
                    privateCacheDir: $original->privateCacheDir(),
                    kernel: $original->kernel(),
                    publicCacheDir: $original->publicCacheDir(),
                );
            }

            public function probihaRegistraceAktivit(): bool
            {
                return true;
            }
        };
    }
}
