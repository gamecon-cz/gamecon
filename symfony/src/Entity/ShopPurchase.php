<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShopPurchaseRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Shop purchase (record of item purchased by user)
 */
#[ORM\Entity(repositoryClass: ShopPurchaseRepository::class)]
#[ORM\Table(name: 'shop_nakupy')]
#[ORM\Index(columns: ['rok', 'id_uzivatele'], name: 'IDX_rok_id_uzivatele')]
class ShopPurchase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_nakupu', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $idNakupu = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private User $customer;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_objednatele', referencedColumnName: 'id_uzivatele', nullable: true, onDelete: 'SET NULL', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private ?User $orderer = null;

    #[ORM\ManyToOne(targetEntity: ShopItem::class)]
    #[ORM\JoinColumn(name: 'id_predmetu', referencedColumnName: 'id_predmetu', nullable: false, onDelete: 'RESTRICT', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private ShopItem $shopItem;

    #[ORM\Column(name: 'rok', type: Types::SMALLINT, nullable: false)]
    private int $rok;

    #[ORM\Column(name: 'cena_nakupni', type: Types::DECIMAL, precision: 6, scale: 2, nullable: false, options: [
        'comment' => 'aktuální cena v okamžiku nákupu (bez slev)',
    ])]
    private string $cenaNakupni;

    #[ORM\Column(name: 'datum', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $datum;

    public function getIdNakupu(): ?int
    {
        return $this->idNakupu;
    }

    public function getCustomer(): User
    {
        return $this->customer;
    }

    public function setCustomer(User $customer): self
    {
        $this->customer = $customer;

        return $this;
    }

    public function getOrderer(): ?User
    {
        return $this->orderer;
    }

    public function setOrderer(?User $orderer): self
    {
        $this->orderer = $orderer;

        return $this;
    }

    public function getShopItem(): ShopItem
    {
        return $this->shopItem;
    }

    public function setShopItem(ShopItem $shopItem): self
    {
        $this->shopItem = $shopItem;

        return $this;
    }

    public function getRok(): int
    {
        return $this->rok;
    }

    public function setRok(int $rok): self
    {
        $this->rok = $rok;

        return $this;
    }

    public function getCenaNakupni(): string
    {
        return $this->cenaNakupni;
    }

    public function setCenaNakupni(string $cenaNakupni): self
    {
        $this->cenaNakupni = $cenaNakupni;

        return $this;
    }

    public function getDatum(): \DateTime
    {
        return $this->datum;
    }

    public function setDatum(\DateTime $datum): self
    {
        $this->datum = $datum;

        return $this;
    }
}
