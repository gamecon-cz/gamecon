<?php

declare(strict_types=1);

namespace Gamecon\Aktivita\OnlinePrezence;

use Gamecon\Logger\LogUdalosti;

/**
 * Stav se drží v session, ne v cookie: cookie si nastaví i ten, kdo souhlas
 * odklikat nechce, takže by se odemčení neprojevilo v logu a záznam by
 * zachytil jen ty poctivé. Session drží rozhodnutí na serveru, kde zápis do
 * logu proběhne na stejné cestě, která kontakty odemyká.
 */
class PotvrzeniZobrazeniKontaktu
{
    public const ZPRAVA_LOGU = 'odemcení kontaktů účastníků';

    private const KLIC_SESSION = 'potvrzeneZobrazeniKontaktu';

    public function __construct(
        private readonly LogUdalosti $logUdalosti,
    ) {
    }

    public function jePotvrzeno(int $idAktivity): bool
    {
        return ! empty($_SESSION[self::KLIC_SESSION][$idAktivity]);
    }

    public function potvrd(\Uzivatel $vypravec, int $idAktivity): void
    {
        if ($this->jePotvrzeno($idAktivity)) {
            return;
        }

        $_SESSION[self::KLIC_SESSION][$idAktivity] = true;

        $this->logUdalosti->zalogovatUdalost(
            $vypravec,
            self::ZPRAVA_LOGU,
            [
                'idAktivity' => $idAktivity,
            ],
        );
    }
}
