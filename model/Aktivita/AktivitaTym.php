<?php

namespace Gamecon\Aktivita;

use App\Entity\Team;
use App\Repository\TeamRepository;
use App\Service\AktivitaTymService;
use Gamecon\Aktivita\SqlStruktura\AkceTymSqlStruktura;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

/**
 * Nastaveno v services.yaml že se dá získat AktivitaTymService
 */
class AktivitaTym extends \DbObject
{
    public const HAJENI_TEAMU_HODIN = 72;
    public const CAS_NA_PRIPRAVENI_TYMU_MINUT = 30;

    protected static $tabulka = AkceTymSqlStruktura::AKCE_TYM_TABULKA;

    private function __construct(
        private readonly Team $team,
        private readonly int $idAktivity,
    ) {}

    // ====== FACTORY ======

    /** Najde tým podle ID. */
    public static function najdi(int $idTymu, int $idAktivity): self
    {
        $team = self::teamRepository()->find($idTymu)
            ?? throw new \Chyba('Tým ' . $idTymu . ' neexistuje');

        return new self($team, $idAktivity);
    }

    /** Najde tým podle kódu (4místný kód platný v rámci aktivity). */
    public static function najdiPodleKodu(int $idAktivity, int $kodTymu): self
    {
        $team = self::teamRepository()->findByKodNaAktivite($idAktivity, $kodTymu)
            ?? throw new \Chyba('Tým s kódem ' . $kodTymu . ' na této aktivitě neexistuje');

        return new self($team, $idAktivity);
    }

    /** Vrátí tým uživatele na aktivitě, nebo null pokud v žádném týmu není. */
    public static function proUzivatele(int $idUzivatele, int $idAktivity): ?self
    {
        $idTymu = self::service()->idTymuUzivatele($idUzivatele, $idAktivity);
        if ($idTymu === null) {
            return null;
        }
        $team = self::teamRepository()->find($idTymu)
            ?? throw new \Chyba('Tým ' . $idTymu . ' neexistuje');

        return new self($team, $idAktivity);
    }

    // ====== INSTANCE GETTERY ======

    public function getId(): int
    {
        return (int)$this->team->getId();
    }

    public function getKod(): int
    {
        return $this->team->getKod();
    }

    public function isVerejny(): bool
    {
        return $this->team->isVerejny();
    }

    public function idKapitana(): ?int
    {
        return (int)$this->team->getKapitan()->getId() ?: null;
    }

    public function jeKapitanem(int $idUzivatele): bool
    {
        return $this->idKapitana() === $idUzivatele;
    }

    // ====== INSTANCE OPERACE ======

    public function zkontrolujVolnouKapacitu(): void
    {
        self::service()->zkontrolujVolnouKapacituVTymu($this->getId());
    }

    public function zkontrolujZeJeKapitan(int $idUzivatele): void
    {
        if (!$this->jeKapitanem($idUzivatele)) {
            throw new \Chyba('Tuto akci může provést pouze kapitán týmu');
        }
    }

    public function nastavVerejnost(bool $verejny): void
    {
        self::service()->nastavVerejnostTymu($this->getKod(), $this->idAktivity, $verejny);
    }

    public function pregenerujKod(): int
    {
        return self::service()->pregenerujKodTymu($this->getKod(), $this->idAktivity);
    }

    /** @return \Uzivatel[] seřazení: kapitán první, pak ostatní podle pořadí přihlášení */
    public function clenoveTymu(): array
    {
        return self::service()->clenoveTymu($this->getKod(), $this->idAktivity);
    }

    /**
     * Vrátí ID aktivit, na které je tým přihlášen, kromě zadané výjimky.
     * @return int[]
     */
    public function idDalsichAktivit(int $vyjmaIdAktivity = -1): array
    {
        return self::service()->idDalsichAktivitTymu($this->getId(), $vyjmaIdAktivity);
    }

    public function pridejNaAktivitu(int $idAktivity): void
    {
        self::service()->pridejTymNaAktivitu($this->getId(), $idAktivity);
    }

    // ====== STATICKÉ — OPERACE NA ÚROVNI AKTIVITY ======

    // todo(tym): Je potřeba zajistit že před přidáním účastníka do týmu je přihlášený na všechny aktivity týmu
    // todo(tym): dochází ke zdvojené kontrole na kapacitu
    public static function prihlasUzivateleDoTymu(int $idUzivatele, int $idAktivity, int $kodTymu, bool $ignorovatLimity = false): void
    {
        self::service()->prihlasUzivateleDoTymu($idUzivatele, $idAktivity, $kodTymu, $ignorovatLimity);
    }

    public static function odhlasUzivateleOdTymu(int $idUzivatele, int $idAktivity): void
    {
        self::service()->odhlasUzivateleOdTymu($idUzivatele, $idAktivity);
    }

    public static function zkontrolujMuzeZalozitTym(int $idAktivity): void
    {
        self::service()->zkontrolujMuzeZalozitTym($idAktivity);
    }

    /** @return [int|null, int] [$team_kapacita, $pocetAktualnych] nebo null pokud team_kapacita není nastaven */
    public static function tymAktivitaKapacity(int $idAktivity): ?array
    {
        return self::service()->tymAktivitaKapacity($idAktivity);
    }

    /**
     * Ověří zda se může založit další tým (je místo v kapacitě).
     * @return bool true pokud se může založit, false pokud je kapacita plná
     */
    public static function muzePridatDalsiTym(int $idAktivity): bool
    {
        return self::service()->muzePridatDalsiTym($idAktivity);
    }

    public static function maAktivitaTym(int $idAktivity): bool
    {
        return self::service()->maAktivitaTym($idAktivity);
    }

    /** @return VerejnyTym[] */
    public static function verejneTymy(int $idAktivity): array
    {
        return self::service()->verejneTymy($idAktivity);
    }

    /** @return TymVSeznamu[] */
    public static function vsechnyTymy(int $idAktivity): array
    {
        return self::service()->vsechnyTymy($idAktivity);
    }

    /**
     * Vrátí počet volných míst ve všech veřejných týmech na aktivitě.
     * @return int Součet volných míst (limit - počet členů) ve všech veřejných týmech
     */
    // todo(tym): tým bez limitu (limit === null) se počítá jako 0 volných míst - pokud tým nemá limit, měl by se asi počítat jako neomezený
    public static function pocetVolnychMistVVerejnychTymech(int $idAktivity): int
    {
        return self::service()->pocetVolnychMistVVerejnychTymech($idAktivity);
    }

    public static function rozebratTym(int $kodTymu, int $idAktivity): void
    {
        self::service()->rozebratTym($kodTymu, $idAktivity);
    }

    public function jeRozpracovany(): bool
    {
        return self::service()->jeRozpracovany($this->getId());
    }

    /** @return int[] */
    public static function rozpracovaneTymyIds(?int $casNaPripraveniMinut = null): array
    {
        return self::service()->rozpracovaneTymyIds($casNaPripraveniMinut);
    }

    public static function smazRozpracovaneTymy(?int $casNaPripraveniMinut = null): int
    {
        return self::service()->smazRozpracovaneTymy($casNaPripraveniMinut);
    }

    /** @return int[] */
    public static function expirovaneTymyIds(?int $hajeniHodin = null): array
    {
        return self::service()->expirovaneTymyIds($hajeniHodin);
    }

    public static function infoOTymuUzivatele(int $idUzivatele, int $idAktivity): ?InfoOTymu
    {
        return self::service()->infoOTymuUzivatele($idUzivatele, $idAktivity);
    }

    public static function idTymuUzivatele(int $idUzivatele, int $idAktivity): ?int
    {
        return self::service()->idTymuUzivatele($idUzivatele, $idAktivity);
    }

    public static function casZalozeniTymuUzivatele(int $idUzivatele, int $idAktivity): ?int
    {
        return self::service()->casZalozeniTymuUzivatele($idUzivatele, $idAktivity);
    }

    public static function vratKodTymuProUzivatele(int $idUzivatele, int $idAktivity): int
    {
        return self::service()->vratKodTymuProUzivatele($idUzivatele, $idAktivity);
    }

    private static function service(): AktivitaTymService
    {
        return SystemoveNastaveni::zGlobals()
            ->kernel()
            ->getContainer()
            ->get(AktivitaTymService::class);
    }

    private static function teamRepository(): TeamRepository
    {
        return SystemoveNastaveni::zGlobals()
            ->kernel()
            ->getContainer()
            ->get(TeamRepository::class);
    }
}
