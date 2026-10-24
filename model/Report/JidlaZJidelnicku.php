<?php

declare(strict_types=1);

namespace Gamecon\Report;

use App\Entity\ProductVariant;
use App\Enum\ProductTagCode;
use App\Service\MealSlot;
use Gamecon\Role\Role;

/**
 * Exporty jídelníčku: kolik se má uvařit kterého jídla a co dostane který účastník.
 *
 * Porce bez výběru dostane výchozí jídlo slotu — označené, jinak první v pořadí. Musí to sedět
 * s tím, co účastník vidí, @see \App\Service\MealSlot::getEffectiveDish()
 *
 * Počítají se porce přihlášených na letošní GameCon, stejně jako na stravenkách.
 */
class JidlaZJidelnicku
{
    public const HLAVICKY_POCTU = ['den', 'porce', 'jidlo', 'vegetarianske', 'vybrali_si', 'bez_vyberu_vychozi', 'celkem'];

    public const HLAVICKY_VYBERU = ['id_uzivatele', 'login_uzivatele', 'jmeno_uzivatele', 'prijmeni_uzivatele', 'den', 'porce', 'jidlo', 'vybral_si'];

    /**
     * SQL výraz s id jídla, které porce opravdu dostane: vybrané, jinak výchozí jídlo slotu.
     */
    public static function efektivniJidloSql(string $nakupy): string
    {
        return <<<SQL
            COALESCE({$nakupy}.meal_dish_id, (
                SELECT vychozi_jidlo.id
                FROM meal_dish AS vychozi_jidlo
                WHERE vychozi_jidlo.variant_id = {$nakupy}.variant_id
                    AND vychozi_jidlo.year = {$nakupy}.rok
                ORDER BY vychozi_jidlo.is_default DESC, vychozi_jidlo.position, vychozi_jidlo.id
                LIMIT 1
            ))
            SQL;
    }

    /**
     * Řádek za každé jídlo jídelníčku, i za to, které si nikdo nevybral — jídelna má vidět celý
     * lístek. Porce bez jídelníčku (snídaně, nebo jídla ještě nezadaná) mají řádek bez jídla.
     *
     * @return list<array<string, string|int>>
     */
    public function poctyJidel(int $rocnik): array
    {
        $efektivniJidlo = self::efektivniJidloSql('nakupy');
        $pocty          = dbFetchAll(<<<SQL
            SELECT varianta.id AS variant_id,
                   COALESCE(varianta.accommodation_day, predmety.ubytovani_den) AS den,
                   TRIM(predmety.nazev) AS porce,
                   jidlo.id AS jidlo_id, jidlo.name AS jidlo_nazev, jidlo.vegetarian, jidlo.position,
                   SUM(nakupy.meal_dish_id IS NOT NULL) AS vybrali_si,
                   SUM(nakupy.meal_dish_id IS NULL) AS bez_vyberu,
                   COUNT(*) AS celkem
            FROM shop_nakupy AS nakupy
            JOIN platne_role_uzivatelu AS role
                ON role.id_uzivatele = nakupy.id_uzivatele AND role.id_role = $0
            JOIN product_variant AS varianta
                ON varianta.id = nakupy.variant_id
            JOIN shop_predmety AS predmety
                ON predmety.id_predmetu = varianta.product_id
            JOIN product_product_tag AS stitek_jidla
                ON stitek_jidla.product_id = predmety.id_predmetu
            JOIN product_tag AS kategorie_jidla
                ON kategorie_jidla.id = stitek_jidla.tag_id AND kategorie_jidla.code = $1
            LEFT JOIN meal_dish AS jidlo
                ON jidlo.id = {$efektivniJidlo}
            WHERE nakupy.rok = $2
            GROUP BY varianta.id, jidlo.id
            SQL,
            [0 => Role::PRIHLASEN_NA_LETOSNI_GC($rocnik), 1 => ProductTagCode::JIDLO->value, 2 => $rocnik],
        );

        $jidla = dbFetchAll(<<<'SQL'
            SELECT meal_dish.id, meal_dish.variant_id, meal_dish.name, meal_dish.vegetarian, meal_dish.position,
                   COALESCE(product_variant.accommodation_day, shop_predmety.ubytovani_den) AS den,
                   TRIM(shop_predmety.nazev) AS porce
            FROM meal_dish
            JOIN product_variant
                ON product_variant.id = meal_dish.variant_id
            JOIN shop_predmety
                ON shop_predmety.id_predmetu = product_variant.product_id
            WHERE meal_dish.year = $0
            SQL,
            [0 => $rocnik],
        );

        /** @var array<string, array{den: int, porce: string, poradi: int, jidlo: string, vegetarianske: string, vybrali_si: int|string, bez_vyberu_vychozi: int|string, celkem: int}> $radky */
        $radky = [];
        foreach ($jidla as $jidlo) {
            $radky[$jidlo['variant_id'] . '-' . $jidlo['id']] = [
                'den'                => (int)$jidlo['den'],
                'porce'              => $jidlo['porce'],
                'poradi'             => (int)$jidlo['position'],
                'jidlo'              => $jidlo['name'],
                'vegetarianske'      => $jidlo['vegetarian'] ? 'ano' : 'ne',
                'vybrali_si'         => 0,
                'bez_vyberu_vychozi' => 0,
                'celkem'             => 0,
            ];
        }
        foreach ($pocty as $pocet) {
            if ($pocet['jidlo_id'] === null) {
                $radky[$pocet['variant_id'] . '-'] = [
                    'den'                => (int)$pocet['den'],
                    'porce'              => $pocet['porce'],
                    'poradi'             => PHP_INT_MAX,
                    'jidlo'              => '',
                    'vegetarianske'      => '',
                    'vybrali_si'         => '',
                    'bez_vyberu_vychozi' => '',
                    'celkem'             => (int)$pocet['celkem'],
                ];
                continue;
            }
            $klic = $pocet['variant_id'] . '-' . $pocet['jidlo_id'];
            $radky[$klic] ??= [
                'den'           => (int)$pocet['den'],
                'porce'         => $pocet['porce'],
                'poradi'        => (int)$pocet['position'],
                'jidlo'         => $pocet['jidlo_nazev'],
                'vegetarianske' => $pocet['vegetarian'] ? 'ano' : 'ne',
            ];
            $radky[$klic]['vybrali_si']         = (int)$pocet['vybrali_si'];
            $radky[$klic]['bez_vyberu_vychozi'] = (int)$pocet['bez_vyberu'];
            $radky[$klic]['celkem']             = (int)$pocet['celkem'];
        }

        usort($radky, static fn(array $a, array $b): int => [$a['den'], MealSlot::getMealOrder($a['porce']), $a['porce'], $a['poradi'], $a['jidlo']]
            <=> [$b['den'], MealSlot::getMealOrder($b['porce']), $b['porce'], $b['poradi'], $b['jidlo']]);

        return array_map(static fn(array $radek): array => [
            'den'                => self::nazevDne($radek['den']),
            'porce'              => $radek['porce'],
            'jidlo'              => $radek['jidlo'],
            'vegetarianske'      => $radek['vegetarianske'],
            'vybrali_si'         => $radek['vybrali_si'],
            'bez_vyberu_vychozi' => $radek['bez_vyberu_vychozi'],
            'celkem'             => $radek['celkem'],
        ], $radky);
    }

    /**
     * Kdo dostane jaké jídlo k obědům a večeřím. Snídaně jsou švédský stůl, nevybírá se k nim.
     *
     * @return list<array<string, string|int>>
     */
    public function vyberUcastniku(int $rocnik): array
    {
        $efektivniJidlo = self::efektivniJidloSql('nakupy');
        $radky          = dbFetchAll(<<<SQL
            SELECT uzivatele.id_uzivatele, uzivatele.login_uzivatele, uzivatele.jmeno_uzivatele, uzivatele.prijmeni_uzivatele,
                   COALESCE(varianta.accommodation_day, predmety.ubytovani_den) AS den,
                   TRIM(predmety.nazev) AS porce,
                   COALESCE(jidlo.name, '') AS jidlo,
                   IF(nakupy.meal_dish_id IS NULL, 'ne', 'ano') AS vybral_si
            FROM shop_nakupy AS nakupy
            JOIN uzivatele_hodnoty AS uzivatele
                ON uzivatele.id_uzivatele = nakupy.id_uzivatele
            JOIN platne_role_uzivatelu AS role
                ON role.id_uzivatele = nakupy.id_uzivatele AND role.id_role = $0
            JOIN product_variant AS varianta
                ON varianta.id = nakupy.variant_id
            JOIN shop_predmety AS predmety
                ON predmety.id_predmetu = varianta.product_id
            JOIN product_product_tag AS stitek_jidla
                ON stitek_jidla.product_id = predmety.id_predmetu
            JOIN product_tag AS kategorie_jidla
                ON kategorie_jidla.id = stitek_jidla.tag_id AND kategorie_jidla.code = $1
            LEFT JOIN meal_dish AS jidlo
                ON jidlo.id = {$efektivniJidlo}
            WHERE nakupy.rok = $2
                AND NOT EXISTS (
                    SELECT 1
                    FROM product_product_tag AS stitek_snidane
                    JOIN product_tag AS snidane
                        ON snidane.id = stitek_snidane.tag_id AND snidane.code = $3
                    WHERE stitek_snidane.product_id = predmety.id_predmetu
                )
            ORDER BY den, FIELD(SUBSTRING_INDEX(TRIM(predmety.nazev), ' ', 1), 'Oběd', 'Večeře'),
                     uzivatele.prijmeni_uzivatele, uzivatele.jmeno_uzivatele, uzivatele.id_uzivatele
            SQL,
            [
                0 => Role::PRIHLASEN_NA_LETOSNI_GC($rocnik),
                1 => ProductTagCode::JIDLO->value,
                2 => $rocnik,
                3 => ProductTagCode::SNIDANE->value,
            ],
        );

        return array_map(static fn(array $radek): array => [
            'id_uzivatele'       => (int)$radek['id_uzivatele'],
            'login_uzivatele'    => $radek['login_uzivatele'],
            'jmeno_uzivatele'    => $radek['jmeno_uzivatele'],
            'prijmeni_uzivatele' => $radek['prijmeni_uzivatele'],
            'den'                => self::nazevDne((int)$radek['den']),
            'porce'              => $radek['porce'],
            'jidlo'              => $radek['jidlo'],
            'vybral_si'          => $radek['vybral_si'],
        ], $radky);
    }

    private static function nazevDne(int $den): string
    {
        return ProductVariant::NIGHT_NAMES[$den] ?? (string)$den;
    }
}
