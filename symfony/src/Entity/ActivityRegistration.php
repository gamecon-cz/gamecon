<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityRegistrationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activity registration (current state of user's registration to activity)
 */
#[ORM\Entity(repositoryClass: ActivityRegistrationRepository::class)]
#[ORM\Table(name: 'akce_prihlaseni')]
#[ORM\Index(name: 'id_uzivatele', columns: ['id_uzivatele'])]
#[ORM\Index(name: 'id_stavu_prihlaseni', columns: ['id_stavu_prihlaseni'])]
class ActivityRegistration
{
    #[ORM\Id]
    #[ORM\Column(name: 'id_akce', type: Types::INTEGER)]
    private int $idAkce;

    #[ORM\Id]
    #[ORM\Column(name: 'id_uzivatele', type: Types::INTEGER)]
    private int $idUzivatele;

    #[ORM\Column(name: 'id_stavu_prihlaseni', type: Types::SMALLINT, nullable: false)]
    private int $idStavuPrihlaseni;

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

    public function getIdStavuPrihlaseni(): int
    {
        return $this->idStavuPrihlaseni;
    }

    public function setIdStavuPrihlaseni(int $idStavuPrihlaseni): self
    {
        $this->idStavuPrihlaseni = $idStavuPrihlaseni;

        return $this;
    }
}
