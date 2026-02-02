<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CancelledOrderItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * CancelledOrderItem - archived cancelled order items for audit/history
 *
 * This entity stores cancelled order items from shop_nakupy that have been cancelled.
 * It's simpler than OrderItem - no product snapshots, no discount tracking, no orderer.
 *
 * Used by legacy code in model/Shop/Shop.php for archiving cancelled purchases.
 */
#[ORM\Entity(repositoryClass: CancelledOrderItemRepository::class)]
#[ORM\Table(name: 'shop_nakupy_zrusene')]
#[ORM\Index(name: 'IDX_id_uzivatele', columns: ['id_uzivatele'])]
#[ORM\Index(name: 'IDX_id_predmetu', columns: ['id_predmetu'])]
#[ORM\Index(name: 'IDX_datum_zruseni', columns: ['datum_zruseni'])]
#[ORM\Index(name: 'IDX_zdroj_zruseni', columns: ['zdroj_zruseni'])]
class CancelledOrderItem
{
    #[ORM\Id]
    #[ORM\Column(name: 'id_nakupu', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'cancelledOrderItems')]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE')]
    private ?User $customer = null;

    #[ORM\ManyToOne(targetEntity: Product::class, inversedBy: 'cancelledOrderItems')]
    #[ORM\JoinColumn(name: 'id_predmetu', referencedColumnName: 'id_predmetu', nullable: false)]
    private ?Product $product = null;

    #[ORM\Column(name: 'rocnik', type: Types::SMALLINT, nullable: false)]
    private int $year;

    #[ORM\Column(name: 'cena_nakupni', type: Types::DECIMAL, precision: 6, scale: 2, nullable: false)]
    private string $purchasePrice;

    #[ORM\Column(name: 'datum_nakupu', type: Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $purchasedAt;

    #[ORM\Column(name: 'datum_zruseni', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTimeInterface $cancelledAt;

    #[ORM\Column(name: 'zdroj_zruseni', type: Types::STRING, length: 255, nullable: true)]
    private ?string $cancellationReason = null;

    public function __construct()
    {
        $this->cancelledAt = new \DateTime();
    }

    // ==================== Getters and Setters ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): self
    {
        $this->id = $id;

        return $this;
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

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;

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

    public function getPurchasePrice(): string
    {
        return $this->purchasePrice;
    }

    public function setPurchasePrice(string $purchasePrice): self
    {
        $this->purchasePrice = $purchasePrice;

        return $this;
    }

    public function getPurchasedAt(): \DateTimeInterface
    {
        return $this->purchasedAt;
    }

    public function setPurchasedAt(\DateTimeInterface $purchasedAt): self
    {
        $this->purchasedAt = $purchasedAt;

        return $this;
    }

    public function getCancelledAt(): \DateTimeInterface
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(\DateTimeInterface $cancelledAt): self
    {
        $this->cancelledAt = $cancelledAt;

        return $this;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function setCancellationReason(?string $cancellationReason): self
    {
        $this->cancellationReason = $cancellationReason;

        return $this;
    }

    // ==================== Helper Methods ====================

    /**
     * Get display product name (fallback to "Smazaný produkt" if product is deleted)
     */
    public function getDisplayProductName(): string
    {
        if ($this->product instanceof Product) {
            return $this->product->getName();
        }

        return 'Smazaný produkt';
    }
}
