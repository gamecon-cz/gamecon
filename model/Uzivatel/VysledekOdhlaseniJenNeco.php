<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

class VysledekOdhlaseniJenNeco
{
    private ?int $odhlasenoUbytovani = null;
    private ?int $odhlasenoLarpu = null;
    private ?int $odhlasenoRpg = null;
    private ?int $odhlasenoOstatnichAktivit = null;

    public function celkemOdhlaseno(): int {
        return ($this->odhlasenoUbytovani() ?? 0)
            + ($this->odhlasenoLarpu() ?? 0)
            + ($this->odhlasenoRpg() ?? 0)
            + ($this->odhlasenoOstatnichAktivit() ?? 0);
    }

    public function jesteNecoNeodhlasovano(): bool {
        return $this->odhlasenoUbytovani() === null
            || $this->odhlasenoLarpu() === null
            || $this->odhlasenoRpg() === null
            || $this->odhlasenoOstatnichAktivit() === null;
    }

    public function odhlasenoUbytovani(): ?int {
        return $this->odhlasenoUbytovani;
    }

    public function nastavOdhlasenoUbytovani(int $odhlasenoUbytovani): void {
        $this->odhlasenoUbytovani = $odhlasenoUbytovani;
    }

    public function odhlasenoLarpu(): ?int {
        return $this->odhlasenoLarpu;
    }

    public function nastavOdhlasenoLarpu(int $odhlasenoLarpu): void {
        $this->odhlasenoLarpu = $odhlasenoLarpu;
    }

    public function odhlasenoRpg(): ?int {
        return $this->odhlasenoRpg;
    }

    public function nastavOdhlasenoRpg(int $odhlasenoRpg): void {
        $this->odhlasenoRpg = $odhlasenoRpg;
    }

    public function odhlasenoOstatnichAktivit(): ?int {
        return $this->odhlasenoOstatnichAktivit;
    }

    public function nastavOdhlasenoOstatnichAktivit(int $odhlasenoOstatnichAktivit): void {
        $this->odhlasenoOstatnichAktivit = $odhlasenoOstatnichAktivit;
    }

}
