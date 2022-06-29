<?php declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * https://trello.com/c/Zzo2htqI/892-vytvo%C5%99it-nov%C3%BD-report-email%C5%AF-p%C5%99i-odhla%C5%A1ov%C3%A1n%C3%AD-neplati%C4%8D%C5%AF
 */
class KategorieNeplatice
{

    public const LETOS_NEPOSLAL_NIC_Z_LONSKA_NIC_A_MA_DLUH = 1;
    public const LETOS_POSLAL_MALO_A_MA_VELKY_DLUH = 2;
    public const LETOS_NEPOSLAL_NIC_Z_LONSKA_NECO_MA_A_MA_MALY_DLUH = 3;
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
            $uzivatel->kdySeRegistrovalNaLetosniGc(),
            $uzivatel->maPravoNerusitObjednavky(),
            SystemoveNastaveni::zacatekNejblizsiVlnyOdhlasovani(),
            ROK,
            NEPLATIC_CASTKA_VELKY_DLUH,
            NEPLATIC_CASTKA_POSLAL_DOST,
            NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN
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
    private $pocetDnuPredVlnouKdyJeJesteChranen;
    /**
     * @var \DateTimeInterface|null
     */
    private $kdySeRegistrovalNaLetosniGc;

    public function __construct(
        Finance             $finance,
        ?\DateTimeInterface $kdySeRegistrovalNaLetosniGc,
        bool                $maPravoPlatitAzNaMiste,
        ?\DateTimeInterface $zacatekVlnyOdhlasovani, // prvni nebo druha vlna
        int                 $rok,
        float               $castkaVelkyDluh,
        float               $castkaPoslalDost,
        int                 $pocetDnuPredVlnouKdyJeJesteChranen
    ) {
        $this->castkaVelkyDluh = -abs($castkaVelkyDluh);
        $this->castkaPoslalDost = $castkaPoslalDost;
        $this->pocetDnuPredVlnouKdyJeJesteChranen = $pocetDnuPredVlnouKdyJeJesteChranen;
        $this->kdySeRegistrovalNaLetosniGc = $kdySeRegistrovalNaLetosniGc;
        $this->zacatekVlnyOdhlasovani = $zacatekVlnyOdhlasovani;
        $this->finance = $finance;
        $this->rok = $rok;
        $this->maPravoPlatitAzNaMiste = $maPravoPlatitAzNaMiste;
    }

    /**
     * Specifikace viz
     * https://trello.com/c/Zzo2htqI/892-vytvo%C5%99it-nov%C3%BD-report-email%C5%AF-p%C5%99i-odhla%C5%A1ov%C3%A1n%C3%AD-neplati%C4%8D%C5%AF
     * https://docs.google.com/document/d/1pP3mp9piPNAl1IKCC5YYe92zzeFdTLDMiT-xrUhVLdQ/edit
     */
    public function dejCiselnouKategoriiNeplatice(): ?int {
        if ($this->maPravoPlatitAzNaMiste) {
            // kategorie 6
            return self::MA_PRAVO_PLATIT_AZ_NA_MISTE;
        }

        if (!$this->kdySeRegistrovalNaLetosniGc
            || !$this->zacatekVlnyOdhlasovani
            || $this->zacatekVlnyOdhlasovani < $this->kdySeRegistrovalNaLetosniGc
        ) {
            // zjišťovat neplatiče už nejde, některé platby mohly přijít až po začátku hromadného odhlašování (leda bychom filtrovali jednotlivé platby, ale tou dobou už to stejně nepotřebujeme)
            return null;
        }

        $sumaLetosnichPlateb = $this->finance->sumaPlateb($this->rok);
        if ($sumaLetosnichPlateb >= $this->castkaPoslalDost) {
            // kategorie 4
            return self::LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY;
        }

        if ($this->prihlasilSeParDniPredVlnouOdhlasovani()) {
            // kategorie 5
            return self::LETOS_SE_REGISTROVAL_PAR_DNU_PRED_ODHLASOVACI_VLNOU;
        }

        if ($sumaLetosnichPlateb <= 0.0
            && $this->finance->zustatekZPredchozichRocniku() > 0.0
            && $this->finance->stav() > $this->castkaVelkyDluh // nemá tak velký dluh (protože -999 je VĚTŠÍ než -1000)
        ) {
            // kategorie 3
            return self::LETOS_NEPOSLAL_NIC_Z_LONSKA_NECO_MA_A_MA_MALY_DLUH;
        }

        if ($sumaLetosnichPlateb <= 0.0
            && $this->finance->zustatekZPredchozichRocniku() <= 0.0
            && $this->finance->stav() < 0.0
        ) {
            // kategorie 1
            return self::LETOS_NEPOSLAL_NIC_Z_LONSKA_NIC_A_MA_DLUH;
        }

        if ($sumaLetosnichPlateb < $this->castkaPoslalDost
            && $this->finance->stav() <= $this->castkaVelkyDluh // má velký dluh
        ) {
            // poslal málo na to, abychom mu ignorovali dluh a ještě k tomu má dluh velký
            // kategorie 2
            return self::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH;
        }

        return null;
    }

    private function prihlasilSeParDniPredVlnouOdhlasovani(): bool {
        return $this->kdySeRegistrovalNaLetosniGc <= $this->zacatekVlnyOdhlasovani
            /** pozor, @see \DateInterval::$days vrací vždy absolutní hodnotu */
            && $this->zacatekVlnyOdhlasovani->diff($this->kdySeRegistrovalNaLetosniGc)->days <= $this->pocetDnuPredVlnouKdyJeJesteChranen;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function zacatekVlnyOdhlasovani(): ?\DateTimeInterface {
        return $this->zacatekVlnyOdhlasovani;
    }
}
