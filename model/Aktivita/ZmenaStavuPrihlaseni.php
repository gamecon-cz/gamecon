<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

class ZmenaStavuPrihlaseni
{
    private const UCASTNIK_SE_PRIHLASIL = 'ucastnik_se_prihlasil';
    private const UCASTNIK_SE_ODHLASIL = 'ucastnik_se_odhlasil';
    private const UCASTNIK_DORAZIL = 'ucastnik_dorazil';
    private const UCASTNIK_NEDORAZIL = 'ucastnik_nedorazil';
    private const SLEDUJICI_SE_PRIHLASIL = 'sledujici_se_prihlasil';
    private const SLEDUJICI_SE_ODHLASIL = 'sledujici_se_odhlasil';
    private const NAHRADNIK_DORAZIL = 'nahradnik_dorazil';
    private const NAHRADNIK_NEDORAZIL = 'nahradnik_nedorazil';

    public static function vytvorZDatDatabaze(int $idUzivatele, ?\DateTimeImmutable $casZmeny, ?string $stavPrihlaseni): self {
        return new static($idUzivatele, $casZmeny, $stavPrihlaseni);
    }

    public static function vytvorZDatJavscriptu(int $idUzivatele, ?\DateTimeImmutable $casZmeny, string $stavPrihlaseniJs): self {
        return static::vytvorZDatDatabaze(
            $idUzivatele,
            $casZmeny,
            self::stavPrihlaseniZJsDoDatabazoveho($stavPrihlaseniJs)
        );
    }

    private static function stavPrihlaseniZJsDoDatabazoveho(string $stavPrihlaseniJs): string {
        switch ($stavPrihlaseniJs) {
            case self::UCASTNIK_SE_PRIHLASIL :
                return AktivitaPrezenceTyp::PRIHLASENI;
            case self::UCASTNIK_DORAZIL :
                return AktivitaPrezenceTyp::DORAZIL;
            case self::UCASTNIK_SE_ODHLASIL :
                return AktivitaPrezenceTyp::ODHLASENI;
            case self::UCASTNIK_NEDORAZIL :
                return AktivitaPrezenceTyp::NEDOSTAVENI_SE;
            case self::SLEDUJICI_SE_PRIHLASIL :
                return AktivitaPrezenceTyp::PRIHLASENI_SLEDUJICI;
            case self::SLEDUJICI_SE_ODHLASIL :
                return AktivitaPrezenceTyp::ODHLASENI_SLEDUJICI;
            case self::NAHRADNIK_DORAZIL :
                return AktivitaPrezenceTyp::DORAZIL_JAKO_NAHRADNIK;
            case self::NAHRADNIK_NEDORAZIL :
                return AktivitaPrezenceTyp::NAHRADNIK_NEDORAZIL;
            default:
                throw new \RuntimeException('Neznámý stav přihlášení pro JS ' . var_export($stavPrihlaseniJs, true));
        }
    }

    /**
     * @var int
     */
    private $idUzivatele;
    /**
     * @var ?\DateTimeImmutable
     */
    private $casZmeny;
    /**
     * @var string
     */
    private $stavPrihlaseni;

    public function __construct(int $idUzivatele, ?\DateTimeImmutable $casZmeny, ?string $stavPrihlaseni) {
        if ($stavPrihlaseni && !AktivitaPrezenceTyp::jeZnamy($stavPrihlaseni)) {
            throw new \LogicException('Neznamy stav prihlaseni ' . var_export($stavPrihlaseni, true));
        }
        $this->idUzivatele = $idUzivatele;
        $this->casZmeny = $casZmeny;
        $this->stavPrihlaseni = $stavPrihlaseni;
    }

    public function idUzivatele(): int {
        return $this->idUzivatele;
    }

    public function casZmeny(): ?\DateTimeImmutable {
        return $this->casZmeny;
    }

    public function stavPrihlaseni(): ?string {
        return $this->stavPrihlaseni;
    }

    public function casZmenyProJs(): ?string {
        return $this->casZmeny
            ? $this->casZmeny->format(DATE_ATOM)
            : null;
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
                return self::UCASTNIK_SE_PRIHLASIL;
            case AktivitaPrezenceTyp::ODHLASENI :
            case AktivitaPrezenceTyp::ODHLASENI_HROMADNE :
                return self::UCASTNIK_SE_ODHLASIL;
            case AktivitaPrezenceTyp::NEDOSTAVENI_SE :
                return self::UCASTNIK_NEDORAZIL;
            case AktivitaPrezenceTyp::DORAZIL :
                return self::UCASTNIK_DORAZIL;
            // SLEDUJÍCÍ
            case AktivitaPrezenceTyp::PRIHLASENI_SLEDUJICI :
                return self::SLEDUJICI_SE_PRIHLASIL;
            case AktivitaPrezenceTyp::ODHLASENI_SLEDUJICI :
                return self::SLEDUJICI_SE_ODHLASIL;
            // NÁHRADNÍK
            case AktivitaPrezenceTyp::DORAZIL_JAKO_NAHRADNIK :
                return self::NAHRADNIK_DORAZIL;
            case AktivitaPrezenceTyp::NAHRADNIK_NEDORAZIL :
                return self::NAHRADNIK_NEDORAZIL; // nebo spíš odešel, popřípadě to byl omyl ve vyplňování prezence
            default :
                return null; // nějaký pro JS nezajímavý stav
        }
    }

    public function dorazilNejak(): bool {
        return $this->stavPrihlaseni() !== null && AktivitaPrezenceTyp::dorazilNejak($this->stavPrihlaseni());
    }
}
