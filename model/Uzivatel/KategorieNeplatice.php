<?php declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Pravo;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * https://trello.com/c/Zzo2htqI/892-vytvo%C5%99it-nov%C3%BD-report-email%C5%AF-p%C5%99i-odhla%C5%A1ov%C3%A1n%C3%AD-neplati%C4%8D%C5%AF
 */
class KategorieNeplatice
{

    public const V_PORADKU = 0;
    public const LETOS_NEZAPLATIL_VUBEC_NIC = 1;
    public const LETOS_POSLAL_MALO_A_MA_VELKY_DLUH = 2;
    public const LETOS_POSLAL_MALO_A_MA_MALY_DLUH = 3;
    public const LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY = 4;
    public const LETOS_SE_REGISTROVAL_PAR_DNU_PRED_ODHLASOVACI_VLNOU = 5;
    public const MA_PRAVO_PLATIT_AZ_NA_MISTE = 6; // orgové a tak
    /**
     * @var \DateTimeInterface|null
     */
    private $zacatekVlnyOdhlasovani;
    /**
     * @var Finance
     */
    private $finance;
    /**
     * @var int
     */
    private $rok;
    /**
     * @var bool
     */
    private $maPravoPlatitAzNaMiste;

    public static function vytvorProNadchazejiciVlnuZGlobals(
        \Uzivatel $uzivatel
    ) {
        return new self(
            $uzivatel->finance(),
            $uzivatel->kdySePrihlasilNaLetosniGc(),
            $uzivatel->maPravoPlatitAzNaMiste(),
            SystemoveNastaveni::zacatekNejblizsiVlnyOdhlasovani(),
            ROK,
            NEPLATIC_CASTKA_VELKY_DLUH,
            NEPLATIC_CASTKA_POSLAL_DOST,
            NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN,
            Pravo::NERUSIT_AUTOMATICKY_OBJEDNAVKY
        );
    }

    /**
     * @var float
     */
    private $castkaVelkyDluh;
    /**
     * @var float
     */
    private $castkaPoslalDost;
    /**
     * @var int
     */
    private $pocetDnuPredVlnouKdyJeJesteChrane;
    /**
     * @var int
     */
    private $idPravaZeMuzePlatitAzNaMiste;
    /**
     * @var \DateTimeInterface|null
     */
    private $kdySePrihlasilNaLetosniGc;

    public function __construct(
        Finance             $finance,
        ?\DateTimeInterface $kdySePrihlasilNaLetosniGc,
        bool                $maPravoPlatitAzNaMiste,
        ?\DateTimeInterface $zacatekVlnyOdhlasovani, // prvni nebo druha vlna
        int                 $rok,
        float               $castkaVelkyDluh,
        float               $castkaPoslalDost,
        int                 $pocetDnuPredVlnouKdyJeJesteChrane,
        int                 $idPravaZeMuzePlatitAzNaMiste
    ) {
        $this->castkaVelkyDluh = -abs($castkaVelkyDluh);
        $this->castkaPoslalDost = $castkaPoslalDost;
        $this->pocetDnuPredVlnouKdyJeJesteChrane = $pocetDnuPredVlnouKdyJeJesteChrane;
        $this->idPravaZeMuzePlatitAzNaMiste = $idPravaZeMuzePlatitAzNaMiste;
        $this->kdySePrihlasilNaLetosniGc = $kdySePrihlasilNaLetosniGc;
        $this->zacatekVlnyOdhlasovani = $zacatekVlnyOdhlasovani;
        $this->finance = $finance;
        $this->rok = $rok;
        $this->maPravoPlatitAzNaMiste = $maPravoPlatitAzNaMiste;
    }

    public function dejCiselnouKategoriiNeplatice(): int {
        if (!$this->kdySePrihlasilNaLetosniGc || !$this->zacatekVlnyOdhlasovani
            // zjišťovat neplatiče už nejde, platby mohly už přoijít až po začátku hromadného odhlašování
            || $this->zacatekVlnyOdhlasovani < $this->kdySePrihlasilNaLetosniGc
        ) {
            return self::V_PORADKU;
        }
        if ($this->maPravoPlatitAzNaMiste) {
            return self::MA_PRAVO_PLATIT_AZ_NA_MISTE;
        }
        $stavPlatebZaRok = $this->finance->stavPlatebZaRok($this->rok);
        if (!$this->prihlasilSeParDniPredVlnouOdhlasovani()) {
            if ($stavPlatebZaRok <= 0.0) {
                return self::LETOS_NEZAPLATIL_VUBEC_NIC;
            }
            if ($stavPlatebZaRok < $this->castkaPoslalDost) {
                return $this->finance->stav() /* ještě zápornější než velký dluh */ <= $this->castkaVelkyDluh
                    // poslal málo na to, abychom mu ignorovali dluh a ještě k tomu má dluh velký
                    ? self::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH
                    : self::LETOS_POSLAL_MALO_A_MA_MALY_DLUH;
            }
        }
        if ($stavPlatebZaRok >= $this->castkaPoslalDost) {
            return self::LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY;
        }
        return $this->prihlasilSeParDniPredVlnouOdhlasovani()
            ? self::LETOS_SE_REGISTROVAL_PAR_DNU_PRED_ODHLASOVACI_VLNOU
            : self::V_PORADKU;
    }

    private function prihlasilSeParDniPredVlnouOdhlasovani(): bool {
        /** pozor, @see \DateTimeInterface::diff vrací vždy absolutní hodnotu */
        return $this->zacatekVlnyOdhlasovani->diff($this->kdySePrihlasilNaLetosniGc) <= $this->pocetDnuPredVlnouKdyJeJesteChrane;
    }
}
