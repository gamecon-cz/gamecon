<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\SystemSettingRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * System setting (key-value configuration)
 */
#[ORM\Entity(repositoryClass: SystemSettingRepository::class)]
#[ORM\Table(name: 'systemove_nastaveni')]
#[ORM\UniqueConstraint(name: 'UNIQ_klic_rocnik_nastaveni', columns: ['klic', 'rocnik_nastaveni'])]
#[ORM\UniqueConstraint(name: 'UNIQ_nazev_rocnik_nastaveni', columns: ['nazev', 'rocnik_nastaveni'])]
#[ORM\Index(columns: ['skupina'], name: 'IDX_skupina')]
class SystemSetting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_nastaveni', type: Types::BIGINT, options: [
        'unsigned' => true,
    ])]
    private ?int $id = null;

    #[ORM\Column(name: 'klic', type: Types::STRING, length: 128, nullable: false)]
    private string $klic;

    #[ORM\Column(name: 'hodnota', type: Types::STRING, length: 255, nullable: false, options: [
        'default' => '',
    ])]
    private string $hodnota = '';

    #[ORM\Column(name: 'vlastni', type: Types::BOOLEAN, nullable: true, options: [
        'default' => 0,
    ])]
    private ?bool $vlastni = false;

    #[ORM\Column(name: 'datovy_typ', type: Types::STRING, length: 24, nullable: false, options: [
        'default' => 'string',
    ])]
    private string $datovyTyp = 'string';

    #[ORM\Column(name: 'nazev', type: Types::STRING, length: 255, nullable: false)]
    private string $nazev;

    #[ORM\Column(name: 'popis', type: Types::STRING, length: 1028, nullable: false, options: [
        'default' => '',
    ])]
    private string $popis = '';

    #[ORM\Column(name: 'zmena_kdy', type: Types::DATETIME_MUTABLE, nullable: false, options: [
        'default' => 'CURRENT_TIMESTAMP',
    ])]
    private \DateTime $zmenaKdy;

    #[ORM\Column(name: 'skupina', type: Types::STRING, length: 128, nullable: true)]
    private ?string $skupina = null;

    #[ORM\Column(name: 'poradi', type: Types::INTEGER, nullable: true, options: [
        'unsigned' => true,
    ])]
    private ?int $poradi = null;

    #[ORM\Column(name: 'pouze_pro_cteni', type: Types::BOOLEAN, nullable: true, options: [
        'default' => 0,
    ])]
    private ?bool $pouzeProCteni = false;

    #[ORM\Column(name: 'rocnik_nastaveni', type: Types::INTEGER, nullable: false, options: [
        'default' => -1,
    ])]
    private int $rocnikNastaveni = -1;

    public function getId(): int
    {
        return $this->id;
    }

    public function getKlic(): string
    {
        return $this->klic;
    }

    public function setKlic(string $klic): self
    {
        $this->klic = $klic;

        return $this;
    }

    public function getHodnota(): string
    {
        return $this->hodnota;
    }

    public function setHodnota(string $hodnota): self
    {
        $this->hodnota = $hodnota;

        return $this;
    }

    public function getVlastni(): ?bool
    {
        return $this->vlastni;
    }

    public function setVlastni(?bool $vlastni): self
    {
        $this->vlastni = $vlastni;

        return $this;
    }

    public function getDatovyTyp(): string
    {
        return $this->datovyTyp;
    }

    public function setDatovyTyp(string $datovyTyp): self
    {
        $this->datovyTyp = $datovyTyp;

        return $this;
    }

    public function getNazev(): string
    {
        return $this->nazev;
    }

    public function setNazev(string $nazev): self
    {
        $this->nazev = $nazev;

        return $this;
    }

    public function getPopis(): string
    {
        return $this->popis;
    }

    public function setPopis(string $popis): self
    {
        $this->popis = $popis;

        return $this;
    }

    public function getZmenaKdy(): \DateTime
    {
        return $this->zmenaKdy;
    }

    public function setZmenaKdy(\DateTime $zmenaKdy): self
    {
        $this->zmenaKdy = $zmenaKdy;

        return $this;
    }

    public function getSkupina(): ?string
    {
        return $this->skupina;
    }

    public function setSkupina(?string $skupina): self
    {
        $this->skupina = $skupina;

        return $this;
    }

    public function getPoradi(): ?int
    {
        return $this->poradi;
    }

    public function setPoradi(?int $poradi): self
    {
        $this->poradi = $poradi;

        return $this;
    }

    public function getPouzeProCteni(): ?bool
    {
        return $this->pouzeProCteni;
    }

    public function setPouzeProCteni(?bool $pouzeProCteni): self
    {
        $this->pouzeProCteni = $pouzeProCteni;

        return $this;
    }

    public function getRocnikNastaveni(): int
    {
        return $this->rocnikNastaveni;
    }

    public function setRocnikNastaveni(int $rocnikNastaveni): self
    {
        $this->rocnikNastaveni = $rocnikNastaveni;

        return $this;
    }
}
