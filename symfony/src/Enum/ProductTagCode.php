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
 * Seven of them are categories, one per product, mirroring the old typ 1–7. The rest are
 * sub-tags carried in addition to a category, each declaring which category it belongs on.
 *
 * Codes separate words with an underscore, never a dash: a tag names one concept, and the
 * underscore reads as the space in that name. (Product codes are a different namespace —
 * there a dash does separate parts, as in `Hd-2L-ct` = type `Hd-2L`, night `ct`.)
 */
enum ProductTagCode: string
{
    case PREDMET = 'predmet';
    case UBYTOVANI = 'ubytovani';
    case TRICKO = 'tricko';
    case JIDLO = 'jidlo';
    case VSTUPNE = 'vstupne';
    case PARCON = 'parcon';
    case PROPLACENI_BONUSU = 'proplaceni_bonusu';

    case MIKINA = 'mikina';

    case SPACAK = 'spacak';

    case TRICKO_MODRE = 'tricko_modre';

    case TRICKO_CERVENE = 'tricko_cervene';

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

    /**
     * Sub-tags qualify a category instead of being one, and each only makes sense on the
     * category it qualifies — a hoodie is a kind of merch, so `mikina` on anything but
     * `predmet` describes a product that cannot exist.
     *
     * @return array<string, self> sub-tag value => the category it requires
     */
    public static function subTagCategories(): array
    {
        return [
            self::MIKINA->value         => self::PREDMET,
            self::SPACAK->value         => self::UBYTOVANI,
            self::TRICKO_MODRE->value   => self::TRICKO,
            self::TRICKO_CERVENE->value => self::TRICKO,
        ];
    }

    public function requiredCategory(): ?self
    {
        return self::subTagCategories()[$this->value] ?? null;
    }

    /**
     * The legacy shop_predmety.typ this category replaced, as the compatibility view
     * still reports it. Legacy rows carry the number, not the tag, so anything reading
     * them has to translate — see TypPredmetu.
     */
    public static function fromLegacyTyp(int $typ): ?self
    {
        return match ($typ) {
            1       => self::PREDMET,
            2       => self::UBYTOVANI,
            3       => self::TRICKO,
            4       => self::JIDLO,
            5       => self::VSTUPNE,
            6       => self::PARCON,
            7       => self::PROPLACENI_BONUSU,
            default => null,
        };
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
            self::SPACAK            => 'Spacák',
            self::TRICKO_MODRE      => 'Modré tričko',
            self::TRICKO_CERVENE    => 'Červené tričko',
        };
    }
}
