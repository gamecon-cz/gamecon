<?php declare(strict_types=1);

namespace Gamecon\Cas;

use Gamecon\Cas\Exceptions\ChybnaZpetnaPlatnost;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\SystemoveNastaveni\ZdrojTed;
use Gamecon\SystemoveNastaveni\ZdrojVlnAktivit;

/**
 * @method static DateTimeGamecon|false createFromMysql(string $dateTime, \DateTimeZone $timeZone = null)
 * @method static DateTimeGamecon|false createFromFormat($format, $datetime, $timezone = null)
 * @method static DateTimeGamecon|false createFromInterface(\DateTimeInterface $dateTime)
 */
class DateTimeGamecon extends DateTimeCz
{
    public const PORADI_HERNIHO_DNE_STREDA  = 0;
    public const PORADI_HERNIHO_DNE_CTVRTEK = 1;
    public const PORADI_HERNIHO_DNE_PATEK   = 2;
    public const PORADI_HERNIHO_DNE_SOBOTA  = 3;
    public const PORADI_HERNIHO_DNE_NEDELE  = 4;

    public const VYCHOZI_PLATNOST_HROMADNYCH_AKCI_ZPETNE = '-1 day';

    public static function poradiDneVTydnuPodleIndexuOdZacatkuGameconu(int $indexDneKZacatkuGc, int $rocnik = ROCNIK): int
    {
        $indexDneVuciStrede = $indexDneKZacatkuGc - 1;
        return (int)self::zacatekGameconu($rocnik)->modify("$indexDneVuciStrede days")->format('N');
    }

    public static function denPodleIndexuOdZacatkuGameconu(int $indexDneKZacatkuGc, int $rocnik = ROCNIK): string
    {
        $poradiDneVTydnu = self::poradiDneVTydnuPodleIndexuOdZacatkuGameconu($indexDneKZacatkuGc, $rocnik);
        return self::$dnyIndexovanePoradim[$poradiDneVTydnu];
    }

    /**
     * Čtvrtek s programem pro účastníky, nikoli středa se stavbou GC
     * @param int $rocnik
     * @return DateTimeGamecon
     */
    public static function zacatekGameconu(int $rocnik = ROCNIK): static
    {
        if ($rocnik === (int)ROCNIK && defined('GC_BEZI_OD')) {
            return static::zDbFormatu(GC_BEZI_OD);
        }
        return static::spocitejZacatekGameconu($rocnik);
    }

    public static function spocitejZacatekGameconu(int $rocnik): static
    {
        $konecCervence                          = new static($rocnik . '-07-31 00:00:00');
        $posledniNedeleVCervenci                = static::dejDatumDneVTydnuDoData(
            static::NEDELE,
            $konecCervence,
        );
        $predposledniNedeleVCervnu              = $posledniNedeleVCervenci->modify('-1 week');
        $ctvrtekVPredposlednimCelemTydnuVCervnu = static::dejDatumDneVTydnuDoData(
            static::CTVRTEK,
            $predposledniNedeleVCervnu,
        );

        return $ctvrtekVPredposlednimCelemTydnuVCervnu->setTime(7, 0, 0);
    }

    public static function dejZacatekPredposlednihoTydneVMesici(DateTimeGamecon $datum): static
    {
        $posledniDenString = (clone $datum)->format('Y-m-t 00:00:00'); // t je maximum dni v mesici
        $posledniDen       = DateTimeGamecon::createFromFormat('Y-m-d H:i:s', $posledniDenString);
        $predposledniTyden = $posledniDen->modify('-1 week');
        return static::dejDatumDneVTydnuDoData(static::PONDELI, $predposledniTyden);
    }

    public static function konecGameconu(int $rocnik = ROCNIK): static
    {
        if ($rocnik === (int)ROCNIK && defined('GC_BEZI_DO')) {
            return static::zDbFormatu(GC_BEZI_DO);
        }
        return static::spocitejKonecGameconu($rocnik);
    }

    public static function spocitejKonecGameconu(int $rocnik): static
    {
        $zacatekGameconu              = static::spocitejZacatekGameconu($rocnik);
        $prvniNedelePoZacatkuGameconu = static::dejDatumDneVTydnuOdData(static::NEDELE, $zacatekGameconu);

        return $prvniNedelePoZacatkuGameconu->setTime(21, 0, 0);
    }

    public static function zDbFormatu(string $datum): static
    {
        $zacatekGameconu = static::createFromFormat(static::FORMAT_DB, $datum);
        if ($zacatekGameconu) {
            /** @var DateTimeGamecon $zacatekGameconu */
            return $zacatekGameconu;
        }
        throw new \RuntimeException(sprintf("Can not create date from '%s' with expected format '%s'", $datum, static::FORMAT_DB));
    }

    protected static function dejZacatekXTydne(int $poradiTydne, DateTimeGamecon $datum): static
    {
        $prvniDenVMesici      = (clone $datum)->setDate((int)$datum->format('Y'), (int)$datum->format('m'), 1);
        $posunTydnu           = $poradiTydne - 1; // prvni tyden uz mame, dalsi posun je bez nej
        $datumSeChtenymTydnem = $prvniDenVMesici->modify("+$posunTydnu weeks");
        return static::dejDatumDneVTydnuDoData(static::PONDELI, $datumSeChtenymTydnem);
    }

    public static function dejDatumDneVTydnuDoData(string $cilovyDenVTydnuDoData, DateTimeGamecon $doData): static
    {
        $poradiDneVTydnuDoData   = (int)$doData->format('N');
        $poradiCilovehoDneVTydnu = static::poradiDne($cilovyDenVTydnuDoData);
        $rozdilDni               = $poradiCilovehoDneVTydnu - $poradiDneVTydnuDoData;
        if ($poradiCilovehoDneVTydnu > $poradiDneVTydnuDoData) {
            $rozdilDni = $rozdilDni - 7; // chceme třeba neděli, když poslední den je sobota, takže potřebujeme předchozí týden
        }
        // záporné rozdíly posouvají vzad
        return (clone $doData)->modify("$rozdilDni days");
    }

    public static function dejDatumDneVTydnuOdData(string $cilovyDenVTydnuOdData, DateTimeGamecon $odData): static
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

    public static function denKolemZacatkuGameconu(string $den, int $rocnik = ROCNIK): static
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

    public static function zacatekProgramu(int $rocnik = ROCNIK): static
    {
        $zacatekGameconu = static::zacatekGameconu($rocnik);
        // Gamecon začíná sice ve čtvrtek, ale technické aktivity již ve středu
        $zacatekTechnickychAktivit = $zacatekGameconu->modify('-1 day');
        return $zacatekTechnickychAktivit->setTime(0, 0, 0);
    }

    public static function konecProgramu(SystemoveNastaveni $systemoveNastaveni): static
    {
        return static::konecGameconu($systemoveNastaveni->rocnik());
    }

    public static function registraceUcastnikuOd(int $rocnik = ROCNIK): static
    {
        return $rocnik === (int)ROCNIK && defined('REG_GC_OD')
            ? static::zDbFormatu(REG_GC_OD)
            : static::spocitejZacatekRegistraciUcastniku($rocnik);
    }

    public static function registraceUcastnikuDo(int $rocnik = ROCNIK): static
    {
        return $rocnik === (int)ROCNIK && defined('REG_GC_DO')
            ? static::zDbFormatu(REG_GC_DO)
            : static::spocitejKonecRegistraciUcastniku($rocnik);
    }

    public static function spocitejKonecRegistraciUcastniku(int $rocnik): static
    {
        return static::spocitejKonecGameconu($rocnik);
    }

    public static function spocitejZacatekRegistraciUcastniku(int $rocnik): static
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

        $nedelePrvniTydenVKvetnu = static::dejDatumDneVTydnuOdData(static::NEDELE, $zacatekKvetna);
        $poradiPrvniNedele       = $nedelePrvniTydenVKvetnu->format('j');
        if ((int)$poradiPrvniNedele !== 7) {
            $poradiTydne++; // přeskočíme neúplný týden
        }

        $zacatekXTydneVKvetnu = static::dejZacatekXTydne($poradiTydne, $zacatekKvetna);
        $denVTydnuVKvetnu     = static::dejDatumDneVTydnuOdData($denVTydnu, $zacatekXTydneVKvetnu);
        [$hodina, $minuta] = str_split((string)$rocnik, 2); // ciselna hricka, rok 2022 = hodina 20 a minuta 22

        return $denVTydnuVKvetnu->setTime((int)$hodina, (int)$minuta, 0);
    }

    public static function prvniVlnaKdy(int $rocnik = ROCNIK): static
    {
        $prvniVlnaKdy = $rocnik === (int)ROCNIK && defined('PRVNI_VLNA_KDY')
            ? static::zDbFormatu(PRVNI_VLNA_KDY)
            : static::spoctejKdyJePrvniVlna($rocnik);

        return $prvniVlnaKdy;
    }

    public static function spoctejKdyJePrvniVlna(int $rocnik): static
    {
        return static::spocitejZacatekRegistraciUcastniku($rocnik)->modify('+1 week');
    }

    public static function druhaVlnaKdy(int $rocnik = ROCNIK): static
    {
        $zacatekDruheVlnyOd = $rocnik === (int)ROCNIK && defined('DRUHA_VLNA_KDY')
            ? static::zDbFormatu(DRUHA_VLNA_KDY)
            : static::spocitejKdyJeDruhaVlna($rocnik);

        return $zacatekDruheVlnyOd;
    }

    public static function spocitejKdyJeDruhaVlna(int $rocnik): static
    {
        return static::spoctejKdyJePrvniVlna($rocnik)->modify('+3 weeks');
    }

    public static function tretiVlnaKdy(int $rocnik = ROCNIK): static
    {
        $zacatekTretiVlnyOd = $rocnik === (int)ROCNIK && defined('TRETI_VLNA_KDY')
            ? static::zDbFormatu(TRETI_VLNA_KDY)
            : static::spocitejKdyJeTretiVlna($rocnik);

        return $zacatekTretiVlnyOd;
    }

    public static function spocitejKdyJeTretiVlna(int $rocnik): static
    {
        return static::spocitejZacatekRegistraciUcastniku($rocnik)->setDate($rocnik, 7, 1);
    }

    public static function prvniHromadneOdhlasovani(int $rocnik = ROCNIK): static
    {
        if ($rocnik < 2023) {
            return static::zDbFormatu("$rocnik-06-30 23:59:00");
        }
        if ($rocnik === (int)ROCNIK && defined('HROMADNE_ODHLASOVANI_1')) {
            return static::zDbFormatu(HROMADNE_ODHLASOVANI_1);
        }
        return static::spocitejPrvniHromadneOdhlasovani($rocnik);
    }

    public static function spocitejPrvniHromadneOdhlasovani(int $rocnik): static
    {
        return static::tretiVlnaKdy($rocnik)->modify('-10 minutes');
    }

    public static function druheHromadneOdhlasovani(int $rocnik = ROCNIK): static
    {
        if ($rocnik < 2023) {
            return static::zDbFormatu("$rocnik-07-17 23:59:00");
        }
        if ($rocnik === (int)ROCNIK && defined('HROMADNE_ODHLASOVANI_2')) {
            return static::zDbFormatu(HROMADNE_ODHLASOVANI_2);
        }
        return static::spocitejDruheHromadneOdhlasovani($rocnik);
    }

    public static function spocitejDruheHromadneOdhlasovani(int $rocnik): static
    {
        return static::tretiVlnaKdy($rocnik)->modify('+9 day')->setTime(0, 0, 0);
    }

    public static function tretiHromadneOdhlasovani(int $rocnik = ROCNIK): static
    {
        if ($rocnik === (int)ROCNIK && defined('HROMADNE_ODHLASOVANI_3')) {
            return static::zDbFormatu(HROMADNE_ODHLASOVANI_3);
        }
        return static::spocitejTretiHromadneOdhlasovani($rocnik);
    }

    public static function spocitejTretiHromadneOdhlasovani(int $rocnik = ROCNIK): static
    {
        return static::spocitejDruheHromadneOdhlasovani($rocnik)->modify('+1 week');
    }

    /**
     * @throws ChybnaZpetnaPlatnost
     */
    public static function nejblizsiHromadneOdhlasovaniKdy(
        SystemoveNastaveni $systemoveNastaveni,
        \DateTimeInterface $platnostZpetne = null,
    ): DateTimeImmutableStrict
    {
        $platnostZpetne = $platnostZpetne ?? static::overenaPlatnostZpetne($systemoveNastaveni);

        $prvniHromadneOdhlasovani = $systemoveNastaveni->prvniHromadneOdhlasovani();
        if ($prvniHromadneOdhlasovani >= $platnostZpetne) { // právě je nebo teprve bude
            return $prvniHromadneOdhlasovani;
        }

        $druheHromadneOdhlasovani = $systemoveNastaveni->druheHromadneOdhlasovani();
        if ($druheHromadneOdhlasovani >= $platnostZpetne) { // právě je nebo teprve bude
            return $druheHromadneOdhlasovani;
        }

        return $systemoveNastaveni->tretiHromadneOdhlasovani();
    }

    public static function poradiHromadnehoOdhlasovani(
        \DateTimeInterface $casOdhlasovani,
        SystemoveNastaveni $systemoveNastaveni,
    ): int
    {
        if ($systemoveNastaveni->prvniHromadneOdhlasovani()->getTimestamp() === $casOdhlasovani->getTimestamp()) {
            return 1;
        }
        if ($systemoveNastaveni->druheHromadneOdhlasovani()->getTimestamp() === $casOdhlasovani->getTimestamp()) {
            return 2;
        }
        if ($systemoveNastaveni->tretiHromadneOdhlasovani()->getTimestamp() === $casOdhlasovani->getTimestamp()) {
            return 3;
        }
        throw new \LogicException(
            "Neznámé pořadí data hromadného odhlašování '{$casOdhlasovani->format(static::FORMAT_DB)}'"
        );
    }

    public static function poradiVlny(
        \DateTimeInterface $casVlny,
        SystemoveNastaveni $systemoveNastaveni,
    ): int
    {
        if ($systemoveNastaveni->prvniVlnaKdy()->getTimestamp() === $casVlny->getTimestamp()) {
            return 1;
        }
        if ($systemoveNastaveni->druhaVlnaKdy()->getTimestamp() === $casVlny->getTimestamp()) {
            return 2;
        }
        if ($systemoveNastaveni->tretiVlnaKdy()->getTimestamp() === $casVlny->getTimestamp()) {
            return 3;
        }
        throw new \LogicException(
            "Neznámé pořadí data vlny (hromadné aktivace aktivit) '{$casVlny->format(static::FORMAT_DB)}'"
        );
    }

    /**
     * @throws ChybnaZpetnaPlatnost
     */
    public static function overenaPlatnostZpetne(
        ZdrojTed           $zdrojTed,
        \DateTimeInterface $platnostZpetne = null,
    ): DateTimeImmutableStrict
    {
        $ted = $zdrojTed->ted();
        // s rezervou jednoho dne, aby i po půlnoci ještě platilo včerejší datum odhlašování
        $platnostZpetne = $platnostZpetne ?? $ted->modifyStrict(static::VYCHOZI_PLATNOST_HROMADNYCH_AKCI_ZPETNE);
        if ($platnostZpetne > $ted) {
            throw new ChybnaZpetnaPlatnost(
                sprintf(
                    "Nelze použít platnost zpětně k datu '%s' (%s) když teď je teprve '%s'. Vyžadován čas v minulosti.",
                    $platnostZpetne->format(DateTimeCz::FORMAT_DB),
                    $platnostZpetne->relativniVBudoucnu($ted),
                    $ted->format(DateTimeCz::FORMAT_DB),
                )
            );
        }

        return DateTimeImmutableStrict::createFromInterface($platnostZpetne);
    }

    public static function nejblizsiVlnaKdy(
        ZdrojVlnAktivit|ZdrojTed $zdrojCasu,
        \DateTimeInterface       $platnostZpetne = null,
    ): static
    {
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

    public static function zrozeniGameconu(): static
    {
        return new static('2013-09-10 15:53:49');
    }
}
