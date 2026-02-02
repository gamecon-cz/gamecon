<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Order - grouping of OrderItems (optional enhancement)
 *
 * Currently OrderItems exist independently in shop_nakupy.
 * This entity provides optional grouping for:
 * - Cart management (pending orders)
 * - Order status tracking
 * - Bulk operations
 *
 * Note: Legacy shop_nakupy doesn't have order grouping,
 * so this is a new enhancement. Can be added later if needed.
 */
#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'shop_order')]
class Order
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'customer_id', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Zákazník musí být vyplněn')]
    private ?User $customer = null;

    #[ORM\Column(type: Types::SMALLINT, nullable: false)]
    #[Assert\Positive(message: 'Rok musí být kladné číslo')]
    private int $year;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: false, options: [
        'default' => 'pending',
    ])]
    #[Assert\Choice(
        choices: [self::STATUS_PENDING, self::STATUS_COMPLETED, self::STATUS_CANCELLED],
        message: 'Neplatný stav objednávky'
    )]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DECIMAL, precision: 8, scale: 2, nullable: false, options: [
        'default' => '0.00',
    ])]
    #[Assert\PositiveOrZero(message: 'Celková cena musí být kladné číslo nebo nula')]
    private string $totalPrice = '0.00';

    /**
     * @var Collection<int, OrderItem>
     */
    #[ORM\OneToMany(targetEntity: OrderItem::class, mappedBy: 'order', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $items;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    // ==================== Getters and Setters ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCustomer(): ?User
    {
        return $this->customer;
    }

    public function setCustomer(?User $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getYear(): int
    {
        return $this->year;
    }

    public function setYear(int $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getTotalPrice(): string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): self
    {
        $this->totalPrice = $totalPrice;

        return $this;
    }

    /**
     * @return Collection<int, OrderItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): self
    {
        if (! $this->items->contains($item)) {
            $this->items->add($item);
        }

        return $this;
    }

    public function removeItem(OrderItem $item): self
    {
        $this->items->removeElement($item);

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeInterface $completedAt): self
    {
        $this->completedAt = $completedAt;

        return $this;
    }

    // ==================== Helper Methods ====================

    /**
     * Recalculate total price from items
     */
    public function recalculateTotal(): self
    {
        $total = '0.00';
        foreach ($this->items as $item) {
            $total = bcadd($total, $item->getPurchasePrice(), 2);
        }

        $this->totalPrice = $total;

        return $this;
    }

    /**
     * Mark order as completed
     */
    public function complete(): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTime();

        return $this;
    }

    /**
     * Mark order as cancelled
     */
    public function cancel(): self
    {
        $this->status = self::STATUS_CANCELLED;

        return $this;
    }

    /**
     * Check if order is pending
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Check if order is completed
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Check if order is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Get item count
     */
    public function getItemCount(): int
    {
        return $this->items->count();
    }

    /**
     * Check if order is empty
     */
    public function isEmpty(): bool
    {
        return $this->items->isEmpty();
    }

    /**
     * Get total savings (sum of all discounts)
     */
    public function getTotalSavings(): string
    {
        $total = '0.00';
        foreach ($this->items as $item) {
            if ($item->getDiscountAmount() !== null) {
                $total = bcadd($total, $item->getDiscountAmount(), 2);
            }
        }

        return $total;
    }
}
