<?php

namespace Gamecon\SystemoveNastaveni;

use Gamecon\Cas\DateTimeGamecon;

interface ZdrojVlnAktivit
{
    public function prvniVlnaKdy(): DateTimeGamecon;

    public function druhaVlnaKdy(): DateTimeGamecon;

    public function tretiVlnaKdy(): DateTimeGamecon;
}
