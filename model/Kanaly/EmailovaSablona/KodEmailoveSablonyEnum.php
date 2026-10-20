<?php

declare(strict_types=1);

namespace Gamecon\Kanaly\EmailovaSablona;

/**
 * Code of an e-mail text. The value is what the column `emailova_sablona.kod`, the log of sent mails and the admin URLs hold.
 */
enum KodEmailoveSablonyEnum: string
{
    case OBNOVA_HESLA = 'obnova_hesla';
    case RYCHLA_REGISTRACE = 'rychla_registrace';

    case UVOLNENE_MISTO = 'uvolnene_misto';
    case NEDOSTAVENI_SE_NA_AKTIVITU = 'nedostaveni_se_na_aktivitu';
    case UZAVRENI_PREZENCE = 'uzavreni_prezence';

    case ODHLASENI_Z_GAMECONU = 'odhlaseni_z_gameconu';
    case HROMADNE_ODHLASENI_NEPLATIC_ZRUSENO = 'hromadne_odhlaseni_neplatic_zruseno';
    case VAROVANI_BRZKE_ODHLASENI_NEPLATIC = 'varovani_brzke_odhlaseni_neplatic';

    case UPOMINKA_TYDEN_PRITOMEN = 'upominka_tyden_pritomen';
    case UPOMINKA_TYDEN_JEN_PRIHLASEN = 'upominka_tyden_jen_prihlasen';
    case UPOMINKA_TYDEN_NEDORAZIL_DRIVE = 'upominka_tyden_nedorazil_drive';
    case UPOMINKA_TYDEN_NEDORAZIL_NIKDY = 'upominka_tyden_nedorazil_nikdy';
    case UPOMINKA_MESIC_PRITOMEN = 'upominka_mesic_pritomen';
    case UPOMINKA_MESIC_JEN_PRIHLASEN = 'upominka_mesic_jen_prihlasen';
    case UPOMINKA_MESIC_NEDORAZIL_DRIVE = 'upominka_mesic_nedorazil_drive';
    case UPOMINKA_MESIC_NEDORAZIL_NIKDY = 'upominka_mesic_nedorazil_nikdy';

    /** Has no row in `emailova_sablona`: its wording is kept per year in `upominka_vlastni_zneni`, so it is not in the catalog. */
    case UPOMINKA_VLASTNI = 'upominka_vlastni';

    case PROMLCENI_VAROVANI_MESIC = 'promlceni_varovani_mesic';
    case PROMLCENI_VAROVANI_TYDEN = 'promlceni_varovani_tyden';

    case CFO_BRZKE_ODHLASENI_NEPLATICU = 'cfo_brzke_odhlaseni_neplaticu';
    case CFO_NESPAROVANE_PLATBY = 'cfo_nesparovane_platby';
    case CFO_NEPLATICI_VAROVANI_ODESLANO = 'cfo_neplatici_varovani_odeslano';
    case CFO_ODHLASENI_NEPLATICU_PROVEDENO = 'cfo_odhlaseni_neplaticu_provedeno';
    case CFO_PODEZRELE_VYSOKA_PLATBA = 'cfo_podezrele_vysoka_platba';
    case CFO_PROMLCENI_NIKDO = 'cfo_promlceni_nikdo';
    case CFO_PROMLCENI_PRED = 'cfo_promlceni_pred';
    case CFO_PROMLCENI_DOKONCENO = 'cfo_promlceni_dokonceno';
    case CFO_PROMLCENI_VAROVANI_MESIC_ODESLANO = 'cfo_promlceni_varovani_mesic_odeslano';
    case CFO_PROMLCENI_VAROVANI_TYDEN_ODESLANO = 'cfo_promlceni_varovani_tyden_odeslano';
    case CFO_UPOMINKY_TYDEN_ODESLANO = 'cfo_upominky_tyden_odeslano';
    case CFO_UPOMINKY_MESIC_ODESLANO = 'cfo_upominky_mesic_odeslano';
    case CFO_UPOMINKY_RUCNI_ODESLANO = 'cfo_upominky_rucni_odeslano';
    case CFO_UPOMINKY_VLASTNI_ODESLANO = 'cfo_upominky_vlastni_odeslano';

    case INFO_ODHLASIL_ALE_PLATIL = 'info_odhlasil_ale_platil';
    case INFO_ODHLASIL_MEL_OBJEDNAVKY = 'info_odhlasil_mel_objednavky';
    case INFO_HROMADNA_AKTIVACE_AKTIVIT = 'info_hromadna_aktivace_aktivit';

    case BFGR_REPORT = 'bfgr_report';
    case BFGR_REPORT_CHYBA = 'bfgr_report_chyba';
}
