<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\ActivityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Activity (main activity/event entity)
 */
#[ORM\Entity(repositoryClass: ActivityRepository::class)]
#[ORM\Table(name: 'akce_seznam')]
#[ORM\UniqueConstraint(name: 'url_akce_rok_typ', columns: ['url_akce', 'rok', 'typ'])]
#[ORM\Index(columns: ['rok'], name: 'rok')]
#[ORM\Index(columns: ['patri_pod'], name: 'patri_pod')]
#[ORM\Index(columns: ['lokace'], name: 'lokace')]
#[ORM\Index(columns: ['typ'], name: 'typ')]
#[ORM\Index(columns: ['stav'], name: 'stav')]
#[ORM\Index(columns: ['popis'], name: 'popis')]
#[ORM\Index(columns: ['zamcel'], name: 'FK_akce_seznam_zamcel_to_uzivatele_hodnoty')]
#[ORM\UniqueConstraint(name: 'PRIMARY', columns: ['id_akce'])]
class Activity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_akce', type: Types::INTEGER)]
    private ?int $idAkce = null;

    #[ORM\Column(name: 'patri_pod', type: Types::INTEGER, nullable: true)]
    private ?int $patriPod = null;

    #[ORM\Column(name: 'nazev_akce', type: Types::STRING, length: 255, nullable: false)]
    private string $nazevAkce;

    #[ORM\Column(name: 'url_akce', type: Types::STRING, length: 64, nullable: true)]
    private ?string $urlAkce = null;

    #[ORM\Column(name: 'zacatek', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $zacatek = null;

    #[ORM\Column(name: 'konec', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTime $konec = null;

    #[ORM\Column(name: 'lokace', type: Types::INTEGER, nullable: true)]
    private ?int $lokace = null;

    #[ORM\Column(name: 'kapacita', type: Types::INTEGER, nullable: false)]
    private int $kapacita;

    #[ORM\Column(name: 'kapacita_f', type: Types::INTEGER, nullable: false)]
    private int $kapacitaF;

    #[ORM\Column(name: 'kapacita_m', type: Types::INTEGER, nullable: false)]
    private int $kapacitaM;

    #[ORM\Column(name: 'cena', type: Types::INTEGER, nullable: false)]
    private int $cena;

    #[ORM\Column(name: 'bez_slevy', type: Types::BOOLEAN, nullable: false, options: [
        'comment' => 'na aktivitu se neuplatňují slevy',
    ])]
    private bool $bezSlevy;

    #[ORM\Column(name: 'nedava_bonus', type: Types::BOOLEAN, nullable: false, options: [
        'comment' => 'aktivita negeneruje organizátorovi bonus za vedení aktivity',
    ])]
    private bool $nedavaBonus;

    #[ORM\Column(name: 'typ', type: Types::INTEGER, nullable: false)]
    private int $typ;

    #[ORM\Column(name: 'dite', type: Types::STRING, length: 64, nullable: true, options: [
        'comment' => 'potomci oddělení čárkou',
    ])]
    private ?string $dite = null;

    #[ORM\Column(name: 'rok', type: Types::INTEGER, nullable: false)]
    private int $rok;

    #[ORM\Column(name: 'stav', type: Types::INTEGER, nullable: false, options: [
        'default' => 1,
    ])]
    private int $stav = 1;

    #[ORM\Column(name: 'teamova', type: Types::BOOLEAN, nullable: false)]
    private bool $teamova;

    #[ORM\Column(name: 'team_min', type: Types::INTEGER, nullable: true, options: [
        'comment' => 'minimální velikost teamu',
    ])]
    private ?int $teamMin = null;

    #[ORM\Column(name: 'team_max', type: Types::INTEGER, nullable: true, options: [
        'comment' => 'maximální velikost teamu',
    ])]
    private ?int $teamMax = null;

    #[ORM\Column(name: 'team_kapacita', type: Types::INTEGER, nullable: true, options: [
        'comment' => 'max. počet týmů, pokud jde o další kolo týmové aktivity',
    ])]
    private ?int $teamKapacita = null;

    #[ORM\Column(name: 'team_nazev', type: Types::STRING, length: 255, nullable: true)]
    private ?string $teamNazev = null;

    #[ORM\Column(name: 'zamcel', type: Types::INTEGER, nullable: true, options: [
        'comment' => 'případně kdo zamčel aktivitu pro svůj team',
    ])]
    private ?int $zamcel = null;

    #[ORM\Column(name: 'zamcel_cas', type: Types::DATETIME_MUTABLE, nullable: true, options: [
        'comment' => 'případně kdy zamčel aktivitu',
    ])]
    private ?\DateTime $zamcelCas = null;

    #[ORM\Column(name: 'popis', type: Types::INTEGER, nullable: false)]
    private int $popis;

    #[ORM\Column(name: 'popis_kratky', type: Types::STRING, length: 255, nullable: false)]
    private string $opisKratky;

    #[ORM\Column(name: 'vybaveni', type: Types::TEXT, nullable: false)]
    private string $vybaveni;

    #[ORM\Column(name: 'team_limit', type: Types::INTEGER, nullable: true, options: [
        'comment' => 'uživatelem (vedoucím týmu) nastavený limit kapacity menší roven team_max, ale větší roven team_min. Prostřednictvím on update triggeru kontrolována tato vlastnost a je-li non-null, tak je tato kapacita nastavena do sloupce `kapacita`',
    ])]
    private ?int $teamLimit = null;

    #[ORM\Column(name: 'probehla_korekce', type: Types::BOOLEAN, nullable: false, options: [
        'default' => 0,
    ])]
    private bool $probehlaKorekce = false;

    public function getIdAkce(): ?int
    {
        return $this->idAkce;
    }

    public function getPatriPod(): ?int
    {
        return $this->patriPod;
    }

    public function setPatriPod(?int $patriPod): self
    {
        $this->patriPod = $patriPod;

        return $this;
    }

    public function getNazevAkce(): string
    {
        return $this->nazevAkce;
    }

    public function setNazevAkce(string $nazevAkce): self
    {
        $this->nazevAkce = $nazevAkce;

        return $this;
    }

    public function getUrlAkce(): ?string
    {
        return $this->urlAkce;
    }

    public function setUrlAkce(?string $urlAkce): self
    {
        $this->urlAkce = $urlAkce;

        return $this;
    }

    public function getZacatek(): ?\DateTime
    {
        return $this->zacatek;
    }

    public function setZacatek(?\DateTime $zacatek): self
    {
        $this->zacatek = $zacatek;

        return $this;
    }

    public function getKonec(): ?\DateTime
    {
        return $this->konec;
    }

    public function setKonec(?\DateTime $konec): self
    {
        $this->konec = $konec;

        return $this;
    }

    public function getLokace(): ?int
    {
        return $this->lokace;
    }

    public function setLokace(?int $lokace): self
    {
        $this->lokace = $lokace;

        return $this;
    }

    public function getKapacita(): int
    {
        return $this->kapacita;
    }

    public function setKapacita(int $kapacita): self
    {
        $this->kapacita = $kapacita;

        return $this;
    }

    public function getKapacitaF(): int
    {
        return $this->kapacitaF;
    }

    public function setKapacitaF(int $kapacitaF): self
    {
        $this->kapacitaF = $kapacitaF;

        return $this;
    }

    public function getKapacitaM(): int
    {
        return $this->kapacitaM;
    }

    public function setKapacitaM(int $kapacitaM): self
    {
        $this->kapacitaM = $kapacitaM;

        return $this;
    }

    public function getCena(): int
    {
        return $this->cena;
    }

    public function setCena(int $cena): self
    {
        $this->cena = $cena;

        return $this;
    }

    public function getBezSlevy(): bool
    {
        return $this->bezSlevy;
    }

    public function setBezSlevy(bool $bezSlevy): self
    {
        $this->bezSlevy = $bezSlevy;

        return $this;
    }

    public function getNedavaBonus(): bool
    {
        return $this->nedavaBonus;
    }

    public function setNedavaBonus(bool $nedavaBonus): self
    {
        $this->nedavaBonus = $nedavaBonus;

        return $this;
    }

    public function getTyp(): int
    {
        return $this->typ;
    }

    public function setTyp(int $typ): self
    {
        $this->typ = $typ;

        return $this;
    }

    public function getDite(): ?string
    {
        return $this->dite;
    }

    public function setDite(?string $dite): self
    {
        $this->dite = $dite;

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

    public function getStav(): int
    {
        return $this->stav;
    }

    public function setStav(int $stav): self
    {
        $this->stav = $stav;

        return $this;
    }

    public function getTeamova(): bool
    {
        return $this->teamova;
    }

    public function setTeamova(bool $teamova): self
    {
        $this->teamova = $teamova;

        return $this;
    }

    public function getTeamMin(): ?int
    {
        return $this->teamMin;
    }

    public function setTeamMin(?int $teamMin): self
    {
        $this->teamMin = $teamMin;

        return $this;
    }

    public function getTeamMax(): ?int
    {
        return $this->teamMax;
    }

    public function setTeamMax(?int $teamMax): self
    {
        $this->teamMax = $teamMax;

        return $this;
    }

    public function getTeamKapacita(): ?int
    {
        return $this->teamKapacita;
    }

    public function setTeamKapacita(?int $teamKapacita): self
    {
        $this->teamKapacita = $teamKapacita;

        return $this;
    }

    public function getTeamNazev(): ?string
    {
        return $this->teamNazev;
    }

    public function setTeamNazev(?string $teamNazev): self
    {
        $this->teamNazev = $teamNazev;

        return $this;
    }

    public function getZamcel(): ?int
    {
        return $this->zamcel;
    }

    public function setZamcel(?int $zamcel): self
    {
        $this->zamcel = $zamcel;

        return $this;
    }

    public function getZamcelCas(): ?\DateTime
    {
        return $this->zamcelCas;
    }

    public function setZamcelCas(?\DateTime $zamcelCas): self
    {
        $this->zamcelCas = $zamcelCas;

        return $this;
    }

    public function getPopis(): int
    {
        return $this->popis;
    }

    public function setPopis(int $popis): self
    {
        $this->popis = $popis;

        return $this;
    }

    public function getPopisKratky(): string
    {
        return $this->opisKratky;
    }

    public function setPopisKratky(string $opisKratky): self
    {
        $this->opisKratky = $opisKratky;

        return $this;
    }

    public function getVybaveni(): string
    {
        return $this->vybaveni;
    }

    public function setVybaveni(string $vybaveni): self
    {
        $this->vybaveni = $vybaveni;

        return $this;
    }

    public function getTeamLimit(): ?int
    {
        return $this->teamLimit;
    }

    public function setTeamLimit(?int $teamLimit): self
    {
        $this->teamLimit = $teamLimit;

        return $this;
    }

    public function getProbehlaKorekce(): bool
    {
        return $this->probehlaKorekce;
    }

    public function setProbehlaKorekce(bool $probehlaKorekce): self
    {
        $this->probehlaKorekce = $probehlaKorekce;

        return $this;
    }
}
