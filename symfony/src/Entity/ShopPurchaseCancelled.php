<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShopPurchaseCancelledRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Shop purchase cancelled (history of cancelled purchases)
 */
#[ORM\Entity(repositoryClass: ShopPurchaseCancelledRepository::class)]
#[ORM\Table(name: 'shop_nakupy_zrusene')]
#[ORM\Index(columns: ['datum_zruseni'], name: 'IDX_datum_zruseni')]
#[ORM\Index(columns: ['zdroj_zruseni'], name: 'IDX_zdroj_zruseni')]
class ShopPurchaseCancelled
{
    #[ORM\Id]
    #[ORM\Column(name: 'id_nakupu', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private int $deletedShopPurchaseId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'id_uzivatele', referencedColumnName: 'id_uzivatele', nullable: false, onDelete: 'CASCADE', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private User $customer;

    #[ORM\ManyToOne(targetEntity: ShopItem::class)]
    #[ORM\JoinColumn(name: 'id_predmetu', referencedColumnName: 'id_predmetu', nullable: false, onDelete: 'RESTRICT', options: [
        'ON UPDATE' => 'CASCADE',
    ])]
    private ShopItem $shopItem;

    #[ORM\Column(name: 'rocnik', type: Types::SMALLINT, nullable: false)]
    private int $rocnik;

    #[ORM\Column(name: 'cena_nakupni', type: Types::DECIMAL, precision: 6, scale: 2, nullable: false, options: [
        'comment' => 'aktuální cena v okamžiku nákupu (bez slev)',
    ])]
    private string $cenaNakupni;

    #[ORM\Column(name: 'datum_nakupu', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $datumNakupu;

    #[ORM\Column(name: 'datum_zruseni', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $datumZruseni;

    #[ORM\Column(name: 'zdroj_zruseni', type: Types::STRING, length: 255, nullable: true)]
    private ?string $zdrojZruseni = null;

    public function getDeletedShopPurchaseId(): int
    {
        return $this->deletedShopPurchaseId;
    }

    public function setDeletedShopPurchaseId(int $deletedShopPurchaseId): self
    {
        $this->deletedShopPurchaseId = $deletedShopPurchaseId;

        return $this;
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

    public function getShopItem(): ShopItem
    {
        return $this->shopItem;
    }

    public function setShopItem(ShopItem $shopItem): self
    {
        $this->shopItem = $shopItem;

        return $this;
    }

    public function getRocnik(): int
    {
        return $this->rocnik;
    }

    public function setRocnik(int $rocnik): self
    {
        $this->rocnik = $rocnik;

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

    public function getDatumNakupu(): \DateTime
    {
        return $this->datumNakupu;
    }

    public function setDatumNakupu(\DateTime $datumNakupu): self
    {
        $this->datumNakupu = $datumNakupu;

        return $this;
    }

    public function getDatumZruseni(): \DateTime
    {
        return $this->datumZruseni;
    }

    public function setDatumZruseni(\DateTime $datumZruseni): self
    {
        $this->datumZruseni = $datumZruseni;

        return $this;
    }

    public function getZdrojZruseni(): ?string
    {
        return $this->zdrojZruseni;
    }

    public function setZdrojZruseni(?string $zdrojZruseni): self
    {
        $this->zdrojZruseni = $zdrojZruseni;

        return $this;
    }
}
