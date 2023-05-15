<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use DbObject;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Uzivatel\SqlStruktura\PlatbySqlStruktura as Sql;

class Platba extends DbObject
{
    protected static $tabulka = Sql::PLATBY_TABULKA;
    protected static $pk      = Sql::ID;

    public function id(): ?int
    {
        $id = parent::id();
        return $id
            ? (int)$id
            : null;
    }

    public function idUzivatele(): ?int
    {
        $idUzivatele = $this->r[Sql::ID_UZIVATELE] ?? null;
        return $idUzivatele
            ? (int)$idUzivatele
            : null;
    }

    public function fioId(): ?string
    {
        return $this->r[Sql::FIO_ID] ?? null;
    }

    public function castka(): ?float
    {
        $castka = $this->r[Sql::CASTKA] ?? null;
        return $castka !== null
            ? (float)$castka
            : null;
    }

    public function rok(): ?int
    {
        $rok = $this->r[Sql::ROK] ?? null;
        return $rok !== null
            ? (int)$rok
            : null;
    }

    public function pripsanoNaUcetBanky(): ?string
    {
        $pripsanoNaUcetBanky = $this->r[Sql::PRIPSANO_NA_UCET_BANKY] ?? null;
        return $pripsanoNaUcetBanky !== null
            ? (string)$pripsanoNaUcetBanky
            : null;
    }

    public function provedeno(): ?string
    {
        return $this->r[Sql::PROVEDENO] ?? null;
    }

    public function provedenoObject(): ?DateTimeImmutableStrict
    {
        $provedeno = $this->provedeno();
        return $provedeno !== null
            ? new DateTimeImmutableStrict($provedeno)
            : null;
    }

    public function provedl(): ?int
    {
        $povedl = $this->r[Sql::PROVEDL] ?? null;
        return $povedl !== null
            ? (int)$povedl
            : null;
    }

    public function stranka(): ?string
    {
        return $this->r[Sql::POZNAMKA] ?? null;
    }

}
