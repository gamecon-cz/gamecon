<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\GoogleApiUserTokenRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Google API user tokens for OAuth integration
 */
#[ORM\Entity(repositoryClass: GoogleApiUserTokenRepository::class)]
#[ORM\Table(name: 'google_api_user_tokens')]
#[ORM\UniqueConstraint(name: 'UNIQ_user_id_google_client_id', columns: ['user_id', 'google_client_id'])]
class GoogleApiUserToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private User $user;

    #[ORM\Column(name: 'google_client_id', type: Types::STRING, length: 128, nullable: false)]
    private string $googleClientId;

    #[ORM\Column(name: 'tokens', type: Types::TEXT, nullable: false)]
    private string $tokens;

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

    public function getGoogleClientId(): string
    {
        return $this->googleClientId;
    }

    public function setGoogleClientId(string $googleClientId): self
    {
        $this->googleClientId = $googleClientId;

        return $this;
    }

    public function getTokens(): string
    {
        return $this->tokens;
    }

    public function setTokens(string $tokens): self
    {
        $this->tokens = $tokens;

        return $this;
    }
}
