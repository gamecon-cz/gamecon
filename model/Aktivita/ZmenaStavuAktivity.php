<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

class ZmenaStavuAktivity
{
    private const AKTIVOVANA_JS = 'aktivovana';
    private const PROBEHNUTA_JS = 'probehnuta';
    private const SYSTEMOVA_JS = 'systemova';
    private const PUBLIKOVANA_JS = 'publikovana';
    private const PRIPRAVENA_JS = 'pripravena';
    private const UZAVRENA_JS = 'uzavrena';

    public static function vytvorZDatDatabaze(
        int                $idAktivity,
        int                $idLogu,
        \DateTimeImmutable $casZmeny,
        int                $stavAktivity/** @see \Stav */
    ): self {
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

    public function __construct(int $idAktivity, int $idLogu, \DateTimeImmutable $casZmeny, int $stavAktivity) {
        if ($stavAktivity && !\Stav::jeZnamy($stavAktivity)) {
            throw new \LogicException('Neznamy stav prihlaseni ' . var_export($stavAktivity, true));
        }
        $this->idAktivity = $idAktivity;
        $this->idLogu = $idLogu;
        $this->casZmeny = $casZmeny;
        $this->stavAktivity = $stavAktivity;
    }

    public function idAktivity(): int {
        return $this->idAktivity;
    }

    public function idLogu(): int {
        return $this->idLogu;
    }

    public function casZmeny(): \DateTimeImmutable {
        return $this->casZmeny;
    }

    public function stavAktivity(): int {
        return $this->stavAktivity;
    }

    public function casZmenyProJs(): string {
        return $this->casZmeny()->format(DATE_ATOM);
    }

    /**
     * Oddělíme konstanty, závislé na PHP a MySQL enum, od JavaScriptu tím, že je tady přemapujeme.
     * Změna hodnoty konstanty v PHP tak neohrozí funkčnost JavaScriptové logiky.
     * @return null|string
     */
    public function stavAktivityProJs(): ?string {
        switch ($this->stavAktivity()) {
            case \Stav::AKTIVOVANA :
                return self::AKTIVOVANA_JS;
            case \Stav::PROBEHNUTA :
                return self::PROBEHNUTA_JS;
            case \Stav::SYSTEMOVA :
                return self::SYSTEMOVA_JS;
            case \Stav::PUBLIKOVANA :
                return self::PUBLIKOVANA_JS;
            case \Stav::PRIPRAVENA :
                return self::PRIPRAVENA_JS;
            case \Stav::UZAVRENA :
                return self::UZAVRENA_JS;
            default :
                return null; // nějaký pro JS nezajímavý stav
        }
    }
}
