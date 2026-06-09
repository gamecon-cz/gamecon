<?php

declare(strict_types=1);

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
 */
final readonly class OvereneSso
{
    public function __construct(
        public int $idUzivatele,
        public string $nonce,
    ) {
    }
}
