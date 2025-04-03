<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use DbObject;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Uzivatel\SqlStruktura\PlatbySqlStruktura as Sql;

/**
 * @method static Platba|null zId(int $id, bool $zCache = false)
 */
class Platba extends DbObject
{
    protected static $tabulka = Sql::PLATBY_TABULKA;
    protected static $pk      = Sql::ID;

    public static function zFioId(int | string $fioId): ?self
    {
        $platba = self::zWhereRadek(Sql::FIO_ID . ' = ' . dbQv($fioId));

        if ($platba) {
            return $platba;
        }

        return null;
    }

    public function id(): ?int
    {
        $id = parent::id();

        return $id
            ? (int)$id
            : null;
    }

    public function idUzivatele(): ?int
    {
        $idUzivatele = $this->r[Sql::ID_UZIVATELE];

        return $idUzivatele
            ? (int)$idUzivatele
            : null;
    }

    public function fioId(): ?string
    {
        return $this->r[Sql::FIO_ID];
    }

    public function variabilniSymbol(): ?string
    {
        return $this->r[Sql::VS];
    }

    public function castka(): ?float
    {
        $castka = $this->r[Sql::CASTKA];

        return $castka !== null
            ? (float)$castka
            : null;
    }

    public function rok(): ?int
    {
        $rok = $this->r[Sql::ROK];

        return $rok !== null
            ? (int)$rok
            : null;
    }

    public function pripsanoNaUcetBanky(): ?string
    {
        $pripsanoNaUcetBanky = $this->r[Sql::PRIPSANO_NA_UCET_BANKY];

        return $pripsanoNaUcetBanky !== null
            ? (string)$pripsanoNaUcetBanky
            : null;
    }

    public function provedeno(): ?string
    {
        return $this->r[Sql::PROVEDENO];
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
        $povedl = $this->r[Sql::PROVEDL];

        return $povedl !== null
            ? (int)$povedl
            : null;
    }

    public function nazevProtiuctu(): ?string
    {
        return $this->r[Sql::NAZEV_PROTIUCTU];
    }

    public function cisloProtiuctu(): ?string
    {
        return $this->r[Sql::CISLO_PROTIUCTU];
    }

    public function kodBankyProtiuctu(): ?string
    {
        return $this->r[Sql::KOD_BANKY_PROTIUCTU];
    }

    public function nazevBankyProtiuctu(): ?string
    {
        return $this->r[Sql::NAZEV_BANKY_PROTIUCTU];
    }

    public function poznamka(): ?string
    {
        return $this->r[Sql::POZNAMKA];
    }

    public function skrytaPoznamka(): ?string
    {
        return $this->r[Sql::SKRYTA_POZNAMKA];
    }

    public function priradUzivateli(\Uzivatel $uzivatel): void
    {
        $this->r[Sql::ID_UZIVATELE] = $uzivatel->id();
    }
}
