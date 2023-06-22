<?php declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Objekt\ObnoveniVychozichHodnotTrait;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * https://trello.com/c/Zzo2htqI/892-vytvo%C5%99it-nov%C3%BD-report-email%C5%AF-p%C5%99i-odhla%C5%A1ov%C3%A1n%C3%AD-neplati%C4%8D%C5%AF
 */
class KategorieNeplatice
{
    use ObnoveniVychozichHodnotTrait;

    public const LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH            = 1;
    public const LETOS_POSLAL_MALO_A_MA_VELKY_DLUH                           = 2;
    public const LETOS_NEPOSLAL_DOST_NEBO_Z_LONSKA_NECO_MA_A_NEMA_VELKY_DLUH = 3;
    public const LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY                         = 4;
    public const LETOS_SE_REGISTROVAL_PAR_DNU_PRED_ODHLASOVACI_VLNOU         = 5;
    public const MA_PRAVO_NEODHLASOVAT                                       = 6; // orgové a tak
    public const LETOS_NEPOSLAL_NIC_ALE_TAKY_NEOBJEDNAL_NIC                  = 7;

    public static function vytvorProNadchazejiciVlnuZGlobals(
        \Uzivatel          $uzivatel,
        SystemoveNastaveni $systemoveNastaveni = null,
    ): static
    {
        /** @var SystemoveNastaveni $systemoveNastaveni */
        $systemoveNastaveni ??= $GLOBALS['systemoveNastaveni'];

        return static::vytvorZHromadnehoOdhlasovani(
            $uzivatel,
            $systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy(),
            $systemoveNastaveni,
        );
    }

    public static function vytvorZHromadnehoOdhlasovani(
        \Uzivatel          $uzivatel,
        \DateTimeInterface $hromadneOdhlasovaniKdy,
        SystemoveNastaveni $systemoveNastaveni = null,
    ): static
    {
        /** @var SystemoveNastaveni $systemoveNastaveni */
        $systemoveNastaveni ??= $GLOBALS['systemoveNastaveni'];

        return new self(
            $uzivatel->finance(),
            $uzivatel->kdySeRegistrovalNaLetosniGc(),
            $uzivatel->maPravoNerusitObjednavky(),
            $hromadneOdhlasovaniKdy,
            $systemoveNastaveni->rocnik(),
            $systemoveNastaveni->neplaticCastkaVelkyDluh(),
            $systemoveNastaveni->neplaticCastkaPoslalDost(),
            $systemoveNastaveni->neplaticPocetDnuPredVlnouKdyJeChranen()
        );
    }

    private float  $castkaVelkyDluh;
    private ?float $sumaLetosnichPlateb = null;

    public function __construct(
        private Finance             $finance,
        private ?\DateTimeInterface $kdySeRegistrovalNaLetosniGc,
        private bool                $maPravoNerusitObjednavky,
        private \DateTimeInterface  $hromadneOdhlasovaniKdy,
        private int                 $rocnik,
        float                       $castkaVelkyDluh,
        private float               $castkaPoslalDost,
        private int                 $pocetDnuPredVlnouKdyJeJesteChranen,
    )
    {
        $this->castkaVelkyDluh = -abs($castkaVelkyDluh);
    }

    public function melByBytOdhlasen(): bool
    {
        return in_array(
            $this->ciselnaKategoriiNeplatice(),
            [
                self::LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH,
                self::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH,
            ],
            true,
        );
    }

    public function maSmyslOdhlasitMuJenNeco(): bool
    {
        return $this->ciselnaKategoriiNeplatice() === self::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH;
    }

    /**
     * Specifikace viz
     * https://trello.com/c/Zzo2htqI/892-vytvo%C5%99it-nov%C3%BD-report-email%C5%AF-p%C5%99i-odhla%C5%A1ov%C3%A1n%C3%AD-neplati%C4%8D%C5%AF
     * https://docs.google.com/document/d/1pP3mp9piPNAl1IKCC5YYe92zzeFdTLDMiT-xrUhVLdQ/edit
     */
    public function ciselnaKategoriiNeplatice(): ?int
    {
        if ($this->maPravoNerusitObjednavky) {
            /**
             * Kategorie účastníka s právem platit až na místě
             * tj. orgové, vypravěči, partneři, dobrovolníci senioři, čestní orgové
             */
            // kategorie 6
            return self::MA_PRAVO_NEODHLASOVAT;
        }

        if (!$this->kdySeRegistrovalNaLetosniGc) {
            return null;
        }
        if ($this->hromadneOdhlasovaniKdy < $this->kdySeRegistrovalNaLetosniGc) {
            /*
             * zjišťovat neplatiče už vlastně nejde, některé platby mohly přijít až po začátku hromadného odhlašování
             * (leda bychom filtrovali jednotlivé platby, ale tou dobou už to stejně nepotřebujeme)
             */
            /**
             * v tomto případě je název zavádějící, pro hlavní účel této kategorie
             * @see prihlasilSeParDniPredVlnouOdhlasovani
             */
            // kategorie 5
            return self::LETOS_SE_REGISTROVAL_PAR_DNU_PRED_ODHLASOVACI_VLNOU;
        }

        if ($this->poslalDost()) {
            /**
             * Zaplatil 1.000 Kč a více
             * historický zůstatek se nepočítá (tj. 1.000 Kč+ platba v aktuálním roce)
             * aktuální zůstatek nehraje roli ani když je záporný
             */
            // kategorie 4
            return self::LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY;
        }

        if ($this->prihlasilSeParDniPredVlnouOdhlasovani()) {
            /**
             * Registroval se v době jednoho týdne před odhlašováním
             * registrace na GC jako takový (ne na aktivitu)
             * poslané peníze a aktuální zůstatek (tj. ani záporný) nemají vliv
             */
            // kategorie 5
            return self::LETOS_SE_REGISTROVAL_PAR_DNU_PRED_ODHLASOVACI_VLNOU;
        }

        if (((!$this->poslalDost() && $this->sumaLetosnichPlateb() > 0.0)
                || $this->finance->zustatekZPredchozichRocniku() > 0.0
            )
            && !$this->maVelkyDluh()
        ) {
            /**
             * Letos poslal 1 - 999 Kč nebo má kladný historický zůstatek, má celkový dluh méně než -200 Kč
             * a přitom se registroval na GC před více než týdnem
             */
            // kategorie 3
            return self::LETOS_NEPOSLAL_DOST_NEBO_Z_LONSKA_NECO_MA_A_NEMA_VELKY_DLUH;
        }

        if ($this->sumaLetosnichPlateb() <= 0.0
            && ($this->finance->zustatekZPredchozichRocniku() <= 0.0 || $this->maVelkyDluh())
        ) {
            /**
             * - nezaplatil vůbec nic
             * - přitom se registroval na GC před více než týdnem
             * (spadá do této kategorie i pokud má celkový dluh -200 Kč a více a má kladný historický zůstatek)
             */
            // kategorie 1
            return self::LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH;
        }

        if ($this->sumaLetosnichPlateb() <= 0.0 && $this->pocetLetosnichObjednavek() === 0 && !$this->maVelkyDluh()) {
            return self::LETOS_NEPOSLAL_NIC_ALE_TAKY_NEOBJEDNAL_NIC;
        }

        if (!$this->poslalDost() && $this->maVelkyDluh()) {
            /**
             * Letos poslal 1 - 999 Kč, má celkový dluh -200 Kč a více
             * přitom se registroval na GC před více než týdnem
             */
            // kategorie 2
            return self::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH;
        }

        return null;
    }

    private function maVelkyDluh(): bool
    {
        return $this->finance->stav() <= $this->castkaVelkyDluh;
    }

    private function poslalDost(): bool
    {
        return $this->sumaLetosnichPlateb() >= $this->castkaPoslalDost;
    }

    private function sumaLetosnichPlateb(): float
    {
        if ($this->sumaLetosnichPlateb === null) {
            $this->sumaLetosnichPlateb = $this->finance->sumaPlateb($this->rocnik);
        }
        return $this->sumaLetosnichPlateb;
    }

    private function pocetLetosnichObjednavek(): int
    {
        /**
         * Necachovat lokálně, jinak nebude fungovat postupné odhlašování položek,
         * @see HromadneOdhlaseniNeplaticu::hromadneOdhlasit
         */
        return $this->finance->pocetObjednavek();
    }

    private function prihlasilSeParDniPredVlnouOdhlasovani(): bool
    {
        return $this->kdySeRegistrovalNaLetosniGc <= $this->hromadneOdhlasovaniKdy
            /** pozor, @see \DateInterval::$days vrací vždy absolutní hodnotu */
            && $this->hromadneOdhlasovaniKdy->diff($this->kdySeRegistrovalNaLetosniGc)->days <= $this->pocetDnuPredVlnouKdyJeJesteChranen;
    }

    public function zacatekVlnyOdhlasovani(): ?\DateTimeInterface
    {
        return $this->hromadneOdhlasovaniKdy;
    }

    public function obnovUdaje(bool $vcetneSumyLetosnichPlateb = true)
    {
        $sumaLetosnichPlateb = $this->sumaLetosnichPlateb;

        $this->finance->obnovUdaje();
        $this->obnovVychoziHodnotyObjektu();

        if (!$vcetneSumyLetosnichPlateb) { // chceme zachovat cache plateb
            $this->sumaLetosnichPlateb = $sumaLetosnichPlateb;
        }
    }
}
