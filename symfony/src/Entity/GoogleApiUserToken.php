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
#[ORM\UniqueConstraint(name: 'id', columns: ['id'])]
class GoogleApiUserToken
{
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id', type: Types::INTEGER, options: [
        'unsigned' => true,
    ])]
    private int $id; // @phpstan-ignore-line property.onlyRead

    #[ORM\Id]
    #[ORM\Column(name: 'user_id', type: Types::INTEGER, nullable: false)]
    private int $userId;

    #[ORM\Id]
    #[ORM\Column(name: 'google_client_id', type: Types::STRING, length: 128, nullable: false)]
    private string $googleClientId;

    #[ORM\Column(name: 'tokens', type: Types::TEXT, nullable: false)]
    private string $tokens;

    public function getId(): int
    {
        return $this->id;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
    {
        $this->userId = $userId;

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
