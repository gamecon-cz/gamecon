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
#[ORM\Index(columns: ['id_predmetu'], name: 'FK_zrusene_objednavky_to_shop_predmety')]
#[ORM\Index(columns: ['id_uzivatele'], name: 'FK_zrusene_objednavky_to_uzivatele_hodnoty')]
#[ORM\Index(columns: ['datum_zruseni'], name: 'datum_zruseni')]
#[ORM\Index(columns: ['zdroj_zruseni'], name: 'zdroj_zruseni')]
class ShopPurchaseCancelled
{
    #[ORM\Id]
    #[ORM\Column(name: 'id_nakupu', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private int $idNakupu;

    #[ORM\Column(name: 'id_uzivatele', type: Types::INTEGER, nullable: false)]
    private int $idUzivatele;

    #[ORM\Column(name: 'id_predmetu', type: Types::INTEGER, nullable: false)]
    private int $idPredmetu;

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

    public function getIdNakupu(): int
    {
        return $this->idNakupu;
    }

    public function setIdNakupu(int $idNakupu): self
    {
        $this->idNakupu = $idNakupu;

        return $this;
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

    public function getIdPredmetu(): int
    {
        return $this->idPredmetu;
    }

    public function setIdPredmetu(int $idPredmetu): self
    {
        $this->idPredmetu = $idPredmetu;

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
