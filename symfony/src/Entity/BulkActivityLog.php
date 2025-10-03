<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BulkActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Bulk activity log (history of bulk operations on activities)
 */
#[ORM\Entity(repositoryClass: BulkActivityLogRepository::class)]
#[ORM\Table(name: 'hromadne_akce_log')]
#[ORM\UniqueConstraint(name: 'id_logu', columns: ['id_logu'])]
#[ORM\Index(columns: ['akce'], name: 'akce')]
#[ORM\Index(columns: ['provedl'], name: 'FK_hromadne_akce_log_to_uzivatele_hodnoty')]
class BulkActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_logu', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $idLogu = null;

    #[ORM\Column(name: 'skupina', type: Types::STRING, length: 128, nullable: true)]
    private ?string $skupina = null;

    #[ORM\Column(name: 'akce', type: Types::STRING, length: 255, nullable: true)]
    private ?string $akce = null;

    #[ORM\Column(name: 'vysledek', type: Types::STRING, length: 255, nullable: true)]
    private ?string $vysledek = null;

    #[ORM\Column(name: 'provedl', type: Types::INTEGER, nullable: true)]
    private ?int $provedl = null;

    #[ORM\Column(name: 'kdy', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $kdy;

    public function getIdLogu(): ?int
    {
        return $this->idLogu;
    }

    public function getSkupina(): ?string
    {
        return $this->skupina;
    }

    public function setSkupina(?string $skupina): self
    {
        $this->skupina = $skupina;

        return $this;
    }

    public function getAkce(): ?string
    {
        return $this->akce;
    }

    public function setAkce(?string $akce): self
    {
        $this->akce = $akce;

        return $this;
    }

    public function getVysledek(): ?string
    {
        return $this->vysledek;
    }

    public function setVysledek(?string $vysledek): self
    {
        $this->vysledek = $vysledek;

        return $this;
    }

    public function getProvedl(): ?int
    {
        return $this->provedl;
    }

    public function setProvedl(?int $provedl): self
    {
        $this->provedl = $provedl;

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
