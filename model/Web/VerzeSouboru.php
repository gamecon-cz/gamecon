<?php

declare(strict_types=1);

namespace Gamecon\Web;

class VerzeSouboru
{
    /**
     * @var string
     */
    private $adresar;
    /**
     * @var string
     */
    private $pripona;

    public function __construct(string $adresar, string $pripona)
    {
        $this->adresar = rtrim($adresar, '/');
        $this->pripona = $pripona;
    }

    public function __call(string $nazev, array $arguments): string
    {
        $nazev = basename($nazev, '.' . $this->pripona);
        $cesta = $this->adresar . '/' . $nazev . '.' . $this->pripona;
        if (file_exists($cesta)) {
            return md5_file($cesta);
        }
        return uniqid('neznamySoubor-' . basename($cesta) . '-', true);
    }
}
