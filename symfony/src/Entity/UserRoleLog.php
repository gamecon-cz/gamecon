<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserRoleLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * User role log (history of role assignments)
 */
#[ORM\Entity(repositoryClass: UserRoleLogRepository::class)]
#[ORM\Table(name: 'uzivatele_role_log')]
#[ORM\UniqueConstraint(name: 'id', columns: ['id'])]
#[ORM\Index(columns: ['id_uzivatele'], name: 'id_uzivatele')]
#[ORM\Index(columns: ['id_role'], name: 'id_zidle')]
#[ORM\Index(columns: ['id_zmenil'], name: 'id_zmenil')]
class UserRoleLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'id_uzivatele', type: Types::INTEGER, nullable: false)]
    private int $idUzivatele;

    #[ORM\Column(name: 'id_role', type: Types::INTEGER, nullable: false)]
    private int $idRole;

    #[ORM\Column(name: 'id_zmenil', type: Types::INTEGER, nullable: true)]
    private ?int $idZmenil = null;

    #[ORM\Column(name: 'zmena', type: Types::STRING, length: 128, nullable: false)]
    private string $zmena;

    #[ORM\Column(name: 'kdy', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $kdy;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdUzivatele(): int
    {
        return $this->idUzivatele;
    }

    public function setIdUzivatele(int $idUzivatele): self
    {
        $this->idUzivatele = $idUzivatele;

        return $this;
    }

    public function getIdRole(): int
    {
        return $this->idRole;
    }

    public function setIdRole(int $idRole): self
    {
        $this->idRole = $idRole;

        return $this;
    }

    public function getIdZmenil(): ?int
    {
        return $this->idZmenil;
    }

    public function setIdZmenil(?int $idZmenil): self
    {
        $this->idZmenil = $idZmenil;

        return $this;
    }

    public function getZmena(): string
    {
        return $this->zmena;
    }

    public function setZmena(string $zmena): self
    {
        $this->zmena = $zmena;

        return $this;
    }

    public function getKdy(): \DateTime
    {
        return $this->kdy;
    }

    public function setKdy(\DateTime $kdy): self
    {
        $this->kdy = $kdy;

        return $this;
    }
}
