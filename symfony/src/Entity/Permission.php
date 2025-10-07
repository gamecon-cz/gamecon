<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PermissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Legacy @see \Gamecon\Prava
 */
#[ORM\Entity(repositoryClass: PermissionRepository::class)]
#[ORM\Table(name: 'r_prava_soupis')]
class Permission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_prava', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'jmeno_prava', length: 255, nullable: false)]
    private string $jmenoPrava;

    #[ORM\Column(name: 'popis_prava', type: Types::TEXT, nullable: false)]
    private string $popisPrava;

    public function getId(): ?int
    {
        return $this->id;
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
