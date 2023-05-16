<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

class ZmenaStavuAktivity
{
    private const AKTIVOVANA_JS  = 'aktivovana';
    private const UZAVRENA_JS    = 'uzavrena';
    private const SYSTEMOVA_JS   = 'systemova';
    private const PUBLIKOVANA_JS = 'publikovana';
    private const PRIPRAVENA_JS  = 'pripravena';
    private const ZAMCENA_JS     = 'zamcena';

    public static function vytvorZDatDatabaze(
        int                $idAktivity,
        int                $idLogu,
        \DateTimeImmutable $casZmeny,
        int                $stavAktivity,/** @see StavAktivity */
    ): self
    {
        return new static($idAktivity, $idLogu, $casZmeny, $stavAktivity);
    }

    /** @var int */
    private $idAktivity;
    /** @var int */
    private $idLogu;
    /** @var \DateTimeImmutable */
    private $casZmeny;
    /** @var int */
    private $stavAktivity;

    public function __construct(int $idAktivity, int $idLogu, \DateTimeImmutable $casZmeny, int $stavAktivity)
    {
        if ($stavAktivity && !StavAktivity::jeZnamy($stavAktivity)) {
            throw new \LogicException('Neznamy stav prihlaseni ' . var_export($stavAktivity, true));
        }
        $this->idAktivity   = $idAktivity;
        $this->idLogu       = $idLogu;
        $this->casZmeny     = $casZmeny;
        $this->stavAktivity = $stavAktivity;
    }

    public function idAktivity(): int
    {
        return $this->idAktivity;
    }

    public function idLogu(): int
    {
        return $this->idLogu;
    }

    public function casZmeny(): \DateTimeImmutable
    {
        return $this->casZmeny;
    }

    public function stavAktivity(): int
    {
        return $this->stavAktivity;
    }

    public function casZmenyProJs(): string
    {
        return $this->casZmeny()->format(DATE_ATOM);
    }

    /**
     * Oddělíme konstanty, závislé na PHP a MySQL enum, od JavaScriptu tím, že je tady přemapujeme.
     * Změna hodnoty konstanty v PHP tak neohrozí funkčnost JavaScriptové logiky.
     * @return null|string
     */
    public function stavAktivityProJs(): ?string
    {
        switch ($this->stavAktivity()) {
            case StavAktivity::AKTIVOVANA :
                return self::AKTIVOVANA_JS;
            case StavAktivity::UZAVRENA :
                return self::UZAVRENA_JS;
            case StavAktivity::PUBLIKOVANA :
                return self::PUBLIKOVANA_JS;
            case StavAktivity::PRIPRAVENA :
                return self::PRIPRAVENA_JS;
            case StavAktivity::ZAMCENA :
                return self::ZAMCENA_JS;
            case StavAktivity::SYSTEMOVA :
                return self::SYSTEMOVA_JS;
            default :
                return null; // nějaký pro JS nezajímavý stav
        }
    }
}
