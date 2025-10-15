<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\User
 */
class UserSqlStructure
{
    /**
     * @see User
     */
    public const _table = 'uzivatele_hodnoty';

    /**
     * @see User::$id
     */
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see User::$login
     */
    public const login_uzivatele = 'login_uzivatele';

    /**
     * @see User::$jmeno
     */
    public const jmeno_uzivatele = 'jmeno_uzivatele';

    /**
     * @see User::$prijmeni
     */
    public const prijmeni_uzivatele = 'prijmeni_uzivatele';

    /**
     * @see User::$uliceACp
     */
    public const ulice_a_cp_uzivatele = 'ulice_a_cp_uzivatele';

    /**
     * @see User::$mesto
     */
    public const mesto_uzivatele = 'mesto_uzivatele';

    /**
     * @see User::$stat
     */
    public const stat_uzivatele = 'stat_uzivatele';

    /**
     * @see User::$psc
     */
    public const psc_uzivatele = 'psc_uzivatele';

    /**
     * @see User::$telefon
     */
    public const telefon_uzivatele = 'telefon_uzivatele';

    /**
     * @see User::$datumNarozeni
     */
    public const datum_narozeni = 'datum_narozeni';

    /**
     * @see User::$hesloMd5
     */
    public const heslo_md5 = 'heslo_md5';

    /**
     * @see User::$email
     */
    public const email1_uzivatele = 'email1_uzivatele';

    /**
     * @see User::$nechceMaily
     */
    public const nechce_maily = 'nechce_maily';

    /**
     * @see User::$mrtvyMail
     */
    public const mrtvy_mail = 'mrtvy_mail';

    /**
     * @see User::$forumRazeni
     */
    public const forum_razeni = 'forum_razeni';

    /**
     * @see User::$random
     */
    public const random = 'random';

    /**
     * @see User::$zustatek
     */
    public const zustatek = 'zustatek';

    /**
     * @see User::$pohlavi
     */
    public const pohlavi = 'pohlavi';

    /**
     * @see User::$registrovan
     */
    public const registrovan = 'registrovan';

    /**
     * @see User::$ubytovanS
     */
    public const ubytovan_s = 'ubytovan_s';

    /**
     * @see User::$poznamka
     */
    public const poznamka = 'poznamka';

    /**
     * @see User::$pomocTyp
     */
    public const pomoc_typ = 'pomoc_typ';

    /**
     * @see User::$pomocVice
     */
    public const pomoc_vice = 'pomoc_vice';

    /**
     * @see User::$op
     */
    public const op = 'op';

    /**
     * @see User::$potvrzeniZakonnehoZastupce
     */
    public const potvrzeni_zakonneho_zastupce = 'potvrzeni_zakonneho_zastupce';

    /**
     * @see User::$potvrzeniProtiCovid19PridanoKdy
     */
    public const potvrzeni_proti_covid19_pridano_kdy = 'potvrzeni_proti_covid19_pridano_kdy';

    /**
     * @see User::$potvrzeniProtiCovid19OverenoKdy
     */
    public const potvrzeni_proti_covid19_overeno_kdy = 'potvrzeni_proti_covid19_overeno_kdy';

    /**
     * @see User::$infopultPoznamka
     */
    public const infopult_poznamka = 'infopult_poznamka';

    /**
     * @see User::$typDokladuTotoznosti
     */
    public const typ_dokladu_totoznosti = 'typ_dokladu_totoznosti';

    /**
     * @see User::$statniObcanstvi
     */
    public const statni_obcanstvi = 'statni_obcanstvi';

    /**
     * @see User::$zRychloregistrace
     */
    public const z_rychloregistrace = 'z_rychloregistrace';

    /**
     * @see User::$potvrzeniZakonnehoZastupceSoubor
     */
    public const potvrzeni_zakonneho_zastupce_soubor = 'potvrzeni_zakonneho_zastupce_soubor';
}
