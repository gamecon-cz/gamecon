<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

class PosledniZmenyStavuAktivit
{
    /**
     * @var ZmenaStavuAktivity[]
     */
    private $zmenyStavuAktivit = [];

    public function addPosledniZmenaStavuAktivity(ZmenaStavuAktivity $zmenaStavuAktivity) {
        $this->zmenyStavuAktivit[] = $zmenaStavuAktivity;
    }

    /**
     * @return ZmenaStavuAktivity[]
     */
    public function zmenyStavuAktivit(): array {
        return $this->zmenyStavuAktivit;
    }

    public function posledniZmenaStavuAktivity(): ?ZmenaStavuAktivity {
        $zmeny = $this->zmenyStavuAktivit();
        if (!$zmeny) {
            return null;
        }
        usort($zmeny, static function (ZmenaStavuAktivity $nejakaZmena, ZmenaStavuAktivity $jinaZmena) {
            return $nejakaZmena->idLogu() <=> $jinaZmena->idLogu();
        });
        return end($zmeny);
    }

}
