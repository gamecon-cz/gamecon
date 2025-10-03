<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserMergeLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * User merge log (history of merged user accounts)
 */
#[ORM\Entity(repositoryClass: UserMergeLogRepository::class)]
#[ORM\Table(name: 'uzivatele_slucovani_log')]
#[ORM\Index(columns: ['id_smazaneho_uzivatele'], name: 'idx_smazany_uzivatel')]
#[ORM\Index(columns: ['id_noveho_uzivatele'], name: 'idx_novy_uzivatel')]
#[ORM\Index(columns: ['kdy'], name: 'idx_kdy')]
#[ORM\UniqueConstraint(name: 'PRIMARY', columns: ['id'])]
class UserMergeLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'id_smazaneho_uzivatele', type: Types::INTEGER, nullable: false)]
    private int $idSmazanehoUzivatele;

    #[ORM\Column(name: 'id_noveho_uzivatele', type: Types::INTEGER, nullable: false)]
    private int $idNovehoUzivatele;

    #[ORM\Column(name: 'zustatek_smazaneho_puvodne', type: Types::INTEGER, nullable: false)]
    private int $zustatekSmazanehoPuvodne;

    #[ORM\Column(name: 'zustatek_noveho_puvodne', type: Types::INTEGER, nullable: false)]
    private int $zustatekNovehoPuvodne;

    #[ORM\Column(name: 'email_smazaneho', type: Types::STRING, length: 255, nullable: false)]
    private string $emailSmazaneho;

    #[ORM\Column(name: 'email_noveho_puvodne', type: Types::STRING, length: 255, nullable: false)]
    private string $emailNovehoPuvodne;

    #[ORM\Column(name: 'zustatek_noveho_aktualne', type: Types::INTEGER, nullable: false)]
    private int $zustatekNovehoAktualne;

    #[ORM\Column(name: 'email_noveho_aktualne', type: Types::STRING, length: 255, nullable: false)]
    private string $emailNovehoAktualne;

    #[ORM\Column(name: 'kdy', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $kdy;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIdSmazanehoUzivatele(): int
    {
        return $this->idSmazanehoUzivatele;
    }

    public function setIdSmazanehoUzivatele(int $idSmazanehoUzivatele): self
    {
        $this->idSmazanehoUzivatele = $idSmazanehoUzivatele;

        return $this;
    }

    public function getIdNovehoUzivatele(): int
    {
        return $this->idNovehoUzivatele;
    }

    public function setIdNovehoUzivatele(int $idNovehoUzivatele): self
    {
        $this->idNovehoUzivatele = $idNovehoUzivatele;

        return $this;
    }

    public function getZustatekSmazanehoPuvodne(): int
    {
        return $this->zustatekSmazanehoPuvodne;
    }

    public function setZustatekSmazanehoPuvodne(int $zustatekSmazanehoPuvodne): self
    {
        $this->zustatekSmazanehoPuvodne = $zustatekSmazanehoPuvodne;

        return $this;
    }

    public function getZustatekNovehoPuvodne(): int
    {
        return $this->zustatekNovehoPuvodne;
    }

    public function setZustatekNovehoPuvodne(int $zustatekNovehoPuvodne): self
    {
        $this->zustatekNovehoPuvodne = $zustatekNovehoPuvodne;

        return $this;
    }

    public function getEmailSmazaneho(): string
    {
        return $this->emailSmazaneho;
    }

    public function setEmailSmazaneho(string $emailSmazaneho): self
    {
        $this->emailSmazaneho = $emailSmazaneho;

        return $this;
    }

    public function getEmailNovehoPuvodne(): string
    {
        return $this->emailNovehoPuvodne;
    }

    public function setEmailNovehoPuvodne(string $emailNovehoPuvodne): self
    {
        $this->emailNovehoPuvodne = $emailNovehoPuvodne;

        return $this;
    }

    public function getZustatekNovehoAktualne(): int
    {
        return $this->zustatekNovehoAktualne;
    }

    public function setZustatekNovehoAktualne(int $zustatekNovehoAktualne): self
    {
        $this->zustatekNovehoAktualne = $zustatekNovehoAktualne;

        return $this;
    }

    public function getEmailNovehoAktualne(): string
    {
        return $this->emailNovehoAktualne;
    }

    public function setEmailNovehoAktualne(string $emailNovehoAktualne): self
    {
        $this->emailNovehoAktualne = $emailNovehoAktualne;

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
