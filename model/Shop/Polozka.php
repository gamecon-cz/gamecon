<?php
declare(strict_types=1);

namespace Gamecon\Shop;

use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

class Polozka
{
    private int                      $idPredmetu;
    private string                   $nazev;
    private float                    $cena;
    private float                    $suma;
    private int                      $modelRok;
    private ?DateTimeImmutableStrict $naposledyKoupenoKdy;
    private float                    $prodanoKusu;
    private float                    $vyrobenoKusu;
    private ?DateTimeImmutableStrict $nabizetDo;
    private float                    $zbyvaKusu;
    private int                      $idTypu;
    private int                      $stav;
    private bool                     $jeLetosniHlavni;

    public function __construct(array $hodnoty)
    {
        $this->idPredmetu          = (int)$hodnoty['id_predmetu'];
        $this->nazev               = (string)$hodnoty['nazev'];
        $this->cena                = (float)$hodnoty['cena_aktualni'];
        $this->suma                = (float)$hodnoty['suma'];
        $this->modelRok            = (int)$hodnoty['model_rok'];
        $this->naposledyKoupenoKdy = $hodnoty['naposledy_koupeno_kdy']
            ? new DateTimeImmutableStrict($hodnoty['naposledy_koupeno_kdy'])
            : null;
        $this->prodanoKusu         = (float)$hodnoty['prodano_kusu'];
        $this->vyrobenoKusu        = (float)$hodnoty['kusu_vyrobeno'];
        $this->nabizetDo           = $hodnoty['nabizet_do']
            ? new DateTimeImmutableStrict($hodnoty['nabizet_do'])
            : null;
        $this->zbyvaKusu           = $this->vyrobenoKusu - $this->prodanoKusu;
        $this->idTypu              = (int)$hodnoty['typ'];
        $this->stav                = (int)$hodnoty['stav'];
        $this->jeLetosniHlavni     = (bool)$hodnoty['je_letosni_hlavni'];
    }

    public function idPredmetu(): ?int
    {
        return $this->idPredmetu;
    }

    public function nazev(): string
    {
        return $this->nazev;
    }

    public function cena(): float
    {
        return $this->cena;
    }

    public function suma(): float
    {
        return $this->suma;
    }

    public function modelRok(): int
    {
        return $this->modelRok;
    }

    public function prodanoKusu(): float
    {
        return $this->prodanoKusu;
    }

    public function naposledyKoupenoKdy(): ?DateTimeImmutableStrict
    {
        return $this->naposledyKoupenoKdy;
    }

    public function zbyvaKusu(): float
    {
        return $this->zbyvaKusu;
    }

    public function vyrobenoKusu()
    {
        return $this->vyrobenoKusu;
    }

    public function idTypu(): int
    {
        return $this->idTypu;
    }

    public function nabizetDo(): ?DateTimeImmutableStrict
    {
        return $this->nabizetDo;
    }

    public function stav(): int
    {
        return $this->stav;
    }

    public function jeLetosniHlavni(): bool
    {
        return $this->jeLetosniHlavni;
    }

    public function doKdyNabizetDleNastaveni(SystemoveNastaveni $systemoveNastaveni): ?DateTimeImmutableStrict
    {
        return match ($this->idTypu()) {
            TypPredmetu::JIDLO   => $systemoveNastaveni->prodejJidlaDo(),
            TypPredmetu::TRICKO  => $systemoveNastaveni->prodejTricekDo(),
            TypPredmetu::PREDMET => $systemoveNastaveni->prodejPredmetuBezTricekDo(),
            default              => null,
        };
    }

}
