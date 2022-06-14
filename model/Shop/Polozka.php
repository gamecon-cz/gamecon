<?php declare(strict_types=1);

namespace Gamecon\Shop;

use Gamecon\Cas\DateTimeCz;

class Polozka
{
    private $idPredmetu;
    private $nazev;
    private $cena;
    private $suma;
    private $modelRok;
    private $naposledyKoupenoKdy;
    private $prodanoKusu;
    private $vyrobenoKusu;
    private $zbyvaKusu;
    private $idTypu;

    public function __construct(array $hodnoty) {
        $this->idPredmetu = (int)$hodnoty['id_predmetu'];
        $this->nazev = (string)$hodnoty['nazev'];
        $this->cena = (float)$hodnoty['cena_aktualni'];
        $this->suma = (float)$hodnoty['suma'];
        $this->modelRok = (int)$hodnoty['model_rok'];
        $this->naposledyKoupenoKdy = $hodnoty['naposledy_koupeno_kdy']
            ? new DateTimeCz($hodnoty['naposledy_koupeno_kdy'])
            : null;
        $this->prodanoKusu = (float)$hodnoty['prodano_kusu'];
        $this->vyrobenoKusu = (float)$hodnoty['kusu_vyrobeno'];
        $this->zbyvaKusu = $this->vyrobenoKusu - $this->prodanoKusu;
        $this->idTypu = (int)$hodnoty['typ'];
    }

    public function idPredmetu(): ?int {
        return $this->idPredmetu;
    }

    public function nazev(): string {
        return $this->nazev;
    }

    public function cena(): float {
        return $this->cena;
    }

    public function suma(): float {
        return $this->suma;
    }

    public function modelRok(): int {
        return $this->modelRok;
    }

    public function prodanoKusu(): float {
        return $this->prodanoKusu;
    }

    public function naposledyKoupenoKdy(): ?DateTimeCz {
        return $this->naposledyKoupenoKdy;
    }

    public function zbyvaKusu(): float {
        return $this->zbyvaKusu;
    }

    public function vyrobenoKusu() {
        return $this->vyrobenoKusu;
    }

    public function idTypu(): int {
        return $this->idTypu;
    }
}
