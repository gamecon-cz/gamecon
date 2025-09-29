<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Enum\SymfonyPohlaviEnum;
use App\Repository\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'uzivatele_hodnoty')]
#[ORM\Index(columns: ['infopult_poznamka'], name: 'infopult_poznamka_idx')]
#[ORM\UniqueConstraint(name: 'login_uzivatele', columns: ['login_uzivatele'])]
#[ORM\UniqueConstraint(name: 'email1_uzivatele', columns: ['email1_uzivatele'])]
class User
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_uzivatele', type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(name: 'login_uzivatele', length: 255, nullable: false)]
    private string $login;

    #[ORM\Column(name: 'jmeno_uzivatele', length: 100, nullable: false)]
    private string $jmeno;

    #[ORM\Column(name: 'prijmeni_uzivatele', length: 100, nullable: false)]
    private string $prijmeni;

    #[ORM\Column(name: 'ulice_a_cp_uzivatele', length: 255, nullable: false)]
    private string $uliceACp;

    #[ORM\Column(name: 'mesto_uzivatele', length: 100, nullable: false)]
    private string $mesto;

    #[ORM\Column(name: 'stat_uzivatele', type: Types::INTEGER, nullable: false)]
    private int $stat;

    #[ORM\Column(name: 'psc_uzivatele', length: 20, nullable: false)]
    private string $psc;

    #[ORM\Column(name: 'telefon_uzivatele', length: 100, nullable: false)]
    private string $telefon;

    #[ORM\Column(name: 'datum_narozeni', type: Types::DATE_MUTABLE, nullable: false)]
    private \DateTimeInterface $datumNarozeni;

    #[ORM\Column(name: 'heslo_md5', length: 255, nullable: false)]
    private string $hesloMd5;

    #[ORM\Column(name: 'email1_uzivatele', length: 255, nullable: false)]
    private string $email;

    #[ORM\Column(name: 'nechce_maily', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $nechceMaily = null;

    #[ORM\Column(name: 'mrtvy_mail', type: Types::BOOLEAN, nullable: false, options: ['default' => 0])]
    private bool $mrtvyMail = false;

    #[ORM\Column(name: 'forum_razeni', length: 1, nullable: false)]
    private string $forumRazeni;

    #[ORM\Column(name: 'random', length: 20, nullable: false)]
    private string $random;

    #[ORM\Column(name: 'zustatek', type: Types::INTEGER, nullable: false, options: ['default' => 0])]
    private int $zustatek = 0;

    #[ORM\Column(name: 'pohlavi', type: Types::STRING, nullable: false, enumType: SymfonyPohlaviEnum::class)]
    private SymfonyPohlaviEnum $pohlavi;

    #[ORM\Column(name: 'registrovan', type: Types::DATETIME_MUTABLE, nullable: false)]
    private \DateTimeInterface $registrovan;

    #[ORM\Column(name: 'ubytovan_s', length: 255, nullable: true)]
    private ?string $ubytovanS = null;

    #[ORM\Column(name: 'poznamka', length: 4096, nullable: false)]
    private string $poznamka = '';

    #[ORM\Column(name: 'pomoc_typ', length: 64, nullable: false)]
    private string $pomocTyp = '';

    #[ORM\Column(name: 'pomoc_vice', type: Types::TEXT, nullable: false)]
    private string $pomocVice = '';

    #[ORM\Column(name: 'op', length: 4096, nullable: false)]
    private string $op = '';

    #[ORM\Column(name: 'potvrzeni_zakonneho_zastupce', type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $potvrzeniZakonnehoZastupce = null;

    #[ORM\Column(name: 'potvrzeni_proti_covid19_pridano_kdy', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $potvrzeniProtiCovid19PridanoKdy = null;

    #[ORM\Column(name: 'potvrzeni_proti_covid19_overeno_kdy', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $potvrzeniProtiCovid19OverenoKdy = null;

    #[ORM\Column(name: 'infopult_poznamka', length: 128, nullable: false)]
    private string $infopultPoznamka = '';

    #[ORM\Column(name: 'typ_dokladu_totoznosti', length: 16, nullable: false)]
    private string $typDokladuTotoznosti = '';

    #[ORM\Column(name: 'statni_obcanstvi', length: 64, nullable: true)]
    private ?string $statniObcanstvi = null;

    #[ORM\Column(name: 'z_rychloregistrace', type: Types::BOOLEAN, nullable: true, options: ['default' => 0])]
    private bool $zRychloregistrace = false;

    #[ORM\Column(name: 'potvrzeni_zakonneho_zastupce_soubor', type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $potvrzeniZakonnehoZastupceSoubor = null;

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLogin(): string
    {
        return $this->login;
    }

    public function setLogin(string $login): static
    {
        $this->login = $login;
        return $this;
    }

    public function getJmeno(): string
    {
        return $this->jmeno;
    }

    public function setJmeno(string $jmeno): static
    {
        $this->jmeno = $jmeno;
        return $this;
    }

    public function getPrijmeni(): string
    {
        return $this->prijmeni;
    }

    public function setPrijmeni(string $prijmeni): static
    {
        $this->prijmeni = $prijmeni;
        return $this;
    }

    public function getUliceACp(): string
    {
        return $this->uliceACp;
    }

    public function setUliceACp(string $uliceACp): static
    {
        $this->uliceACp = $uliceACp;
        return $this;
    }

    public function getMesto(): string
    {
        return $this->mesto;
    }

    public function setMesto(string $mesto): static
    {
        $this->mesto = $mesto;
        return $this;
    }

    public function getStat(): int
    {
        return $this->stat;
    }

    public function setStat(int $stat): static
    {
        $this->stat = $stat;
        return $this;
    }

    public function getPsc(): string
    {
        return $this->psc;
    }

    public function setPsc(string $psc): static
    {
        $this->psc = $psc;
        return $this;
    }

    public function getTelefon(): string
    {
        return $this->telefon;
    }

    public function setTelefon(string $telefon): static
    {
        $this->telefon = $telefon;
        return $this;
    }

    public function getDatumNarozeni(): \DateTimeInterface
    {
        return $this->datumNarozeni;
    }

    public function setDatumNarozeni(\DateTimeInterface $datumNarozeni): static
    {
        $this->datumNarozeni = $datumNarozeni;
        return $this;
    }

    public function getHesloMd5(): string
    {
        return $this->hesloMd5;
    }

    public function setHesloMd5(string $hesloMd5): static
    {
        $this->hesloMd5 = $hesloMd5;
        return $this;
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

    public function getNechceMaily(): ?\DateTimeInterface
    {
        return $this->nechceMaily;
    }

    public function setNechceMaily(?\DateTimeInterface $nechceMaily): static
    {
        $this->nechceMaily = $nechceMaily;
        return $this;
    }

    public function isMrtvyMail(): bool
    {
        return $this->mrtvyMail;
    }

    public function setMrtvyMail(bool $mrtvyMail): static
    {
        $this->mrtvyMail = $mrtvyMail;
        return $this;
    }

    public function getForumRazeni(): string
    {
        return $this->forumRazeni;
    }

    public function setForumRazeni(string $forumRazeni): static
    {
        $this->forumRazeni = $forumRazeni;
        return $this;
    }

    public function getRandom(): string
    {
        return $this->random;
    }

    public function setRandom(string $random): static
    {
        $this->random = $random;
        return $this;
    }

    public function getZustatek(): int
    {
        return $this->zustatek;
    }

    public function setZustatek(int $zustatek): static
    {
        $this->zustatek = $zustatek;
        return $this;
    }

    public function getPohlavi(): SymfonyPohlaviEnum
    {
        return $this->pohlavi;
    }

    public function setPohlavi(SymfonyPohlaviEnum $pohlavi): static
    {
        $this->pohlavi = $pohlavi;
        return $this;
    }

    public function getRegistrovan(): \DateTimeInterface
    {
        return $this->registrovan;
    }

    public function setRegistrovan(\DateTimeInterface $registrovan): static
    {
        $this->registrovan = $registrovan;
        return $this;
    }

    public function getUbytovanS(): ?string
    {
        return $this->ubytovanS;
    }

    public function setUbytovanS(?string $ubytovanS): static
    {
        $this->ubytovanS = $ubytovanS;
        return $this;
    }

    public function getPoznamka(): string
    {
        return $this->poznamka;
    }

    public function setPoznamka(string $poznamka): static
    {
        $this->poznamka = $poznamka;
        return $this;
    }

    public function getPomocTyp(): string
    {
        return $this->pomocTyp;
    }

    public function setPomocTyp(string $pomocTyp): static
    {
        $this->pomocTyp = $pomocTyp;
        return $this;
    }

    public function getPomocVice(): string
    {
        return $this->pomocVice;
    }

    public function setPomocVice(string $pomocVice): static
    {
        $this->pomocVice = $pomocVice;
        return $this;
    }

    public function getOp(): string
    {
        return $this->op;
    }

    public function setOp(string $op): static
    {
        $this->op = $op;
        return $this;
    }

    public function getPotvrzeniZakonnehoZastupce(): ?\DateTimeInterface
    {
        return $this->potvrzeniZakonnehoZastupce;
    }

    public function setPotvrzeniZakonnehoZastupce(?\DateTimeInterface $potvrzeniZakonnehoZastupce): static
    {
        $this->potvrzeniZakonnehoZastupce = $potvrzeniZakonnehoZastupce;
        return $this;
    }

    public function getPotvrzeniProtiCovid19PridanoKdy(): ?\DateTimeInterface
    {
        return $this->potvrzeniProtiCovid19PridanoKdy;
    }

    public function setPotvrzeniProtiCovid19PridanoKdy(?\DateTimeInterface $potvrzeniProtiCovid19PridanoKdy): static
    {
        $this->potvrzeniProtiCovid19PridanoKdy = $potvrzeniProtiCovid19PridanoKdy;
        return $this;
    }

    public function getPotvrzeniProtiCovid19OverenoKdy(): ?\DateTimeInterface
    {
        return $this->potvrzeniProtiCovid19OverenoKdy;
    }

    public function setPotvrzeniProtiCovid19OverenoKdy(?\DateTimeInterface $potvrzeniProtiCovid19OverenoKdy): static
    {
        $this->potvrzeniProtiCovid19OverenoKdy = $potvrzeniProtiCovid19OverenoKdy;
        return $this;
    }

    public function getInfopultPoznamka(): string
    {
        return $this->infopultPoznamka;
    }

    public function setInfopultPoznamka(string $infopultPoznamka): static
    {
        $this->infopultPoznamka = $infopultPoznamka;
        return $this;
    }

    public function getTypDokladuTotoznosti(): string
    {
        return $this->typDokladuTotoznosti;
    }

    public function setTypDokladuTotoznosti(string $typDokladuTotoznosti): static
    {
        $this->typDokladuTotoznosti = $typDokladuTotoznosti;
        return $this;
    }

    public function getStatniObcanstvi(): ?string
    {
        return $this->statniObcanstvi;
    }

    public function setStatniObcanstvi(?string $statniObcanstvi): static
    {
        $this->statniObcanstvi = $statniObcanstvi;
        return $this;
    }

    public function isZRychloregistrace(): bool
    {
        return $this->zRychloregistrace;
    }

    public function setZRychloregistrace(bool $zRychloregistrace): static
    {
        $this->zRychloregistrace = $zRychloregistrace;
        return $this;
    }

    public function getPotvrzeniZakonnehoZastupceSoubor(): ?\DateTimeInterface
    {
        return $this->potvrzeniZakonnehoZastupceSoubor;
    }

    public function setPotvrzeniZakonnehoZastupceSoubor(?\DateTimeInterface $potvrzeniZakonnehoZastupceSoubor): static
    {
        $this->potvrzeniZakonnehoZastupceSoubor = $potvrzeniZakonnehoZastupceSoubor;
        return $this;
    }

    public function getCelemeJmeno(): string
    {
        return $this->jmeno . ' ' . $this->prijmeni;
    }
}
