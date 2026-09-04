<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Codes in product_tag.code.
 *
 * These replaced the old shop_predmety.typ column, and until now lived as string
 * literals spread across repositories, services and validators. A typo in one of those
 * — 'tricka' for 'tricko', or a Czech diacritic — matches nothing and fails silently:
 * no error, no failing test, the feature simply never applies.
 *
 * Seven of them are categories, one per product, mirroring the old typ 1–7. MIKINA is
 * different: it is a sub-tag carried in addition to PREDMET, replacing the old
 * podtyp='mikina'.
 */
enum ProductTagCode: string
{
    case PREDMET = 'predmet';
    case UBYTOVANI = 'ubytovani';
    case TRICKO = 'tricko';
    case JIDLO = 'jidlo';
    case VSTUPNE = 'vstupne';
    case PARCON = 'parcon';
    case PROPLACENI_BONUSU = 'proplaceni-bonusu';

    case MIKINA = 'mikina';

    /**
     * The category tags — exactly one of these per product, the successor of typ 1–7.
     *
     * @return self[]
     */
    public static function categories(): array
    {
        return [
            self::PREDMET,
            self::UBYTOVANI,
            self::TRICKO,
            self::JIDLO,
            self::VSTUPNE,
            self::PARCON,
            self::PROPLACENI_BONUSU,
        ];
    }

    public function isCategory(): bool
    {
        return in_array($this, self::categories(), true);
    }

    public function label(): string
    {
        return match ($this) {
            self::PREDMET           => 'Předmět',
            self::UBYTOVANI         => 'Ubytování',
            self::TRICKO            => 'Tričko',
            self::JIDLO             => 'Jídlo',
            self::VSTUPNE           => 'Vstupné',
            self::PARCON            => 'ParCon mini-akce',
            self::PROPLACENI_BONUSU => 'Výplata bonusu (interní)',
            self::MIKINA            => 'Mikina',
        };
    }
}
