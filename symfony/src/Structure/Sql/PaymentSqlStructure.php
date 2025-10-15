<?php

declare(strict_types=1);

namespace App\Structure\Sql;

/**
 * Structure for @see \App\Entity\Payment
 */
class PaymentSqlStructure
{
    /**
     * @see Payment
     */
    public const _table = 'platby';

    /**
     * @see Payment::$id
     */
    public const id = 'id';

    /**
     * @see Payment::$fioId
     */
    public const fio_id = 'fio_id';

    /**
     * @see Payment::$vs
     */
    public const vs = 'vs';

    /**
     * @see Payment::$castka
     */
    public const castka = 'castka';

    /**
     * @see Payment::$rok
     */
    public const rok = 'rok';

    /**
     * @see Payment::$pripsanoNaUcetBanky
     */
    public const pripsano_na_ucet_banky = 'pripsano_na_ucet_banky';

    /**
     * @see Payment::$provedeno
     */
    public const provedeno = 'provedeno';

    /**
     * @see Payment::$nazevProtiuctu
     */
    public const nazev_protiuctu = 'nazev_protiuctu';

    /**
     * @see Payment::$cisloProtiuctu
     */
    public const cislo_protiuctu = 'cislo_protiuctu';

    /**
     * @see Payment::$kodBankyProtiuctu
     */
    public const kod_banky_protiuctu = 'kod_banky_protiuctu';

    /**
     * @see Payment::$nazevBankyProtiuctu
     */
    public const nazev_banky_protiuctu = 'nazev_banky_protiuctu';

    /**
     * @see Payment::$poznamka
     */
    public const poznamka = 'poznamka';

    /**
     * @see Payment::$skrytaPoznamka
     */
    public const skryta_poznamka = 'skryta_poznamka';

    /**
     * @see Payment::$beneficiary
     */
    public const id_uzivatele = 'id_uzivatele';

    /**
     * @see Payment::$madeBy
     */
    public const provedl = 'provedl';
}
