<?php

/**
 * Tato nastavení jsou definovaná v SQL tabulce systemove_nastaveni
 * @see \Gamecon\SystemoveNastaveni\SystemoveNastaveni::zaznamyDoKonstant
 * a zde se definují jen na oko, aby je IDE znalo.
 *
 * TENTO SKRIPT NEINCLUDOVAT
 */
const KURZ_EURO = 0; // SELECT hodnota FROM systemove_nastaveni WHERE klic = 'KURZ_EURO'

const BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU = 0; // SELECT hodnota FROM systemove_nastaveni WHERE klic = 'BONUS_ZA_STANDARDNI_3H_AZ_5H_AKTIVITU'

const NEPLATIC_CASTKA_VELKY_DLUH = 0.0; // SELECT hodnota FROM systemove_nastaveni WHERE klic = 'NEPLATIC_CASTKA_VELKY_DLUH'
const NEPLATIC_CASTKA_POSLAL_DOST = 0.0; // SELECT hodnota FROM systemove_nastaveni WHERE klic = 'NEPLATIC_CASTKA_POSLAL_DOST'
const NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN = 0; // SELECT hodnota FROM systemove_nastaveni WHERE klic = 'NEPLATIC_POCET_DNU_PRED_VLNOU_KDY_JE_CHRANEN'

const AKTIVITA_EDITOVATELNA_X_MINUT_PRED_JEJIM_ZACATKEM = 0;
const AKTIVITA_EDITOVATELNA_X_MINUT_PO_JEJIM_KONCI = 0;
const PRIHLASENI_NA_POSLEDNI_CHVILI_X_MINUT_PRED_ZACATKEM_AKTIVITY = 0;

const AUTOMATICKY_UZAMKNOUT_AKTIVITU_X_MINUT_PO_ZACATKU = 0;
