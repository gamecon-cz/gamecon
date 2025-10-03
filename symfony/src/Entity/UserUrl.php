<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\UserUrlRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * User URL (unique URL slugs for users)
 */
#[ORM\Entity(repositoryClass: UserUrlRepository::class)]
#[ORM\Table(name: 'uzivatele_url')]
#[ORM\UniqueConstraint(name: 'id_url_uzivatele', columns: ['id_url_uzivatele'])]
#[ORM\Index(name: 'id_uzivatele', columns: ['id_uzivatele'])]
class UserUrl
{
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_url_uzivatele', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private int $idUrlUzivatele; // @phpstan-ignore-line property.onlyRead

    #[ORM\Column(name: 'id_uzivatele', type: Types::INTEGER, nullable: false)]
    private int $idUzivatele;

    #[ORM\Id]
    #[ORM\Column(name: 'url', type: Types::STRING, length: 255, nullable: false)]
    private string $url;

    public function getIdUrlUzivatele(): int
    {
        return $this->idUrlUzivatele;
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

    public function getUrl(): string
    {
        return $this->url;
    }

    public function setUrl(string $url): self
    {
        $this->url = $url;

        return $this;
    }
}
