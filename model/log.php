<?php

/**
 * Jednoduchá obalovací třída pro globální přístup k logování.
 */
class Log
{

    /**
     * TODO Info
     */

    /**
     * TODO Debug
     */

    /**
     * TODO Notice: Chyby, které se mohou za normálního provozu občas stát, ale
     * neměly by se stávat často, a nezabraňují dokončení požadavku.
     */

    /**
     * Chyby, které by se za normálního provozu něměly nikdy stávat, ale
     * nezabraňují dokončení požadavku.
     */
    static function warning($zprava)
    {
        // zatím jen jednoduché odeslání mailu
        (new GcMail)
            ->adresat('info@gamecon.cz')
            ->predmet('chyba GC webu: varování')
            ->text($zprava)
            ->odeslat();

        // nápady:
        // - nějak strukturovat (řetězec => přímo uložit, pole => uložit dat. strukturu)
        // - stacktrace a GET/POST/adresa/cookie hodnoty (?)
        // - nějak integrovat do výjimkovače
        // - nějak číst session a logovat, kdo akci provádí
    }

    /**
     * TODO Error: chyby, které zabrání dokončení požadavku.
     */

}
