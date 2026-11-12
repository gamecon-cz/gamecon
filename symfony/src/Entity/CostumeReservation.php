<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Rezervace kostýmu na aktivitu. Platí po dobu aktivity, čas se nikam nekopíruje.
 *
 * Databáze má cizí klíč (id_akce, id_uzivatele) na akce_prihlaseni s ON DELETE CASCADE, takže odhlášení
 * rezervaci smaže. Doctrine asociaci na neprimární sloupce neumí, proto jsou tu oba sloupce holé
 * a klíč je v symfony/migrations/ignored.sql. Vlastní cizí klíč na uživatele schválně chybí, viz migrace
 * migrace/2026-11-12-151618_pujcovna-kostymu.php.
 */
#[ORM\Entity]
#[ORM\Table(name: 'kostym_rezervace')]
#[ORM\UniqueConstraint(name: 'UNIQ_id_akce_id_uzivatele', columns: ['id_akce', 'id_uzivatele'])]
#[ORM\Index(name: 'IDX_id_kostymu', columns: ['id_kostymu'])]
#[ORM\Index(name: 'IDX_rezervoval', columns: ['rezervoval'])]
class CostumeReservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Costume::class)]
    #[ORM\JoinColumn(name: 'id_kostymu', referencedColumnName: 'id', nullable: false, options: [
        'onUpdate' => 'CASCADE',
    ])]
    private Costume $costume;

    #[ORM\Column(name: 'id_akce', type: Types::BIGINT, nullable: false, options: [
        'unsigned' => true,
    ])]
    private int $idAkce;

    #[ORM\Column(name: 'id_uzivatele', type: Types::BIGINT, nullable: false, options: [
        'unsigned' => true,
    ])]
    private int $idUzivatele;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'rezervoval', referencedColumnName: 'id_uzivatele', nullable: true, onDelete: 'SET NULL', options: [
        'onUpdate' => 'CASCADE',
    ])]
    private ?User $rezervoval = null;

    #[ORM\Column(name: 'rezervovano', type: Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $rezervovano;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCostume(): Costume
    {
        return $this->costume;
    }

    public function setCostume(Costume $costume): self
    {
        $this->costume = $costume;

        return $this;
    }

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

    public function getRezervoval(): ?User
    {
        return $this->rezervoval;
    }

    public function setRezervoval(?User $rezervoval): self
    {
        $this->rezervoval = $rezervoval;

        return $this;
    }

    public function getRezervovano(): \DateTimeInterface
    {
        return $this->rezervovano;
    }

    public function setRezervovano(\DateTimeInterface $rezervovano): self
    {
        $this->rezervovano = $rezervovano;

        return $this;
    }
}
