<?php

declare(strict_types=1);

namespace Gamecon\Kostymy;

/**
 * Jedna rezervace kostýmu, jak ji čte @see PujcovnaKostymu
 */
final class RezervaceKostymu
{
    public function __construct(
        private readonly int $id,
        private readonly int $idKostymu,
        private readonly string $nazevKostymu,
        private readonly int $idAktivity,
        private readonly int $idUzivatele,
    ) {
    }

    /**
     * @param array<string, mixed> $radek
     */
    public static function zRadku(array $radek): self
    {
        return new self(
            id: (int) $radek['id'],
            idKostymu: (int) $radek['id_kostymu'],
            nazevKostymu: (string) $radek['nazev_kostymu'],
            idAktivity: (int) $radek['id_akce'],
            idUzivatele: (int) $radek['id_uzivatele'],
        );
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getIdKostymu(): int
    {
        return $this->idKostymu;
    }

    public function getNazevKostymu(): string
    {
        return $this->nazevKostymu;
    }

    public function getIdAktivity(): int
    {
        return $this->idAktivity;
    }

    public function getIdUzivatele(): int
    {
        return $this->idUzivatele;
    }
}
