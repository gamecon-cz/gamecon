<?php

declare(strict_types=1);

namespace Gamecon\PushNotifikace;

use Gamecon\Uzivatel\PrepnutiNaUzivatele;
use Gamecon\Uzivatel\TrvalePrihlaseni;

/**
 * Kdo a z jakého přihlášení se ptá na notifikace tohoto zařízení. Všechno je ze session a z cookie na serveru,
 * nic z toho neposílá prohlížeč v těle požadavku.
 */
final readonly class KontextZarizeni
{
    /**
     * @param bool     $prepnuto             přihlášený je ve skutečnosti admin přepnutý na cizí účet
     * @param int|null $idTrvalehoPrihlaseni trvalé přihlášení uživatele v tomto prohlížeči, null když žádné nemá
     */
    public function __construct(
        public int $idUzivatele,
        public bool $prepnuto,
        public ?int $idTrvalehoPrihlaseni,
    ) {
    }

    public static function zPrihlaseneho(\Uzivatel $prihlaseny): self
    {
        $idUzivatele = (int) $prihlaseny->id();
        $prepnuto = PrepnutiNaUzivatele::zSession()->isPrepnuto();

        return new self(
            idUzivatele: $idUzivatele,
            prepnuto: $prepnuto,
            // Po přepnutí nese prohlížeč cookie toho, kdo se přepnul, a ta trvalé přihlášení cizího účtu neurčuje.
            idTrvalehoPrihlaseni: $prepnuto
                ? null
                : TrvalePrihlaseni::getIdNaTomtoZarizeni($idUzivatele),
        );
    }
}
