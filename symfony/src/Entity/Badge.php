<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BadgeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * User badge/profile information (medailonek)
 *
 * Legacy @see \Gamecon\Uzivatel\Medailonek
 */
#[ORM\Entity(repositoryClass: BadgeRepository::class)]
#[ORM\Table(name: 'medailonky')]
class Badge
{
    /**
     * id_uzivatele is the primary key (references user)
     */
    #[ORM\Id]
    #[ORM\Column(name: 'id_uzivatele', type: Types::INTEGER)]
    private ?int $idUzivatele = null;

    #[ORM\Column(name: 'o_sobe', type: Types::TEXT, nullable: false, options: [
        'comment' => 'markdown',
    ])]
    private string $oSobe;

    #[ORM\Column(name: 'drd', type: Types::TEXT, nullable: false, options: [
        'comment' => 'markdown -- profil pro DrD',
    ])]
    private string $drd;

    public function getIdUzivatele(): ?int
    {
        return $this->idUzivatele;
    }

    public function setIdUzivatele(int $idUzivatele): self
    {
        $this->idUzivatele = $idUzivatele;

        return $this;
    }

    public function getOSobe(): string
    {
        return $this->oSobe;
    }

    public function setOSobe(string $oSobe): self
    {
        $this->oSobe = $oSobe;

        return $this;
    }

    public function getDrd(): string
    {
        return $this->drd;
    }

    public function setDrd(string $drd): self
    {
        $this->drd = $drd;

        return $this;
    }
}
