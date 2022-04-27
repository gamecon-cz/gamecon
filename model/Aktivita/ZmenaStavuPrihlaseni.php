<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

class ZmenaStavuPrihlaseni
{
    /**
     * @var int
     */
    private $idUzivatele;
    /**
     * @var \DateTimeInterface
     */
    private $casZmeny;
    /**
     * @var string
     */
    private $stavPrihlaseni;

    public function __construct(int $idUzivatele, \DateTimeInterface $casZmeny, string $stavPrihlaseni) {
        if (!AktivitaPrezenceTyp::jeZnamy($stavPrihlaseni)) {
            throw new \LogicException('Neznamy stav prihlaseni ' . var_export($stavPrihlaseni, true));
        }
        $this->idUzivatele = $idUzivatele;
        $this->casZmeny = $casZmeny;
        $this->stavPrihlaseni = $stavPrihlaseni;
    }

    public function idUzivatele(): int {
        return $this->idUzivatele;
    }

    public function casZmeny(): \DateTimeInterface {
        return $this->casZmeny;
    }

    public function stavPrihlaseni(): string {
        return $this->stavPrihlaseni;
    }
}
