<?php declare(strict_types=1);

namespace Gamecon\Cas;

use Gamecon\Cas\Exceptions\ChybnaZpetnaPlatnost;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\ZdrojTed;
use Gamecon\SystemoveNastaveni\ZdrojVlnAktivit;

/**
 * @method static DateTimeGamecon|false createFromMysql(string $dateTime, \DateTimeZone $timeZone = null)
 * @method static DateTimeGamecon|false createFromFormat($format, $time, $timezone = null)
 * @method static DateTimeGamecon|false createFromInterface(\DateTimeInterface $dateTime)
 */
class DateTimeGamecon extends DateTimeCz
{

    public static function denPodleIndexuOdZacatkuGameconu(int $indexDneKZacatkuGc, int $rocnik = ROCNIK): string {
        $indexDneVuciStrede    = $indexDneKZacatkuGc - 1;
        $englishOrCzechDayName = self::spocitejZacatekGameconu($rocnik)->modify("$indexDneVuciStrede days")->format('l');
        return strtr($englishOrCzechDayName, static::$dny);
    }

    /**
     * Čtvrtek s programem pro účastníky, nikoli středa se stavbou GC
     * @param int $rocnik
     * @return DateTimeGamecon
     */
    public static function zacatekGameconu(int $rocnik = ROCNIK): DateTimeGamecon {
        if ($rocnik === (int)ROCNIK && defined('GC_BEZI_OD')) {
            return self::zDbFormatu(GC_BEZI_OD);
        }
        return self::spocitejZacatekGameconu($rocnik);
    }

    public static function spocitejZacatekGameconu(int $rocnik): DateTimeGamecon {
        $konecCervence                          = new static($rocnik . '-07-31 00:00:00');
        $posledniNedeleVCervenci                = self::dejDatumDneVTydnuDoData(
            self::NEDELE,
            $konecCervence
        );
        $predposledniNedeleVCervnu              = $posledniNedeleVCervenci->modify('-1 week');
        $ctvrtekVPredposlednimCelemTydnuVCervnu = self::dejDatumDneVTydnuDoData(
            self::CTVRTEK,
            $predposledniNedeleVCervnu
        );

        return $ctvrtekVPredposlednimCelemTydnuVCervnu->setTime(7, 0, 0);
    }

    public static function dejZacatekPredposlednihoTydneVMesici(DateTimeGamecon $datum): DateTimeGamecon {
        $posledniDenString = (clone $datum)->format('Y-m-t 00:00:00'); // t je maximum dni v mesici
        $posledniDen       = DateTimeGamecon::createFromFormat('Y-m-d H:i:s', $posledniDenString);
        $predposledniTyden = $posledniDen->modify('-1 week');
        return self::dejDatumDneVTydnuDoData(self::PONDELI, $predposledniTyden);
    }

    public static function konecGameconu(int $rocnik = ROCNIK): DateTimeGamecon {
        if ($rocnik === (int)ROCNIK && defined('GC_BEZI_DO')) {
            return static::zDbFormatu(GC_BEZI_DO);
        }
        return self::spocitejKonecGameconu($rocnik);
    }

    public static function spocitejKonecGameconu(int $rocnik): DateTimeGamecon {
        $zacatekGameconu              = self::spocitejZacatekGameconu($rocnik);
        $prvniNedelePoZacatkuGameconu = self::dejDatumDneVTydnuOdData(static::NEDELE, $zacatekGameconu);

        return $prvniNedelePoZacatkuGameconu->setTime(21, 0, 0);
    }

    protected static function zDbFormatu(string $datum): DateTimeGamecon {
        $zacatekGameconu = static::createFromFormat(self::FORMAT_DB, $datum);
        if ($zacatekGameconu) {
            /** @var DateTimeGamecon $zacatekGameconu */
            return $zacatekGameconu;
        }
        throw new \RuntimeException(sprintf("Can not create date from '%s' with expected format '%s'", $datum, self::FORMAT_DB));
    }

    protected static function dejZacatekXTydne(int $poradiTydne, DateTimeGamecon $datum): DateTimeGamecon {
        $prvniDenVMesici      = (clone $datum)->setDate((int)$datum->format('Y'), (int)$datum->format('m'), 1);
        $posunTydnu           = $poradiTydne - 1; // prvni tyden uz mame, dalsi posun je bez nej
        $datumSeChtenymTydnem = $prvniDenVMesici->modify("+$posunTydnu weeks");
        return self::dejDatumDneVTydnuDoData(self::PONDELI, $datumSeChtenymTydnem);
    }

    public static function dejDatumDneVTydnuDoData(string $cilovyDenVTydnuDoData, DateTimeGamecon $doData): DateTimeGamecon {
        $poradiDneVTydnuDoData   = (int)$doData->format('N');
        $poradiCilovehoDneVTydnu = static::poradiDne($cilovyDenVTydnuDoData);
        $rozdilDni               = $poradiCilovehoDneVTydnu - $poradiDneVTydnuDoData;
        if ($poradiCilovehoDneVTydnu > $poradiDneVTydnuDoData) {
            $rozdilDni = $rozdilDni - 7; // chceme třeba neděli, když poslední den je sobota, takže potřebujeme předchozí týden
        }
        // záporné rozdíly posouvají vzad
        return (clone $doData)->modify("$rozdilDni days");
    }

    public static function dejDatumDneVTydnuOdData(string $cilovyDenVTydnuOdData, DateTimeGamecon $odData): DateTimeGamecon {
        $poradiDneVTydnu         = $odData->format('N');
        $poradiCilovehoDneVTydnu = static::poradiDne($cilovyDenVTydnuOdData);
        // například neděle - čtvrtek = 7 - 4 = 3; nebo středa - středa = 3 - 3 = 0; pondělí - středa = 1 - 3 = -2
        $rozdilDni = $poradiCilovehoDneVTydnu - $poradiDneVTydnu;
        if ($rozdilDni < 0) { // chceme den v týdnu před současným, třeba pondělí před středou, ale "až potom", tedy v dalším týdnu
            // $rozdilDni je tady záporný, posuneme to do dalšího týdne
            $rozdilDni = 7 + $rozdilDni; // pondělí - středa = 7 + (1 - 3) = 7 + (-2) = 5 (středa + 5 dní je další pondělí)
        }
        return (clone $odData)->modify("+ $rozdilDni days");
    }

    public static function denKolemZacatkuGameconu(string $den, int $rocnik = ROCNIK): DateTimeGamecon {
        $zacatekGameconu = static::zacatekGameconu($rocnik);
        if ($den === static::CTVRTEK) {
            return $zacatekGameconu;
        }
        $poradiCtvrtka = static::poradiDne(static::CTVRTEK);
        $poradiDne     = static::poradiDne($den);
        if ($poradiDne === $poradiCtvrtka) {
            return $zacatekGameconu;
        }
        $rozdilDnu = $poradiDne - $poradiCtvrtka;
        return $zacatekGameconu->modify("$rozdilDnu days");
    }

    public static function zacatekProgramu(int $rocnik = ROCNIK): DateTimeGamecon {
        $zacatekGameconu = self::zacatekGameconu($rocnik);
        // Gamecon začíná sice ve čtvrtek, ale technické aktivity již ve středu
        $zacatekTechnickychAktivit = $zacatekGameconu->modify('-1 day');
        return $zacatekTechnickychAktivit->setTime(0, 0, 0);
    }

    public static function zacatekRegistraciUcastniku(int $rocnik = ROCNIK): DateTimeGamecon {
        $zacatekRegistraciUcastniku = $rocnik === (int)ROCNIK && defined('REG_GC_OD')
            ? static::zDbFormatu(REG_GC_OD)
            : static::spocitejZacatekRegistraciUcastniku($rocnik);
        return $zacatekRegistraciUcastniku;
    }

    public static function konecRegistraciUcastniku(int $rocnik = ROCNIK): DateTimeGamecon {
        return DateTimeGamecon::createFromMysql(GC_BEZI_DO);
    }

    public static function spocitejZacatekRegistraciUcastniku(int $rocnik): DateTimeGamecon {
        if ($rocnik === 2013) {
            // čtvrtek
            return DateTimeGamecon::createFromMysql('2013-05-02 00:00:00');
        }
        if ($rocnik === 2014) {
            // čtvrtek
            return DateTimeGamecon::createFromMysql('2014-05-01 20:00:00');
        }
        if ($rocnik === 2015) {
            // úterý
            return DateTimeGamecon::createFromMysql('2015-04-28 20:15:00');
        }
        $zacatekKvetna = new static($rocnik . '-05-01 00:00:00');
        switch ($rocnik) {
            case 2016 :
            case 2017 :
                $poradiTydne = 1;
                $denVTydnu   = static::UTERY;
                break;
            case 2018 :
            case 2019 :
                $poradiTydne = 2;
                $denVTydnu   = static::UTERY;
                break;
            default :
                $poradiTydne = 2;
                $denVTydnu   = static::CTVRTEK;
                break;
        }

        $nedelePrvniTydenVKvetnu = self::dejDatumDneVTydnuOdData(self::NEDELE, $zacatekKvetna);
        $poradiPrvniNedele       = $nedelePrvniTydenVKvetnu->format('j');
        if ((int)$poradiPrvniNedele !== 7) {
            $poradiTydne++; // přeskočíme neúplný týden
        }

        $zacatekXTydneVKvetnu = self::dejZacatekXTydne($poradiTydne, $zacatekKvetna);
        $denVTydnuVKvetnu     = self::dejDatumDneVTydnuOdData($denVTydnu, $zacatekXTydneVKvetnu);
        [$hodina, $minuta] = str_split((string)$rocnik, 2); // ciselna hricka, rok 2022 = hodina 20 a minuta 22

        return $denVTydnuVKvetnu->setTime((int)$hodina, (int)$minuta, 0);
    }

    public static function prvniVlnaKdy(int $rocnik = ROCNIK): DateTimeGamecon {
        $zacatekPrvniVlnyOd = $rocnik === (int)ROCNIK && defined('PRVNI_VLNA_KDY')
            ? static::zDbFormatu(PRVNI_VLNA_KDY)
            : self::spoctejKdyJePrvniVlna($rocnik);

        return $zacatekPrvniVlnyOd;
    }

    public static function spoctejKdyJePrvniVlna(int $rocnik): DateTimeGamecon {
        return self::spocitejZacatekRegistraciUcastniku($rocnik)->modify('+1 week');
    }

    public static function druhaVlnaKdy(int $rocnik = ROCNIK): DateTimeGamecon {
        $zacatekDruheVlnyOd = $rocnik === (int)ROCNIK && defined('DRUHA_VLNA_KDY')
            ? static::zDbFormatu(DRUHA_VLNA_KDY)
            : self::spocitejKdyJeDruhaVlna($rocnik);

        return $zacatekDruheVlnyOd;
    }

    public static function spocitejKdyJeDruhaVlna(int $rocnik): DateTimeGamecon {
        return self::spoctejKdyJePrvniVlna($rocnik)->modify('+3 weeks');
    }

    public static function tretiVlnaKdy(int $rocnik = ROCNIK): DateTimeGamecon {
        $zacatekTretiVlnyOd = $rocnik === (int)ROCNIK && defined('TRETI_VLNA_KDY')
            ? static::zDbFormatu(TRETI_VLNA_KDY)
            : self::spocitejKdyJeTretiVlna($rocnik);

        return $zacatekTretiVlnyOd;
    }

    public static function spocitejKdyJeTretiVlna(int $rocnik): DateTimeGamecon {
        return self::spocitejZacatekRegistraciUcastniku($rocnik)->setDate($rocnik, 7, 1);
    }

    public static function prvniHromadneOdhlasovani(int $rocnik = ROCNIK): DateTimeGamecon {
        if ($rocnik < 2023) {
            return static::zDbFormatu("$rocnik-06-30 23:59:00");
        }
        return static::spocitejPrvniHromadneOdhlasovani($rocnik);
    }

    public static function spocitejPrvniHromadneOdhlasovani(int $rocnik): DateTimeGamecon {
        return static::druhaVlnaKdy($rocnik)->modify('-10 minutes');
    }

    public static function druheHromadneOdhlasovani(int $rocnik = ROCNIK): DateTimeGamecon {
        if ($rocnik < 2023) {
            return static::zDbFormatu("$rocnik-07-17 23:59:00");
        }
        return static::spocitejDruheHromadneOdhlasovani($rocnik);
    }

    public static function spocitejDruheHromadneOdhlasovani(int $rocnik): DateTimeGamecon {
        return static::tretiVlnaKdy($rocnik)->modify('-10 minutes');
    }

    /**
     * @throws ChybnaZpetnaPlatnost
     */
    public static function nejblizsiHromadneOdhlasovaniKdy(
        SystemoveNastaveni $systemoveNastaveni,
        \DateTimeInterface $platnostZpetne = null
    ): DateTimeImmutableStrict {
        $platnostZpetne = static::overenaPlatnostZpetne($systemoveNastaveni, $platnostZpetne);

        $prvniHromadneOdhlasovani = $systemoveNastaveni->prvniHromadneOdhlasovani();
        if ($prvniHromadneOdhlasovani >= $platnostZpetne) { // právě je nebo teprve bude
            return $prvniHromadneOdhlasovani;
        }
        return $systemoveNastaveni->druheHromadneOdhlasovani();
    }

    public static function poradiHromadnehoOdhlasovani(
        \DateTimeInterface $casOdhlasovani,
        SystemoveNastaveni $systemoveNastaveni
    ): int {
        if ($systemoveNastaveni->prvniHromadneOdhlasovani()->getTimestamp() === $casOdhlasovani->getTimestamp()) {
            return 1;
        }
        if ($systemoveNastaveni->druheHromadneOdhlasovani()->getTimestamp() === $casOdhlasovani->getTimestamp()) {
            return 2;
        }
        throw new \LogicException(
            "Neznámé pořadí data hromadného odhlašování '{$casOdhlasovani->format(self::FORMAT_DB)}'"
        );
    }

    /**
     * @throws ChybnaZpetnaPlatnost
     */
    public static function overenaPlatnostZpetne(
        ZdrojTed           $zdrojTed,
        \DateTimeInterface $platnostZpetne = null
    ): DateTimeImmutableStrict {
        $ted = $zdrojTed->ted();
        // s rezervou jednoho dne, aby i po půlnoci ještě platilo včerejší datum odhlašování
        $platnostZpetne = $platnostZpetne ?? $ted->modifyStrict('-1 day');
        if ($platnostZpetne > $ted) {
            throw new ChybnaZpetnaPlatnost(
                sprintf(
                    "Nelze použít platnost zpětně k datu '%s' když je teprve '%s'. Vyžadován čas v minulosti.",
                    $platnostZpetne->format(DateTimeCz::FORMAT_DB),
                    $ted->format(DateTimeCz::FORMAT_DB),
                )
            );
        }

        return DateTimeImmutableStrict::createFromInterface($platnostZpetne);
    }

    public static function nejblizsiVlnaKdy(
        ZdrojVlnAktivit|ZdrojTed $zdrojCasu,
        \DateTimeInterface       $platnostZpetne = null
    ): DateTimeGamecon {
        $platnostZpetne = static::overenaPlatnostZpetne($zdrojCasu, $platnostZpetne);

        $prvniVlnaKdy = $zdrojCasu->prvniVlnaKdy();
        if ($platnostZpetne <= $prvniVlnaKdy) { // právě je nebo teprve bude
            return $prvniVlnaKdy;
        }
        $druhaVlnaKdy = $zdrojCasu->druhaVlnaKdy();
        if ($platnostZpetne <= $druhaVlnaKdy) { // právě je nebo teprve bude
            return $druhaVlnaKdy;
        }
        return $zdrojCasu->tretiVlnaKdy();
    }
}
