<?php

namespace Gamecon\Dev;

/**
 * Ověřený obsah `?gcsso=` tokenu — výsledek {@see CrossSiteLogin::over}.
 * Podpis i platnost už jsou ověřené; shodu {@see self::$nonce} se spárovanou
 * cookie kontroluje volající (to teprve potvrdí, že jde o prohlížeč, který na
 * odkaz klikl).
 *
 * Identitu nese {@see self::$idUzivatele} — číselné `id_uzivatele`, ne e-mail.
 * ID je napříč ostrou i zmrazenými archivními snapshoty téhož kontinuálního
 * `uzivatele` stabilní; e-mail je proměnný a může se mezi ročníky přiřadit jinému
 * člověku, takže by se podle něj dalo přihlásit do cizího účtu.
 *
 * PHP 5.6-kompatibilní varianta (archivní ročníky 2015-2021 běží na PHP 5.6/7.3):
 * žádné property promotion, readonly, scalar typehinty ani návratové typy.
 */
final class OvereneSso
{
    /** @var int */
    public $idUzivatele;

    /** @var string */
    public $nonce;

    public function __construct($idUzivatele, $nonce)
    {
        $this->idUzivatele = (int) $idUzivatele;
        $this->nonce = (string) $nonce;
    }
}
