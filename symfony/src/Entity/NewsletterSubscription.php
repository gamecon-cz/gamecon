<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NewsletterSubscriptionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Přihlášení k odběru newsletteru.
 */
#[ORM\Entity(repositoryClass: NewsletterSubscriptionRepository::class)]
#[ORM\Table(name: 'newsletter_prihlaseni')]
#[ORM\UniqueConstraint(name: 'email', columns: ['email'])]
class NewsletterSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_newsletter_prihlaseni', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'email', length: 512, nullable: false)]
    private string $email;

    #[ORM\Column(name: 'kdy', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTimeInterface $kdy;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getKdy(): \DateTimeInterface
    {
        return $this->kdy;
    }

    public function setKdy(\DateTimeInterface $kdy): static
    {
        $this->kdy = $kdy;

        return $this;
    }
}
