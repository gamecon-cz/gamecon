<?php

namespace Gamecon\SystemoveNastaveni;

use Gamecon\Cas\DateTimeImmutableStrict;

interface ZdrojTed
{
    public function ted(): DateTimeImmutableStrict;
}
