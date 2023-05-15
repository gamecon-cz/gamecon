<?php declare(strict_types=1);

namespace Gamecon\Cas;

use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

class DateTimeGamecon extends DateTimeCz
{

    public static function denPodleIndexuOdZacatkuGameconu(int $indexDneKZacatkuGc, int $rocnik = ROCNIK): string
    {
        $indexDneVuciStrede    = $indexDneKZacatkuGc - 1;
        $englishOrCzechDayName = self::spocitejZacatekGameconu($rocnik)->modify("$indexDneVuciStrede days")->format('l');
        return strtr($englishOrCzechDayName, static::$dny);
    }

    /**
     * Čtvrtek s programem pro účastníky, nikoli středa se stavbou GC
     * @param int $rocnik
     * @return DateTimeGamecon
     */
    public static function zacatekGameconu(int $rocnik = ROCNIK): DateTimeGamecon
    {
        if ($rocnik === (int)ROCNIK && defined('GC_BEZI_OD')) {
            return self::zDbFormatu(GC_BEZI_OD);
        }
        return self::spocitejZacatekGameconu($rocnik);
    }

    public static function spocitejZacatekGameconu(int $rocnik): DateTimeGamecon
    {
        $zacatekCervence               = new static($rocnik . '-07-01 00:00:00');
        $zacatekTretihoTydneVCervenci  = self::dejZacatekXTydne(3, $zacatekCervence);
        $ctvrtekVeTretimTydnuVCervenci = self::dejDatumDneVTydnuOdData(static::CTVRTEK, $zacatekTretihoTydneVCervenci);
        if ($ctvrtekVeTretimTydnuVCervenci->format('d') >= 15) { // ve třetím týdnu pouze pokud začne v půlce měsíce či později
            return $ctvrtekVeTretimTydnuVCervenci->setTime(7, 0, 0);
        }
        $ctvrtekVeCtvrtemTydnuVCervenci = $ctvrtekVeTretimTydnuVCervenci->modify('+1 week');
        return $ctvrtekVeCtvrtemTydnuVCervenci->setTime(7, 0, 0);
    }

    public static function dejZacatekPredposlednihoTydneVMesici(DateTimeGamecon $datum): DateTimeGamecon
    {
        $posledniDenString = (clone $datum)->format('Y-m-t 00:00:00'); // t je maximum dni v mesici
        $posledniDen       = DateTimeGamecon::createFromFormat('Y-m-d H:i:s', $posledniDenString);
        $predposledniTyden = $posledniDen->modify('-1 week');
        return self::dejDatumDneVTydnuDoData(self::PONDELI, $predposledniTyden);
    }

    public static function konecGameconu(int $rocnik = ROCNIK): DateTimeGamecon
    {
        if ($rocnik === (int)ROCNIK && defined('GC_BEZI_DO')) {
            return static::zDbFormatu(GC_BEZI_DO);
        }
        return self::spocitejKonecGameconu($rocnik);
    }

    public static function spocitejKonecGameconu(int $rocnik): DateTimeGamecon
    {
        $zacatekGameconu              = self::spocitejZacatekGameconu($rocnik);
        $prvniNedelePoZacatkuGameconu = self::dejDatumDneVTydnuOdData(static::NEDELE, $zacatekGameconu);

        return $prvniNedelePoZacatkuGameconu->setTime(21, 0, 0);
    }

    public static function zDbFormatu(string $datum): DateTimeGamecon
    {
        $zacatekGameconu = static::createFromFormat(self::FORMAT_DB, $datum);
        if ($zacatekGameconu) {
            /** @var DateTimeGamecon $zacatekGameconu */
            return $zacatekGameconu;
        }
        throw new \RuntimeException(sprintf("Can not create date from '%s' with expected format '%s'", $datum, self::FORMAT_DB));
    }

    protected static function dejZacatekXTydne(int $poradiTydne, DateTimeGamecon $datum): DateTimeGamecon
    {
        $prvniDenVMesici      = (clone $datum)->setDate((int)$datum->format('Y'), (int)$datum->format('m'), 1);
        $posunTydnu           = $poradiTydne - 1; // prvni tyden uz mame, dalsi posun je bez nej
        $datumSeChtenymTydnem = $prvniDenVMesici->modify("+$posunTydnu weeks");
        return self::dejDatumDneVTydnuDoData(self::PONDELI, $datumSeChtenymTydnem);
    }

    public static function dejDatumDneVTydnuDoData(string $cilovyDenVTydnuDoData, DateTimeGamecon $doData): DateTimeGamecon
    {
        $poradiDneVTydnuDoData   = (int)$doData->format('N');
        $poradiCilovehoDneVTydnu = static::poradiDne($cilovyDenVTydnuDoData);
        $rozdilDni               = $poradiCilovehoDneVTydnu - $poradiDneVTydnuDoData;
        // záporné rozdíly posouvají vzad
        return (clone $doData)->modify("$rozdilDni days");
    }

    public static function dejDatumDneVTydnuOdData(string $cilovyDenVTydnuOdData, DateTimeGamecon $odData): DateTimeGamecon
    {
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

    public static function denKolemZacatkuGameconu(string $den, int $rocnik = ROCNIK): DateTimeGamecon
    {
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

    public static function zacatekProgramu(int $rocnik = ROCNIK): DateTimeGamecon
    {
        $zacatekGameconu = self::zacatekGameconu($rocnik);
        // Gamecon začíná sice ve čtvrtek, ale technické aktivity již ve středu
        $zacatekTechnickychAktivit = $zacatekGameconu->modify('-1 day');
        return $zacatekTechnickychAktivit->setTime(0, 0, 0);
    }

    public static function konecProgramu(SystemoveNastaveni $systemoveNastaveni): DateTimeGamecon
    {
        return defined('GC_BEZI_DO')
            ? static::zDbFormatu(GC_BEZI_DO)
            : self::zDbFormatu($systemoveNastaveni->dejVychoziHodnotu('GC_BEZI_DO'));
    }

    public static function zacatekRegistraciUcastniku(int $rocnik = ROCNIK): DateTimeGamecon
    {
        return $rocnik === (int)ROCNIK && defined('REG_GC_OD')
            ? static::zDbFormatu(REG_GC_OD)
            : static::spocitejZacatekRegistraciUcastniku($rocnik);
    }

    public static function spocitejZacatekRegistraciUcastniku(int $rocnik): DateTimeGamecon
    {
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
                $poradiTydne = 2;
                $denVTydnu   = static::UTERY;
                break;
            case 2017 :
                $poradiTydne = 1;
                $denVTydnu   = static::UTERY;
                break;
            case 2018 :
            case 2019 :
                $poradiTydne = 3;
                $denVTydnu   = static::UTERY;
                break;
            default : // 2020+
                $poradiTydne = 3;
                $denVTydnu   = static::CTVRTEK;
        }
        $zacatekXTydneVKvetnu = self::dejZacatekXTydne($poradiTydne, $zacatekKvetna);
        $denVTydnuVKvetnu     = self::dejDatumDneVTydnuOdData($denVTydnu, $zacatekXTydneVKvetnu);
        [$hodina, $minuta] = str_split((string)$rocnik, 2); // ciselna hricka, rok 2022 = hodina 20 a minuta 22

        return $denVTydnuVKvetnu->setTime((int)$hodina, (int)$minuta, 0);
    }

    public static function zacatekPrvniVlnyOd(int $rocnik = ROCNIK): DateTimeGamecon
    {
        $zacatekPrvniVlnyOd = $rocnik === (int)ROCNIK && defined('REG_AKTIVIT_OD')
            ? static::zDbFormatu(REG_AKTIVIT_OD)
            : self::spoctejZacatekPrvniVlnyOd($rocnik);

        return $zacatekPrvniVlnyOd;
    }

    public static function spoctejZacatekPrvniVlnyOd(int $rocnik): DateTimeGamecon
    {
        return self::spocitejZacatekRegistraciUcastniku($rocnik)->modify('+1 week');
    }

    public static function prvniHromadneOdhlasovaniOd(int $rocnik = ROCNIK): DateTimeGamecon
    {
        if ($rocnik === (int)ROCNIK && defined('HROMADNE_ODHLASOVANI_1')) {
            return static::zDbFormatu(HROMADNE_ODHLASOVANI_1);
        }
        // konec června
        return static::spocitejPrvniHromadneOdhlasovaniOd($rocnik);
    }

    public static function spocitejPrvniHromadneOdhlasovaniOd(int $rocnik)
    {
        // konec června
        return new static($rocnik . '-06-30 23:59:00');
    }

    public static function druheHromadneOdhlasovaniOd(int $rocnik = ROCNIK): DateTimeGamecon
    {
        if ($rocnik === (int)ROCNIK && defined('HROMADNE_ODHLASOVANI_2')) {
            return static::zDbFormatu(HROMADNE_ODHLASOVANI_2);
        }
        return static::spocitejDruheHromadneOdhlasovaniOd($rocnik);
    }

    public static function spocitejDruheHromadneOdhlasovaniOd(int $rocnik): DateTimeGamecon
    {
        $zacatekGameconu                    = self::spocitejZacatekGameconu($rocnik);
        $nedeleNaKonciGameconu              = self::dejDatumDneVTydnuDoData(self::NEDELE, $zacatekGameconu);
        $nedeleDvaTydnyPredZacatkemGameconu = $nedeleNaKonciGameconu->modify('-2 week');

        return $nedeleDvaTydnyPredZacatkemGameconu->setTime(23, 59, 00);
    }

    public static function tretiHromadneOdhlasovaniOd(int $rocnik = ROCNIK): DateTimeGamecon
    {
        if ($rocnik === (int)ROCNIK && defined('HROMADNE_ODHLASOVANI_3')) {
            return static::zDbFormatu(HROMADNE_ODHLASOVANI_3);
        }
        return static::spocitejTretiHromadneOdhlasovaniOd($rocnik);
    }

    public static function spocitejTretiHromadneOdhlasovaniOd(int $rocnik): DateTimeGamecon
    {
        $zacatekGameconu            = self::spocitejZacatekGameconu($rocnik);
        $nedeleNaKonciGameconu      = self::dejDatumDneVTydnuDoData(self::NEDELE, $zacatekGameconu);
        $nedelePredZacatkemGameconu = $nedeleNaKonciGameconu->modify('-1 week');

        return $nedelePredZacatkemGameconu->setTime(23, 59, 00);
    }

    public static function zacatekNejblizsiVlnyOdhlasovani(SystemoveNastaveni $systemoveNastaveni): \DateTimeImmutable
    {
        // s rezervou jednoho dne, aby i po půlnoci ještě platilo včerejší datum odhlašování
        $kDatu                    = ($systemoveNastaveni->ted())->modifyStrict('-1 day');
        $prvniHromadneOdhlasovani = $systemoveNastaveni->prvniHromadneOdhlasovani();
        if ($kDatu <= $prvniHromadneOdhlasovani) { // právě je nebo teprve bude
            return $prvniHromadneOdhlasovani;
        }
        $druheHromadneOdhlasovani = $systemoveNastaveni->druheHromadneOdhlasovani();
        if ($kDatu <= $druheHromadneOdhlasovani) { // právě je nebo teprve bude
            return $druheHromadneOdhlasovani;
        }
        return $systemoveNastaveni->tretiHromadneOdhlasovani();
    }
}
