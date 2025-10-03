<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityTagRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activity tag mapping (many-to-many between activities and unified tags)
 */
#[ORM\Entity(repositoryClass: ActivityTagRepository::class)]
#[ORM\Table(name: 'akce_sjednocene_tagy')]
#[ORM\Index(name: 'FK_akce_sjednocene_tagy_to_sjednocene_tagy', columns: ['id_tagu'])]
class ActivityTag
{
    #[ORM\Id]
    #[ORM\Column(name: 'id_akce', type: Types::INTEGER)]
    private int $idAkce;

    #[ORM\Id]
    #[ORM\Column(name: 'id_tagu', type: Types::INTEGER, options: [
        'unsigned' => true,
    ])]
    private int $idTagu;

    public function getIdAkce(): int
    {
        return $this->idAkce;
    }

    public function setIdAkce(int $idAkce): self
    {
        $this->idAkce = $idAkce;

        return $this;
    }

    public function getIdTagu(): int
    {
        return $this->idTagu;
    }

    public function setIdTagu(int $idTagu): self
    {
        $this->idTagu = $idTagu;

        return $this;
    }
}
