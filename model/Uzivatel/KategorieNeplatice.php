<?php declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Exceptions\NepodporovanaKategorieNeplatice;

/**
 * https://trello.com/c/Zzo2htqI/892-vytvo%C5%99it-nov%C3%BD-report-email%C5%AF-p%C5%99i-odhla%C5%A1ov%C3%A1n%C3%AD-neplati%C4%8D%C5%AF
 */
class KategorieNeplatice
{

    public const LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH    = 1;
    public const LETOS_POSLAL_MALO_A_MA_VELKY_DLUH                   = 2;
    public const LETOS_NEPOSLAL_NIC_Z_LONSKA_NECO_MA_A_MA_MALY_DLUH  = 3;
    public const LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY                 = 4;
    public const LETOS_SE_REGISTROVAL_PAR_DNU_PRED_ODHLASOVACI_VLNOU = 5;
    public const MA_PRAVO_PLATIT_AZ_NA_MISTE                         = 6; // orgové a tak
    public const LETOS_NEPOSLAL_NIC_ALE_TAKY_NEOBJEDNAL_NIC          = 7;

    public static function vytvorProNadchazejiciVlnuZGlobals(
        \Uzivatel          $uzivatel,
        SystemoveNastaveni $systemoveNastaveni = null
    ): static {
        /** @var SystemoveNastaveni $systemoveNastaveni */
        $systemoveNastaveni ??= $GLOBALS['systemoveNastaveni'];

        return static::vytvorProVlnu($uzivatel, $systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy());
    }

    public static function vytvorProVlnu(
        \Uzivatel          $uzivatel,
        \DateTimeInterface $hromadneOdhlasovaniKdy
    ): static {
        return new self(
            $uzivatel->finance(),
            $uzivatel->kdySeRegistrovalNaLetosniGc(),
            $uzivatel->maPravoNerusitObjednavky(),
            $hromadneOdhlasovaniKdy,
            ROCNIK,
            NEPLATIC_CASTKA_VELKY_DLUH,
            NEPLATIC_CASTKA_POSLAL_DOST,
            NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN
        );
    }

    private float $castkaVelkyDluh;
    private ?float $sumaLetosnichPlateb = null;
    private ?int $pocetLetosnichObjednavek = null;

    public function __construct(
        private Finance             $finance,
        private ?\DateTimeInterface $kdySeRegistrovalNaLetosniGc,
        private bool                $maPravoPlatitAzNaMiste,
        private \DateTimeInterface  $zacatekVlnyOdhlasovani,
        private int                 $rok,
        float                       $castkaVelkyDluh,
        private float               $castkaPoslalDost,
        private int                 $pocetDnuPredVlnouKdyJeJesteChranen
    ) {
        $this->castkaVelkyDluh = -abs($castkaVelkyDluh);
    }

    public function melByBytOdhlasen(): bool {
        return match ($this->dejCiselnouKategoriiNeplatice()) {
            null => false,
            self::MA_PRAVO_PLATIT_AZ_NA_MISTE => false,
            self::LETOS_SE_REGISTROVAL_PAR_DNU_PRED_ODHLASOVACI_VLNOU => false,
            self::LETOS_POSLAL_DOST_A_JE_TAK_CHRANENY => false,
            self::LETOS_NEPOSLAL_NIC_ALE_TAKY_NEOBJEDNAL_NIC => false,
            self::LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH => true,
            self::LETOS_POSLAL_MALO_A_MA_VELKY_DLUH => true,
            default => throw new NepodporovanaKategorieNeplatice(
                sprintf("Nepodporovaná kategorie neplatiče '{$this->dejCiselnouKategoriiNeplatice()}'. Doplňte podporu sem do logiky.")
            )
        };
    }

    /**
     * Specifikace viz
     * https://trello.com/c/Zzo2htqI/892-vytvo%C5%99it-nov%C3%BD-report-email%C5%AF-p%C5%99i-odhla%C5%A1ov%C3%A1n%C3%AD-neplati%C4%8D%C5%AF
     * https://docs.google.com/document/d/1pP3mp9piPNAl1IKCC5YYe92zzeFdTLDMiT-xrUhVLdQ/edit
     */
    public function dejCiselnouKategoriiNeplatice(): ?int {
        if ($this->maPravoPlatitAzNaMiste) {
            /**
             * Kategorie účastníka s právem platit až na místě
             * tj. orgové, vypravěči, partneři, dobrovolníci senioři, čestní orgové
             */
            // kategorie 6
            return self::MA_PRAVO_PLATIT_AZ_NA_MISTE;
        }

        if (!$this->kdySeRegistrovalNaLetosniGc) {
            return null;
        }
        if ($this->zacatekVlnyOdhlasovani < $this->kdySeRegistrovalNaLetosniGc) {
            /*
             * zjišťovat neplatiče už vlastně nejde, některé platby mohly přijít až po začátku hromadného odhlašování
             * (leda bychom filtrovali jednotlivé platby, ale tou dobou už to stejně nepotřebujeme)
             */
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
            return self::LETOS_NEPOSLAL_NIC_Z_LONSKA_NECO_MA_A_MA_MALY_DLUH;
        }

        if ($this->sumaLetosnichPlateb() <= 0.0 && $this->pocetLetosnichObjednavek() === 0) {
            return self::LETOS_NEPOSLAL_NIC_ALE_TAKY_NEOBJEDNAL_NIC;
        }

        if ($this->sumaLetosnichPlateb() <= 0.0
            && ($this->finance->zustatekZPredchozichRocniku() <= 0.0 || $this->maVelkyDluh())
        ) {
            /**
             * Nezaplatil vůbec nic
             * přitom se registroval na GC před více než týdnem
             * pokud má kladný historický zůstatek, spadá do této kategorie, pokud má celkový dluh -200 Kč a více
             */
            // kategorie 1
            return self::LETOS_NEPOSLAL_NIC_A_LONI_NIC_NEBO_MA_VELKY_DLUH;
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

    private function maVelkyDluh(): bool {
        return $this->finance->stav() <= $this->castkaVelkyDluh;
    }

    private function poslalDost(): bool {
        return $this->sumaLetosnichPlateb() >= $this->castkaPoslalDost;
    }

    private function sumaLetosnichPlateb(): float {
        if ($this->sumaLetosnichPlateb === null) {
            $this->sumaLetosnichPlateb = $this->finance->sumaPlateb($this->rok);
        }
        return $this->sumaLetosnichPlateb;
    }

    private function pocetLetosnichObjednavek(): int {
        if ($this->pocetLetosnichObjednavek === null) {
            $this->pocetLetosnichObjednavek = $this->finance->pocetObjednavek();
        }
        return $this->pocetLetosnichObjednavek;
    }

    private function prihlasilSeParDniPredVlnouOdhlasovani(): bool {
        return $this->kdySeRegistrovalNaLetosniGc <= $this->zacatekVlnyOdhlasovani
            /** pozor, @see \DateInterval::$days vrací vždy absolutní hodnotu */
            && $this->zacatekVlnyOdhlasovani->diff($this->kdySeRegistrovalNaLetosniGc)->days <= $this->pocetDnuPredVlnouKdyJeJesteChranen;
    }

    public function zacatekVlnyOdhlasovani(): ?\DateTimeInterface {
        return $this->zacatekVlnyOdhlasovani;
    }
}
