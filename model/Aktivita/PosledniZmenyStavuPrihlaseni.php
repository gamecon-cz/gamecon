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
        $predchoziZmenyStavuPrihlaseni = $this->zmenyStavuPrihlaseni();
        if ($predchoziZmenyStavuPrihlaseni) {
            $casPosledniZmenyStavuPrihlaseni = reset($predchoziZmenyStavuPrihlaseni)->casZmeny();
            if ($casPosledniZmenyStavuPrihlaseni <> $zmenaStavuPrihlaseni->casZmeny()) {
                throw new \LogicException(
                    sprintf(
                        'Predchozi zmeny stavu prihlaseni jsou k casu %s, nove pridavana zmena je ale s casem %s. Vsechny posledni zmeny by meli byt ze stejne chvile.',
                        $casPosledniZmenyStavuPrihlaseni->format(DATE_ATOM),
                        $zmenaStavuPrihlaseni->casZmeny()->format(DATE_ATOM)
                    )
                );
            }
        }
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
