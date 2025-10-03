<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NewsletterSubscriptionLogRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Newsletter subscription log (history of newsletter subscription changes)
 */
#[ORM\Entity(repositoryClass: NewsletterSubscriptionLogRepository::class)]
#[ORM\Table(name: 'newsletter_prihlaseni_log')]
#[ORM\Index(columns: ['email'], name: 'email')]
#[ORM\UniqueConstraint(name: 'PRIMARY', columns: ['id_newsletter_prihlaseni_log'])]
class NewsletterSubscriptionLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_newsletter_prihlaseni_log', type: Types::INTEGER)]
    private ?int $idNewsletterPrihlaseniLog = null;

    #[ORM\Column(name: 'email', type: Types::STRING, length: 512, nullable: false)]
    private string $email;

    #[ORM\Column(name: 'kdy', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $kdy;

    #[ORM\Column(name: 'stav', type: Types::STRING, length: 127, nullable: false)]
    private string $stav;

    public function getIdNewsletterPrihlaseniLog(): ?int
    {
        return $this->idNewsletterPrihlaseniLog;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

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

    public function getStav(): string
    {
        return $this->stav;
    }

    public function setStav(string $stav): self
    {
        $this->stav = $stav;

        return $this;
    }
}
