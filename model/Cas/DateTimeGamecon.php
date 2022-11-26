<?php declare(strict_types=1);

namespace Gamecon\Cas;

class DateTimeGamecon extends DateTimeCz
{

    public static function denPodleIndexuOdZacatkuGameconu(int $indexDneKZacatkuGc, int $rok = ROK): string {
        $indexDneVuciStrede = $indexDneKZacatkuGc - 1;
        $englishOrCzechDayName = self::spocitejZacatekGameconu($rok)->modify("$indexDneVuciStrede days")->format('l');
        return strtr($englishOrCzechDayName, static::$dny);
    }

    /**
     * Čtvrtek s programem pro účastníky, nikoli středa se stavbou GC
     * @param int $rok
     * @return DateTimeGamecon
     */
    public static function zacatekGameconu(int $rok = ROK): DateTimeGamecon {
        if ($rok === (int)ROK && defined('GC_BEZI_OD')) {
            return self::zDbFormatu(GC_BEZI_OD);
        }
        return self::spocitejZacatekGameconu($rok);
    }

    public static function spocitejZacatekGameconu(int $rok): DateTimeGamecon {
        $zacatekCervence = new static($rok . '-07-01 00:00:00');
        $zacatekTretihoTydneVCervenci = self::dejZacatekXTydne(3, $zacatekCervence);
        $ctvrtekVeTretimTydnuVCervenci = self::dejDatumDneVTydnuOdData(static::CTVRTEK, $zacatekTretihoTydneVCervenci);
        if ($ctvrtekVeTretimTydnuVCervenci->format('d') >= 15) { // ve třetím týdnu pouze pokud začne v půlce měsíce či později
            return $ctvrtekVeTretimTydnuVCervenci->setTime(7, 0, 0);
        }
        $ctvrtekVeCtvrtemTydnuVCervenci = $ctvrtekVeTretimTydnuVCervenci->modify('+1 week');
        return $ctvrtekVeCtvrtemTydnuVCervenci->setTime(7, 0, 0);
    }

    public static function dejZacatekPredposlednihoTydneVMesici(DateTimeGamecon $datum): DateTimeGamecon {
        $posledniDenString = (clone $datum)->format('Y-m-t 00:00:00'); // t je maximum dni v mesici
        $posledniDen = DateTimeGamecon::createFromFormat('Y-m-d H:i:s', $posledniDenString);
        $predposledniTyden = $posledniDen->modify('-1 week');
        return self::dejDatumDneVTydnuDoData(self::PONDELI, $predposledniTyden);
    }

    public static function konecGameconu(int $rok = ROK): DateTimeGamecon {
        if ($rok === (int)ROK && defined('GC_BEZI_DO')) {
            return static::zDbFormatu(GC_BEZI_DO);
        }
        return self::spocitejKonecGameconu($rok);
    }

    public static function spocitejKonecGameconu(int $rok): DateTimeGamecon {
        $zacatekGameconu = self::spocitejZacatekGameconu($rok);
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
        $prvniDenVMesici = (clone $datum)->setDate((int)$datum->format('Y'), (int)$datum->format('m'), 1);
        $posunTydnu = $poradiTydne - 1; // prvni tyden uz mame, dalsi posun je bez nej
        $datumSeChtenymTydnem = $prvniDenVMesici->modify("+$posunTydnu weeks");
        return self::dejDatumDneVTydnuDoData(self::PONDELI, $datumSeChtenymTydnem);
    }

    public static function dejDatumDneVTydnuDoData(string $cilovyDenVTydnuDoData, DateTimeGamecon $doData): DateTimeGamecon {
        $poradiDneVTydnuDoData = (int)$doData->format('N');
        $poradiCilovehoDneVTydnu = static::poradiDne($cilovyDenVTydnuDoData);
        $rozdilDni = $poradiCilovehoDneVTydnu - $poradiDneVTydnuDoData;
        // záporné rozdíly posouvají vzad
        return (clone $doData)->modify("$rozdilDni days");
    }

    public static function dejDatumDneVTydnuOdData(string $cilovyDenVTydnuOdData, DateTimeGamecon $odData): DateTimeGamecon {
        $poradiDneVTydnu = $odData->format('N');
        $poradiCilovehoDneVTydnu = static::poradiDne($cilovyDenVTydnuOdData);
        // například neděle - čtvrtek = 7 - 4 = 3; nebo středa - středa = 3 - 3 = 0; pondělí - středa = 1 - 3 = -2
        $rozdilDni = $poradiCilovehoDneVTydnu - $poradiDneVTydnu;
        if ($rozdilDni < 0) { // chceme den v týdnu před současným, třeba pondělí před středou, ale "až potom", tedy v dalším týdnu
            // $rozdilDni je tady záporný, posuneme to do dalšího týdne
            $rozdilDni = 7 + $rozdilDni; // pondělí - středa = 7 + (1 - 3) = 7 + (-2) = 5 (středa + 5 dní je další pondělí)
        }
        return (clone $odData)->modify("+ $rozdilDni days");
    }

    public static function denKolemZacatkuGameconu(string $den, int $rok = ROK): DateTimeGamecon {
        $zacatekGameconu = static::zacatekGameconu($rok);
        if ($den === static::CTVRTEK) {
            return $zacatekGameconu;
        }
        $poradiCtvrtka = static::poradiDne(static::CTVRTEK);
        $poradiDne = static::poradiDne($den);
        if ($poradiDne === $poradiCtvrtka) {
            return $zacatekGameconu;
        }
        $rozdilDnu = $poradiDne - $poradiCtvrtka;
        return $zacatekGameconu->modify("$rozdilDnu days");
    }

    public static function zacatekProgramu(int $rok = ROK): DateTimeGamecon {
        $zacatekGameconu = self::zacatekGameconu($rok);
        // Gamecon začíná sice ve čtvrtek, ale technické aktivity již ve středu
        $zacatekTechnickychAktivit = $zacatekGameconu->modify('-1 day');
        return $zacatekTechnickychAktivit->setTime(0, 0, 0);
    }

    public static function zacatekRegistraciUcastniku(int $rok = ROK): DateTimeGamecon {
        $zacatekRegistraciUcastniku = $rok === (int)ROK && defined('REG_GC_OD')
            ? static::zDbFormatu(REG_GC_OD)
            : static::spocitejZacatekRegistraciUcastniku($rok);
        return $zacatekRegistraciUcastniku;
    }

    public static function spocitejZacatekRegistraciUcastniku(int $rok): DateTimeGamecon {
        if ($rok === 2013) {
            // čtvrtek
            return DateTimeGamecon::createFromMysql('2013-05-02 00:00:00');
        }
        if ($rok === 2014) {
            // čtvrtek
            return DateTimeGamecon::createFromMysql('2014-05-01 20:00:00');
        }
        if ($rok === 2015) {
            // úterý
            return DateTimeGamecon::createFromMysql('2015-04-28 20:15:00');
        }
        $zacatekKvetna = new static($rok . '-05-01 00:00:00');
        switch ($rok) {
            case 2016 :
                $poradiTydne = 2;
                $denVTydnu = static::UTERY;
                break;
            case 2017 :
                $poradiTydne = 1;
                $denVTydnu = static::UTERY;
                break;
            case 2018 :
            case 2019 :
                $poradiTydne = 3;
                $denVTydnu = static::UTERY;
                break;
            default : // 2020+
                $poradiTydne = 3;
                $denVTydnu = static::CTVRTEK;
        }
        $zacatekXTydneVKvetnu = self::dejZacatekXTydne($poradiTydne, $zacatekKvetna);
        $denVTydnuVKvetnu = self::dejDatumDneVTydnuOdData($denVTydnu, $zacatekXTydneVKvetnu);
        [$hodina, $minuta] = str_split((string)$rok, 2); // ciselna hricka, rok 2022 = hodina 20 a minuta 22

        return $denVTydnuVKvetnu->setTime((int)$hodina, (int)$minuta, 0);
    }

    public static function zacatekPrvniVlnyOd(int $rok = ROK): DateTimeGamecon {
        $zacatekPrvniVlnyOd = $rok === (int)ROK && defined('REG_AKTIVIT_OD')
            ? static::zDbFormatu(REG_AKTIVIT_OD)
            : self::spoctejZacatekPrvniVlnyOd($rok);

        return $zacatekPrvniVlnyOd;
    }

    public static function spoctejZacatekPrvniVlnyOd(int $rok): DateTimeGamecon {
        return self::spocitejZacatekRegistraciUcastniku($rok)->modify('+1 week');
    }

    public static function prvniHromadneOdhlasovaniOd(int $rok = ROK): DateTimeGamecon {
        if ($rok === (int)ROK && defined('HROMADNE_ODHLASOVANI')) {
            return static::zDbFormatu(HROMADNE_ODHLASOVANI);
        }
        // konec června
        return static::spocitejPrvniHromadneOdhlasovaniOd($rok);
    }

    public static function spocitejPrvniHromadneOdhlasovaniOd(int $rok) {
        // konec června
        return new static($rok . '-06-30 23:59:00');
    }

    public static function druheHromadneOdhlasovaniOd(int $rok = ROK): DateTimeGamecon {
        if ($rok === (int)ROK && defined('HROMADNE_ODHLASOVANI_2')) {
            return static::zDbFormatu(HROMADNE_ODHLASOVANI_2);
        }
        return static::spocitejDruheHromadneOdhlasovaniOd($rok);
    }

    public static function spocitejDruheHromadneOdhlasovaniOd(int $rok): DateTimeGamecon {
        $zacatekGameconu = self::spocitejZacatekGameconu($rok);
        $nedeleNaKonciGameconu = self::dejDatumDneVTydnuDoData(self::NEDELE, $zacatekGameconu);
        $nedelePredZacatkemGameconu = $nedeleNaKonciGameconu->modify('-1 week');

        return $nedelePredZacatkemGameconu->setTime(23, 59, 00);
    }

    public static function zacatekNejblizsiVlnyOdhlasovani(\DateTimeImmutable $ted = null): \DateTimeImmutable {
        // s rezervou jednoho dne, aby i po půlnoci ještě platilo včerejší datum odhlašování
        $kDatu = ($ted ?? new \DateTimeImmutable())->modify('-1 day');
        $prvniHromadneOdhlasovani = new \DateTimeImmutable(HROMADNE_ODHLASOVANI);
        if ($kDatu <= $prvniHromadneOdhlasovani) { // teprve bude
            return $prvniHromadneOdhlasovani;
        }
        return new \DateTimeImmutable(HROMADNE_ODHLASOVANI_2);
    }
}
