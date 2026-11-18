<?php

declare(strict_types=1);

use Gamecon\Uzivatel\PrihlaseniQrKodem;

/**
 * Stav přihlášení QR kódem, na který se ptá přihlašovací stránka webu (soubory/prihlaseni-qr.js). Schválené
 * přihlášení rovnou dokončí, odpovídá jen o žádosti této session. Odpověď je společná s adminem
 * (admin/scripts/api/prihlaseni-qr.php), liší se jen žádostí, o které odpovídá.
 *
 * @var Uzivatel|null $u
 */

PrihlaseniQrKodem::zSession(doAdminu: false)->odpovezStavem($u !== null);
