<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

class PosledniZmenyPrihlaseni
{
    /**
     * @var ZmenaPrihlaseni[]
     */
    private $zmenyPrihlaseni = [];

    public function addPosledniZmenaPrihlaseni(ZmenaPrihlaseni $zmenaPrihlaseni)
    {
        $this->zmenyPrihlaseni[] = $zmenaPrihlaseni;
    }

    /**
     * @return ZmenaPrihlaseni[]
     */
    public function zmenyPrihlaseni(): array
    {
        return $this->zmenyPrihlaseni;
    }

    public function posledniZmenaPrihlaseni(): ?ZmenaPrihlaseni
    {
        $zmeny = $this->zmenyPrihlaseni();
        if (!$zmeny) {
            return null;
        }
        usort($zmeny, static function (ZmenaPrihlaseni $nejakaZmena, ZmenaPrihlaseni $jinaZmena) {
            return $nejakaZmena->idLogu() <=> $jinaZmena->idLogu();
        });
        return end($zmeny);
    }

}
