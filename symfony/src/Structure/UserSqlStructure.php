<?php

declare(strict_types=1);

namespace App\Structure;

/**
 * Structure for @see \App\Entity\User.
 */
class UserSqlStructure
{
    public const ID = 'id_uzivatele';
    public const LOGIN = 'login_uzivatele';
    public const JMENO = 'jmeno_uzivatele';
    public const PRIJMENI = 'prijmeni_uzivatele';
    public const ULICE_A_CP = 'ulice_a_cp_uzivatele';
    public const MESTO = 'mesto_uzivatele';
    public const STAT = 'stat_uzivatele';
    public const PSC = 'psc_uzivatele';
    public const TELEFON = 'telefon_uzivatele';
    public const DATUM_NAROZENI = 'datum_narozeni';
    public const HESLO_MD5 = 'heslo_md5';
    public const EMAIL = 'email1_uzivatele';
    public const NECHCE_MAILY = 'nechce_maily';
    public const MRTVY_MAIL = 'mrtvy_mail';
    public const FORUM_RAZENI = 'forum_razeni';
    public const RANDOM = 'random';
    public const ZUSTATEK = 'zustatek';
    public const POHLAVI = 'pohlavi';
    public const REGISTROVAN = 'registrovan';
    public const UBYTOVAN_S = 'ubytovan_s';
    public const POZNAMKA = 'poznamka';
    public const POMOC_TYP = 'pomoc_typ';
    public const POMOC_VICE = 'pomoc_vice';
    public const OP = 'op';
    public const POTVRZENI_ZAKONNEHO_ZASTUPCE = 'potvrzeni_zakonneho_zastupce';
    public const POTVRZENI_PROTI_COVID19_PRIDANO_KDY = 'potvrzeni_proti_covid19_pridano_kdy';
    public const POTVRZENI_PROTI_COVID19_OVERENO_KDY = 'potvrzeni_proti_covid19_overeno_kdy';
    public const INFOPULT_POZNAMKA = 'infopult_poznamka';
    public const TYP_DOKLADU_TOTOZNOSTI = 'typ_dokladu_totoznosti';
    public const STATNI_OBCANSTVI = 'statni_obcanstvi';
    public const Z_RYCHLOREGISTRACE = 'z_rychloregistrace';
    public const POTVRZENI_ZAKONNEHO_ZASTUPCE_SOUBOR = 'potvrzeni_zakonneho_zastupce_soubor';
}
