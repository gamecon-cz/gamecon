<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'mutex')]
#[ORM\UniqueConstraint(name: 'UNIQ_akce', columns: ['akce'])]
class Mutex
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_mutex', type: Types::INTEGER, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'akce', type: Types::STRING, length: 128)]
    private string $akce;

    #[ORM\Column(name: 'klic', type: Types::STRING, length: 128, unique: true)]
    private string $klic;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'zamknul', referencedColumnName: 'id_uzivatele', nullable: true, onDelete: 'SET NULL', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private ?User $lockedBy = null;

    #[ORM\Column(name: 'od', type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeInterface $from;

    #[ORM\Column(name: 'do', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeInterface $to = null;

    public function getId(): int
    {
        return $this->id;
    }

    public function getAkce(): string
    {
        return $this->akce;
    }

    public function setAkce(string $akce): self
    {
        $this->akce = $akce;

        return $this;
    }

    public function getKlic(): string
    {
        return $this->klic;
    }

    public function setKlic(string $klic): self
    {
        $this->klic = $klic;

        return $this;
    }

    public function getLockedBy(): ?User
    {
        return $this->lockedBy;
    }

    public function setLockedBy(?User $lockedBy): self
    {
        $this->lockedBy = $lockedBy;

        return $this;
    }

    public function getFrom(): \DateTimeInterface
    {
        return $this->from;
    }

    public function setFrom(\DateTimeInterface $from): self
    {
        $this->from = $from;

        return $this;
    }

    public function getTo(): ?\DateTimeInterface
    {
        return $this->to;
    }

    public function setTo(?\DateTimeInterface $to): self
    {
        $this->to = $to;

        return $this;
    }
}
