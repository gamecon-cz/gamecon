<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

class PosledniZmenyStavuPrihlaseni
{
    /**
     * @var ZmenaStavuPrihlaseni[]
     */
    private $zmenyStavuPrihlaseni = [];

    public function addPosledniZmenaStavuPrihlaseni(ZmenaStavuPrihlaseni $zmenaStavuPrihlaseni) {
        $this->zmenyStavuPrihlaseni[] = $zmenaStavuPrihlaseni;
    }

    /**
     * @return ZmenaStavuPrihlaseni[]
     */
    public function zmenyStavuPrihlaseni(): array {
        return $this->zmenyStavuPrihlaseni;
    }

    public function posledniZmenaStavuPrihlaseni(): ?ZmenaStavuPrihlaseni {
        $zmeny = $this->zmenyStavuPrihlaseni();
        if (!$zmeny) {
            return null;
        }
        usort($zmeny, static function (ZmenaStavuPrihlaseni $nejakaZmena, ZmenaStavuPrihlaseni $jinaZmena) {
            return $nejakaZmena->idLogu() <=> $jinaZmena->idLogu();
        });
        return end($zmeny);
    }

}
