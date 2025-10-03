<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityInstanceRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activity instance linking to main activity
 */
#[ORM\Entity(repositoryClass: ActivityInstanceRepository::class)]
#[ORM\Table(name: 'akce_instance')]
#[ORM\Index(name: 'FK_akce_instance_to_akce_seznam', columns: ['id_hlavni_akce'])]
#[ORM\UniqueConstraint(name: 'PRIMARY', columns: ['id_instance'])]
class ActivityInstance
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_instance', type: Types::INTEGER)]
    private ?int $idInstance = null;

    #[ORM\Column(name: 'id_hlavni_akce', type: Types::INTEGER, nullable: false)]
    private int $idHlavniAkce;

    public function getIdInstance(): ?int
    {
        return $this->idInstance;
    }

    public function getIdHlavniAkce(): int
    {
        return $this->idHlavniAkce;
    }

    public function setIdHlavniAkce(int $idHlavniAkce): self
    {
        $this->idHlavniAkce = $idHlavniAkce;

        return $this;
    }
}
