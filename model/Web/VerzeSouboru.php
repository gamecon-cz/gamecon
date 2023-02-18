<?php

declare(strict_types=1);

namespace Gamecon\Web;

class VerzeSouboru
{
    public function __construct(private string $adresar, private string $pripona) {
        $this->adresar = rtrim($adresar, '/');
        $this->pripona = ltrim('.', $pripona);
    }

    public function __call(string $nazev, array $arguments): string {
        return $this->verze($nazev);
    }

    public function verze(string $nazev): string {
        $nazev = basename($nazev, '.' . $this->pripona);
        $cesta = $this->adresar . '/' . $nazev . '.' . $this->pripona;
        if (file_exists($cesta)) {
            return md5_file($cesta);
        }
        return uniqid('neznamySoubor-' . basename($cesta) . '-', true);
    }
}
