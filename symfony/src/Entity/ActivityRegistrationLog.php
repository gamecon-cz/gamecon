<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityRegistrationLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activity registration log (history of registration state changes)
 */
#[ORM\Entity(repositoryClass: ActivityRegistrationLogRepository::class)]
#[ORM\Table(name: 'akce_prihlaseni_log')]
#[ORM\Index(name: 'typ', columns: ['typ'])]
#[ORM\Index(name: 'id_zmenil', columns: ['id_zmenil'])]
#[ORM\Index(name: 'FK_akce_prihlaseni_log_to_akce_seznam', columns: ['id_akce'])]
#[ORM\Index(name: 'FK_akce_prihlaseni_log_to_uzivatele_hodnoty', columns: ['id_uzivatele'])]
#[ORM\Index(name: 'zdroj_zmeny', columns: ['zdroj_zmeny'])]
#[ORM\UniqueConstraint(name: 'PRIMARY', columns: ['id_log'])]
class ActivityRegistrationLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_log', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $idLog = null;

    #[ORM\Column(name: 'id_akce', type: Types::INTEGER, nullable: false)]
    private int $idAkce;

    #[ORM\Column(name: 'id_uzivatele', type: Types::INTEGER, nullable: false)]
    private int $idUzivatele;

    #[ORM\Column(name: 'kdy', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $kdy;

    #[ORM\Column(name: 'typ', type: Types::STRING, length: 64, nullable: true)]
    private ?string $typ = null;

    #[ORM\Column(name: 'id_zmenil', type: Types::INTEGER, nullable: true)]
    private ?int $idZmenil = null;

    #[ORM\Column(name: 'zdroj_zmeny', type: Types::STRING, length: 128, nullable: true)]
    private ?string $zdrojZmeny = null;

    #[ORM\Column(name: 'rocnik', type: Types::INTEGER, nullable: true, options: [
        'unsigned' => true,
    ])]
    private ?int $rocnik = null;

    public function getIdLog(): ?int
    {
        return $this->idLog;
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

    public function getKdy(): \DateTime
    {
        return $this->kdy;
    }

    public function setKdy(\DateTime $kdy): self
    {
        $this->kdy = $kdy;

        return $this;
    }

    public function getTyp(): ?string
    {
        return $this->typ;
    }

    public function setTyp(?string $typ): self
    {
        $this->typ = $typ;

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

    public function getZdrojZmeny(): ?string
    {
        return $this->zdrojZmeny;
    }

    public function setZdrojZmeny(?string $zdrojZmeny): self
    {
        $this->zdrojZmeny = $zdrojZmeny;

        return $this;
    }

    public function getRocnik(): ?int
    {
        return $this->rocnik;
    }

    public function setRocnik(?int $rocnik): self
    {
        $this->rocnik = $rocnik;

        return $this;
    }
}
