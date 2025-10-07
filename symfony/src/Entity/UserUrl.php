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
class UserUrl
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_url_uzivatele', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(name: 'url', type: Types::STRING, length: 255, unique: true, nullable: false)]
    private string $url;

    public function getId(): int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

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
