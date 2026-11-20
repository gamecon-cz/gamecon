<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel\Exceptions;

/**
 * Hromadné odhlašování naplánované před vlnou aktivit se spustilo až po jejím začátku
 * (třeba zpožděný CRON) a odhlásilo by i aktivity přihlášené v nové vlně.
 */
class HromadneOdhlasovaniAzPoZacatkuVlny extends NevhodnyCasProHromadneOdhlasovani
{

}
