<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

class ZmenaPrihlaseni
{
    private const UCASTNIK_SE_PRIHLASIL_JS  = 'ucastnik_se_prihlasil';
    private const UCASTNIK_SE_ODHLASIL_JS   = 'ucastnik_se_odhlasil';
    private const UCASTNIK_DORAZIL_JS       = 'ucastnik_dorazil';
    private const UCASTNIK_NEDORAZIL_JS     = 'ucastnik_nedorazil';
    private const SLEDUJICI_SE_PRIHLASIL_JS = 'sledujici_se_prihlasil';
    private const SLEDUJICI_SE_ODHLASIL_JS  = 'sledujici_se_odhlasil';
    private const NAHRADNIK_DORAZIL_JS      = 'nahradnik_dorazil';
    private const NAHRADNIK_NEDORAZIL_JS    = 'nahradnik_nedorazil';

    public static function vytvorZDatDatabaze(
        int                $idUzivatele,
        int                $idAktivity,
        int                $idLogu,
        \DateTimeImmutable $casZmeny,
        string             $typPrezence,/** @see AktivitaPrezenceTyp */
    ): self
    {
        return new static($idUzivatele, $idAktivity, $idLogu, $casZmeny, $typPrezence);
    }

    /** @var int */
    private $idUzivatele;
    /** @var int */
    private $idAktivity;
    /** @var int */
    private $idLogu;
    /** @var \DateTimeImmutable */
    private $casZmeny;
    /** @var string */
    private $typPrezence;

    public function __construct(int $idUzivatele, int $idAktivity, int $idLogu, \DateTimeImmutable $casZmeny, string $typPrezence)
    {
        if ($typPrezence && !AktivitaPrezenceTyp::jeZnamy($typPrezence)) {
            throw new \LogicException('Neznamy stav prihlaseni ' . var_export($typPrezence, true));
        }
        $this->idUzivatele = $idUzivatele;
        $this->idAktivity  = $idAktivity;
        $this->idLogu      = $idLogu;
        $this->casZmeny    = $casZmeny;
        $this->typPrezence = $typPrezence;
    }

    public function idUzivatele(): int
    {
        return $this->idUzivatele;
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

    public function typPrezence(): string
    {
        return $this->typPrezence;
    }

    public function stavPrihlaseni(): int
    {
        return match ($this->typPrezence()) {
            AktivitaPrezenceTyp::PRIHLASENI => StavPrihlaseni::PRIHLASEN,
            AktivitaPrezenceTyp::DORAZIL => StavPrihlaseni::PRIHLASEN_A_DORAZIL,
            AktivitaPrezenceTyp::NEDOSTAVENI_SE => StavPrihlaseni::PRIHLASEN_ALE_NEDORAZIL,
            AktivitaPrezenceTyp::DORAZIL_JAKO_NAHRADNIK => StavPrihlaseni::DORAZIL_JAKO_NAHRADNIK,
            AktivitaPrezenceTyp::PRIHLASENI_SLEDUJICI => StavPrihlaseni::SLEDUJICI,
            AktivitaPrezenceTyp::ODHLASENI,
            AktivitaPrezenceTyp::ODHLASENI_HROMADNE,
            AktivitaPrezenceTyp::NAHRADNIK_NEDORAZIL,
            AktivitaPrezenceTyp::ODHLASENI_SLEDUJICI => -1,
            default => throw new \RuntimeException('Neznámý typ prezence ' . $this->typPrezence()),
        };
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
    public function typPrezenceProJs(): ?string
    {
        switch ($this->typPrezence()) {
            // ÚČASTNÍK
            case AktivitaPrezenceTyp::PRIHLASENI :
                return self::UCASTNIK_SE_PRIHLASIL_JS;
            case AktivitaPrezenceTyp::ODHLASENI :
            case AktivitaPrezenceTyp::ODHLASENI_HROMADNE :
                return self::UCASTNIK_SE_ODHLASIL_JS;
            case AktivitaPrezenceTyp::NEDOSTAVENI_SE :
                return self::UCASTNIK_NEDORAZIL_JS;
            case AktivitaPrezenceTyp::DORAZIL :
                return self::UCASTNIK_DORAZIL_JS;
            // SLEDUJÍCÍ
            case AktivitaPrezenceTyp::PRIHLASENI_SLEDUJICI :
                return self::SLEDUJICI_SE_PRIHLASIL_JS;
            case AktivitaPrezenceTyp::ODHLASENI_SLEDUJICI :
                return self::SLEDUJICI_SE_ODHLASIL_JS;
            // NÁHRADNÍK
            case AktivitaPrezenceTyp::DORAZIL_JAKO_NAHRADNIK :
                return self::NAHRADNIK_DORAZIL_JS;
            case AktivitaPrezenceTyp::NAHRADNIK_NEDORAZIL :
                return self::NAHRADNIK_NEDORAZIL_JS; // nebo spíš odešel, popřípadě to byl omyl ve vyplňování prezence
            default :
                return null; // nějaký pro JS nezajímavý stav
        }
    }
}
