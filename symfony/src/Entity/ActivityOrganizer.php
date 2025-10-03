<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityOrganizerRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activity organizer mapping (many-to-many between activities and users)
 */
#[ORM\Entity(repositoryClass: ActivityOrganizerRepository::class)]
#[ORM\Table(name: 'akce_organizatori')]
#[ORM\Index(columns: ['id_uzivatele'], name: 'id_uzivatele')]
class ActivityOrganizer
{
    #[ORM\Id]
    #[ORM\Column(name: 'id_akce', type: Types::INTEGER)]
    private int $idAkce;

    #[ORM\Id]
    #[ORM\Column(name: 'id_uzivatele', type: Types::INTEGER, options: [
        'comment' => 'organizátor',
    ])]
    private int $idUzivatele;

    public function getIdAkce(): int
    {
        return $this->idAkce;
    }

    public function setIdAkce(int $idAkce): self
    {
        $this->idAkce = $idAkce;

        return $this;
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
}
