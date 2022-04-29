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

    /**
     * Oddělíme konstanty, závislé na PHP a MySQL enum, od JavaScriptu tím, že je tady přemapujeme.
     * Změna hodnoty konstanty v PHP tak neohrozí funkčnost JavaScriptové logiky.
     * @return null|string
     */
    public function stavPrihlaseniProJs(): ?string {
        switch ($this->stavPrihlaseni()) {
            // ÚČASTNÍK
            case AktivitaPrezenceTyp::PRIHLASENI :
                return 'ucastnik_se_prihlasil';
            case AktivitaPrezenceTyp::ODHLASENI :
            case AktivitaPrezenceTyp::ODHLASENI_HROMADNE :
                return 'ucastnik_se_odhlasil';
            case AktivitaPrezenceTyp::NEDOSTAVENI_SE :
                return 'ucastnik_dorazil';
            // SLEDUJÍCÍ
            case AktivitaPrezenceTyp::PRIHLASENI_SLEDUJICI :
                return 'sledujici_se_prihlasil';
            case AktivitaPrezenceTyp::ODHLASENI_SLEDUJICI :
                return 'sledujici_se_odhlasil';
            // NÁHRADNÍK
            case AktivitaPrezenceTyp::DORAZIL_JAKO_NAHRADNIK :
                return 'nahradnik_dorazil';
            case AktivitaPrezenceTyp::ZRUSENI_PRIHLASENI_NAHRADNIK :
                return 'nahradnik_nedorazil'; // nebo spíš odešel, popřípadě to byl omyl ve vyplňování prezence
            default :
                return null; // nějaký pro JS nezajímavý stav
        }
    }

    public function dorazilNejak(): bool {
        return AktivitaPrezenceTyp::dorazilNejak($this->stavPrihlaseni());
    }
}
