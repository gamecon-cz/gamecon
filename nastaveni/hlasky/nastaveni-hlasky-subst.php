<?php

declare(strict_types=1);

use Gamecon\Hlaska\Hlaska;

// E-mail texts are not here. They are in the database and edited in the admin (Web / Texty e-mailů),
// viz Gamecon\Kanaly\EmailovaSablona\EmailoveSablony.
return [
    'chybaClenaTymu' => 'Nepodařilo se přihlásit tým. Při přihlašování uživatele %1 (id %2) se u něj objevila chyba: %3',
    'odhlaseniZGc'   => 'Odhlášení z GameConu ' . ROCNIK . ' proběhlo úspěšně',
    'prihlaseniNaGc' => 'Přihlášení na GameCon ' . ROCNIK . ' proběhlo úspěšně',
    'kapacitaMaxUpo' => 'Z ubytovací kapacity typu %1 je naplněno %2 míst z maxima %3 míst.',
    Hlaska::NEWSLETTER_PRIHLASEN => 'E-mail %1 přihlášen k občasným newsletterům',
];
