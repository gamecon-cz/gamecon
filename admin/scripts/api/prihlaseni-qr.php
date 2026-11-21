<?php

declare(strict_types=1);

use Gamecon\Uzivatel\PrihlaseniQrKodem;

/**
 * Stav přihlášení QR kódem, na který se ptá přihlašovací stránka adminu (soubory/prihlaseni-qr.js na webu).
 * Schválené přihlášení rovnou dokončí. Odpovídá jen o žádosti této session založené v adminu, proto ho smí volat
 * i nepřihlášený (ApiAdminu::BEZ_PRIHLASENI). Odpověď je společná s webem (web/moduly/api/prihlaseniQr.php).
 *
 * @var Uzivatel|null $u
 */

PrihlaseniQrKodem::zSession(doAdminu: true)->odpovezStavem($u !== null);
