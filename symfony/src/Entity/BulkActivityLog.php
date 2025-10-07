<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\BulkActivityLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**     * @var string
 * Bulk activity log (history of bulk operations on activities)
 */
#[ORM\Entity(repositoryClass: BulkActivityLogRepository::class)]
#[ORM\Table(name: 'hromadne_akce_log')]
#[ORM\Index(columns: ['akce'], name: 'IDX_akce')]
class BulkActivityLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_logu', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'skupina', type: Types::STRING, length: 128, nullable: true)]
    private ?string $skupina = null;

    #[ORM\Column(name: 'akce', type: Types::STRING, length: 255, nullable: true)]
    private ?string $akce = null;

    #[ORM\Column(name: 'vysledek', type: Types::STRING, length: 255, nullable: true)]
    private ?string $vysledek = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'provedl', referencedColumnName: 'id_uzivatele', nullable: true, onDelete: 'SET NULL', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private ?User $madeBy = null;

    #[ORM\Column(name: 'kdy', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $kdy;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getMadeBy(): ?User
    {
        return $this->madeBy;
    }

    public function setMadeBy(?int $madeBy): self
    {
        $this->madeBy = $madeBy;

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
