<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Sleva udělená uživateli.
 */
#[ORM\Entity]
#[ORM\Table(name: 'slevy')]
#[ORM\Index(columns: ['id_uzivatele'], name: 'id_uzivatele_idx')]
#[ORM\Index(columns: ['provedl'], name: 'provedl_idx')]
class Discount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false)]
    private User $uzivatel;

    #[ORM\Column(name: 'castka', type: Types::DECIMAL, precision: 10, scale: 2, nullable: false)]
    private string $castka;

    #[ORM\Column(name: 'rok', type: Types::INTEGER, nullable: false)]
    private int $rok;

    #[ORM\Column(name: 'provedeno', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTimeInterface $provedeno;

    #[ORM\Column(name: 'provedl', type: Types::INTEGER, nullable: true)]
    private ?int $provedl = null;

    #[ORM\Column(name: 'poznamka', type: Types::TEXT, nullable: true)]
    private ?string $poznamka = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUzivatel(): User
    {
        return $this->uzivatel;
    }

    public function setUzivatel(User $uzivatel): static
    {
        $this->uzivatel = $uzivatel;

        return $this;
    }

    public function getCastka(): string
    {
        return $this->castka;
    }

    public function setCastka(string $castka): static
    {
        $this->castka = $castka;

        return $this;
    }

    public function getRok(): int
    {
        return $this->rok;
    }

    public function setRok(int $rok): static
    {
        $this->rok = $rok;

        return $this;
    }

    public function getProvedeno(): \DateTimeInterface
    {
        return $this->provedeno;
    }

    public function setProvedeno(\DateTimeInterface $provedeno): static
    {
        $this->provedeno = $provedeno;

        return $this;
    }

    public function getProvedl(): ?int
    {
        return $this->provedl;
    }

    public function setProvedl(?int $provedl): static
    {
        $this->provedl = $provedl;

        return $this;
    }

    public function getPoznamka(): ?string
    {
        return $this->poznamka;
    }

    public function setPoznamka(?string $poznamka): static
    {
        $this->poznamka = $poznamka;

        return $this;
    }
}
