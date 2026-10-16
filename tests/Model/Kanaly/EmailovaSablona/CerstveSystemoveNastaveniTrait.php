<?php

declare(strict_types=1);

namespace Gamecon\Tests\Model\Kanaly\EmailovaSablona;

use App\Kernel;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Prostredi\Prostredi;
use Gamecon\SystemoveNastaveni\DatabazoveNastaveni;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * A separate instance that reloads the records from the database. The shared one
 * from {@see SystemoveNastaveni::zGlobals()} keeps its changes in memory even after the test transaction
 * is rolled back, so a flag set in one test would leak into the next.
 */
trait CerstveSystemoveNastaveniTrait
{
    private function cerstveSystemoveNastaveni(): SystemoveNastaveni
    {
        return new SystemoveNastaveni(
            rocnik: ROCNIK,
            ted: new DateTimeImmutableStrict(),
            prostredi: Prostredi::Production,
            databazoveNastaveni: DatabazoveNastaveni::vytvorZGlobals(),
            rootAdresarProjektu: PROJECT_ROOT_DIR,
            privateCacheDir: SPEC,
            kernel: new Kernel('test', false),
            publicCacheDir: CACHE,
        );
    }
}
