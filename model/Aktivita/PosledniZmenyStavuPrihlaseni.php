<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

class PosledniZmenyStavuPrihlaseni
{
    /**
     * @var int
     */
    private $idAktivity;
    /**
     * @var ZmenaStavuPrihlaseni[]
     */
    private $zmenyStavuPrihlaseni = [];

    /**
     * @param int $idAktivity
     */
    public function __construct(int $idAktivity) {
        $this->idAktivity = $idAktivity;
    }

    public function addPosledniZmenaStavuPrihlaseni(ZmenaStavuPrihlaseni $zmenaStavuPrihlaseni) {
        $this->zmenyStavuPrihlaseni[] = $zmenaStavuPrihlaseni;
    }

    public function getIdAktivity(): int {
        return $this->idAktivity;
    }

    /**
     * @return ZmenaStavuPrihlaseni[]
     */
    public function zmenyStavuPrihlaseni(): array {
        return $this->zmenyStavuPrihlaseni;
    }

}
