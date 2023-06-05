<?php

declare(strict_types=1);

namespace Gamecon\SystemoveNastaveni;

class SystemoveNastaveniKlice
{

    public const AKTIVITA_EDITOVATELNA_X_MINUT_PRED_JEJIM_ZACATKEM            = 'AKTIVITA_EDITOVATELNA_X_MINUT_PRED_JEJIM_ZACATKEM';
    public const AUTOMATICKY_UZAMKNOUT_AKTIVITU_X_MINUT_PO_ZACATKU            = 'AUTOMATICKY_UZAMKNOUT_AKTIVITU_X_MINUT_PO_ZACATKU';
    public const PRVNI_VLNA_KDY                                               = 'PRVNI_VLNA_KDY';
    public const DRUHA_VLNA_KDY                                               = 'DRUHA_VLNA_KDY';
    public const TRETI_VLNA_KDY                                               = 'TRETI_VLNA_KDY';
    public const PRISTI_VLNA_KDY                                              = 'PRISTI_VLNA_KDY';
    public const HROMADNE_ODHLASOVANI_1                                       = 'HROMADNE_ODHLASOVANI_1';
    public const HROMADNE_ODHLASOVANI_2                                       = 'HROMADNE_ODHLASOVANI_2';
    public const HROMADNE_ODHLASOVANI_3                                       = 'HROMADNE_ODHLASOVANI_3';
    public const GC_BEZI_OD                                                   = 'GC_BEZI_OD';
    public const GC_BEZI_DO                                                   = 'GC_BEZI_DO';
    public const REG_GC_DO                                                    = 'REG_GC_DO';
    public const REG_GC_OD                                                    = 'REG_GC_OD';
    public const JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE                            = 'JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE';
    public const KURZ_EURO                                                    = 'KURZ_EURO';
    public const NEPLATIC_CASTKA_POSLAL_DOST                                  = 'NEPLATIC_CASTKA_POSLAL_DOST';
    public const NEPLATIC_CASTKA_VELKY_DLUH                                   = 'NEPLATIC_CASTKA_VELKY_DLUH';
    public const NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN                 = 'NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN';
    public const PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE              = 'PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE';
    public const PRIHLASENI_NA_POSLEDNI_CHVILI_X_MINUT_PRED_ZACATKEM_AKTIVITY = 'PRIHLASENI_NA_POSLEDNI_CHVILI_X_MINUT_PRED_ZACATKEM_AKTIVITY';
    public const TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY                            = 'TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY';
    public const TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE                           = 'TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE';
    public const UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE                        = 'UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE';
    public const UCASTNIKY_LZE_PRIDAVAT_X_DNI_PO_GC_U_NEUZAVRENE_PREZENCE     = 'UCASTNIKY_LZE_PRIDAVAT_X_DNI_PO_GC_U_NEUZAVRENE_PREZENCE';
    public const UCASTNIKY_LZE_PRIDAVAT_X_MINUT_PO_KONCI_AKTIVITY             = 'UCASTNIKY_LZE_PRIDAVAT_X_MINUT_PO_KONCI_AKTIVITY';
    public const UPOZORNIT_NA_NEUZAMKNUTOU_AKTIVITU_X_MINUT_PO_KONCI          = 'UPOZORNIT_NA_NEUZAMKNUTOU_AKTIVITU_X_MINUT_PO_KONCI';
    public const ROCNIK                                                       = 'ROCNIK';
    public const PRUMERNE_LONSKE_VSTUPNE                                      = 'PRUMERNE_LONSKE_VSTUPNE';

    public const BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU = 'BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU';
    public const BONUS_ZA_1H_AKTIVITU                  = 'BONUS_ZA_1H_AKTIVITU';
    public const BONUS_ZA_2H_AKTIVITU                  = 'BONUS_ZA_2H_AKTIVITU';
    public const BONUS_ZA_6H_AZ_7H_AKTIVITU            = 'BONUS_ZA_6H_AZ_7H_AKTIVITU';
    public const BONUS_ZA_8H_AZ_9H_AKTIVITU            = 'BONUS_ZA_8H_AZ_9H_AKTIVITU';
    public const BONUS_ZA_10H_AZ_11H_AKTIVITU          = 'BONUS_ZA_10H_AZ_11H_AKTIVITU';
    public const BONUS_ZA_12H_AZ_13H_AKTIVITU          = 'BONUS_ZA_12H_AZ_13H_AKTIVITU';

    public const KOLIK_MINUT_JE_ODHLASENI_AKTIVITY_BEZ_POKUTY   = 'KOLIK_MINUT_JE_ODHLASENI_AKTIVITY_BEZ_POKUTY';
    public const POSILAT_MAIL_O_ODHLASENI_A_UVOLNENEM_UBYTOVANI = 'POSILAT_MAIL_O_ODHLASENI_A_UVOLNENEM_UBYTOVANI';

    public const MODRE_TRICKO_ZDARMA_OD = 'MODRE_TRICKO_ZDARMA_OD';

    /**
     * @return array{string}
     */
    public static function jednorocniKlice(): array
    {
        return [
            self::PRUMERNE_LONSKE_VSTUPNE,
        ];
    }

}
