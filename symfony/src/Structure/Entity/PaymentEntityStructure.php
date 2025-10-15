<?php

declare(strict_types=1);

namespace App\Structure\Entity;

/**
 * Property structure for @see \App\Entity\Payment
 */
class PaymentEntityStructure
{
    /**
     * @see Payment::$id
     */
    public const id = 'id';

    /**
     * @see Payment::$fioId
     */
    public const fioId = 'fioId';

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
    public const pripsanoNaUcetBanky = 'pripsanoNaUcetBanky';

    /**
     * @see Payment::$provedeno
     */
    public const provedeno = 'provedeno';

    /**
     * @see Payment::$nazevProtiuctu
     */
    public const nazevProtiuctu = 'nazevProtiuctu';

    /**
     * @see Payment::$cisloProtiuctu
     */
    public const cisloProtiuctu = 'cisloProtiuctu';

    /**
     * @see Payment::$kodBankyProtiuctu
     */
    public const kodBankyProtiuctu = 'kodBankyProtiuctu';

    /**
     * @see Payment::$nazevBankyProtiuctu
     */
    public const nazevBankyProtiuctu = 'nazevBankyProtiuctu';

    /**
     * @see Payment::$poznamka
     */
    public const poznamka = 'poznamka';

    /**
     * @see Payment::$skrytaPoznamka
     */
    public const skrytaPoznamka = 'skrytaPoznamka';

    /**
     * @see Payment::$beneficiary
     */
    public const beneficiary = 'beneficiary';

    /**
     * @see Payment::$madeBy
     */
    public const madeBy = 'madeBy';
}
