<?php

/**
 * Tato nastavení jsou definovaná v SQL tabulce systemove_nastaveni
 * @see \Gamecon\SystemoveNastaveni\SystemoveNastaveni::zaznamyDoKonstant
 * a zde se definují jen na oko, aby je IDE znalo.
 *
 * TENTO SKRIPT NEINCLUDOVAT
 */
const KURZ_EURO = 0; // SELECT hodnota FROM systemove_nastaveni WHERE klic = 'KURZ_EURO'

const NEPLATIC_CASTKA_VELKY_DLUH                   = 0.0; // SELECT hodnota FROM systemove_nastaveni WHERE klic = 'NEPLATIC_CASTKA_VELKY_DLUH'
const NEPLATIC_CASTKA_POSLAL_DOST                  = 0.0; // SELECT hodnota FROM systemove_nastaveni WHERE klic = 'NEPLATIC_CASTKA_POSLAL_DOST'
const NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN = 0; // SELECT hodnota FROM systemove_nastaveni WHERE klic = 'NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN'
const TEXT_PRO_SPAROVANI_ODCHOZI_PLATBY            = '';

const UBYTOVANI_LZE_OBJEDNAT_A_MENIT_DO_DNE           = '';
const JIDLO_LZE_OBJEDNAT_A_MENIT_DO_DNE               = '';
const PREDMETY_BEZ_TRICEK_LZE_OBJEDNAT_A_MENIT_DO_DNE = '';
const TRICKA_LZE_OBJEDNAT_A_MENIT_DO_DNE              = '';

const AKTIVITA_EDITOVATELNA_X_MINUT_PRED_JEJIM_ZACATKEM            = 0;
const UCASTNIKY_LZE_PRIDAVAT_X_MINUT_PO_KONCI_AKTIVITY             = 0;
const PRIHLASENI_NA_POSLEDNI_CHVILI_X_MINUT_PRED_ZACATKEM_AKTIVITY = 0;
const UCASTNIKY_LZE_PRIDAVAT_X_DNI_PO_GC_U_NEUZAVRENE_PREZENCE     = 0;

const AUTOMATICKY_UZAMKNOUT_AKTIVITU_X_MINUT_PO_ZACATKU   = 0;
const UPOZORNIT_NA_NEUZAMKNUTOU_AKTIVITU_X_MINUT_PO_KONCI = 0;

const ROCNIK = 0;

const BONUS_ZA_1H_AKTIVITU                  = 0;
const BONUS_ZA_2H_AKTIVITU                  = 0;
const BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU = 0; // SELECT hodnota FROM systemove_nastaveni WHERE klic = 'BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU'
const BONUS_ZA_6H_AZ_7H_AKTIVITU            = 0;
const BONUS_ZA_8H_AZ_9H_AKTIVITU            = 0;
const BONUS_ZA_10H_AZ_11H_AKTIVITU          = 0;
const BONUS_ZA_12H_AZ_13H_AKTIVITU          = 0;
