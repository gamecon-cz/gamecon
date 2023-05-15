<?php

namespace Gamecon\Vyjimkovac;

interface Logovac
{
    public function zaloguj(\Throwable $throwable);
}
