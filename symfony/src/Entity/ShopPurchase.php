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
#[ORM\UniqueConstraint(name: 'id_nakupu', columns: ['id_nakupu'])]
#[ORM\Index(name: 'rok_id_uzivatele', columns: ['rok', 'id_uzivatele'])]
#[ORM\Index(name: 'id_predmetu', columns: ['id_predmetu'])]
#[ORM\Index(name: 'id_uzivatele', columns: ['id_uzivatele'])]
#[ORM\Index(name: 'id_objednatele', columns: ['id_objednatele'])]
class ShopPurchase
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_nakupu', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $idNakupu = null;

    #[ORM\Column(name: 'id_uzivatele', type: Types::INTEGER, nullable: false)]
    private int $idUzivatele;

    #[ORM\Column(name: 'id_objednatele', type: Types::INTEGER, nullable: true)]
    private ?int $idObjednatele = null;

    #[ORM\Column(name: 'id_predmetu', type: Types::INTEGER, nullable: false)]
    private int $idPredmetu;

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

    public function getIdUzivatele(): int
    {
        return $this->idUzivatele;
    }

    public function setIdUzivatele(int $idUzivatele): self
    {
        $this->idUzivatele = $idUzivatele;

        return $this;
    }

    public function getIdObjednatele(): ?int
    {
        return $this->idObjednatele;
    }

    public function setIdObjednatele(?int $idObjednatele): self
    {
        $this->idObjednatele = $idObjednatele;

        return $this;
    }

    public function getIdPredmetu(): int
    {
        return $this->idPredmetu;
    }

    public function setIdPredmetu(int $idPredmetu): self
    {
        $this->idPredmetu = $idPredmetu;

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
