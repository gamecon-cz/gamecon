<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PermissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Legacy @see \Gamecon\Prava
 */
#[ORM\Entity(repositoryClass: PermissionRepository::class)]
#[ORM\Table(name: 'r_prava_soupis')]
#[ORM\UniqueConstraint(name: 'UNIQ_kod_prava', columns: ['kod_prava'])]
#[UniqueEntity(fields: ['code'], message: 'Právo s tímto kódem již existuje')]
class Permission
{
    #[ORM\Id]
    #[ORM\Column(name: 'id_prava', type: Types::BIGINT)]
    private ?int $id = null;

    /**
     * The name of its `Gamecon\Pravo` constant, so that a migration can name a right and a test
     * can tell when a constant and its row drift apart.
     */
    #[ORM\Column(name: 'kod_prava', length: 64, nullable: false)]
    #[Assert\NotBlank(message: 'Kód práva musí být vyplněn')]
    #[Assert\Length(max: 64)]
    #[Assert\Regex(pattern: '/^[A-Z][A-Z0-9_]*\z/', message: 'Kód práva smí obsahovat jen velká písmena bez diakritiky, číslice a podtržítka a začíná písmenem')]
    private string $code;

    #[ORM\Column(name: 'jmeno_prava', length: 255, nullable: false)]
    private string $jmenoPrava;

    #[ORM\Column(name: 'popis_prava', type: Types::TEXT, nullable: false)]
    private string $popisPrava;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getJmenoPrava(): string
    {
        return $this->jmenoPrava;
    }

    public function setJmenoPrava(string $jmenoPrava): static
    {
        $this->jmenoPrava = $jmenoPrava;

        return $this;
    }

    public function getPopisPrava(): string
    {
        return $this->popisPrava;
    }

    public function setPopisPrava(string $popisPrava): static
    {
        $this->popisPrava = $popisPrava;

        return $this;
    }
}
