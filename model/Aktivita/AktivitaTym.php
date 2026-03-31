<?php

namespace Gamecon\Aktivita;

use App\Service\AktivitaTymService;
use Gamecon\Aktivita\SqlStruktura\AkceTymSqlStruktura;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

class AktivitaTym extends \DbObject
{
    public const HAJENI_TEAMU_HODIN = 72;

    protected static $tabulka = AkceTymSqlStruktura::AKCE_TYM_TABULKA;

    /**
     * Nastaveno v services.yaml že se dá získat AktivitaTymService
     */
    private static function service(): AktivitaTymService
    {
        return SystemoveNastaveni::zGlobals()
            ->kernel()
            ->getContainer()
            ->get(AktivitaTymService::class);
    }

    // todo(tym): Je potřeba zajistit že před přidáním účastníka do týmu je přihlášený na všechny aktivity týmu
    // todo(tym): dochází ke zdvojené kontrole na kapacitu
    public static function prihlasUzivateleDoTymu(int $idUzivatele, int $idAktivity, int $kodTymu, bool $ignorovatLimity = false): void
    {
        self::service()->prihlasUzivateleDoTymu($idUzivatele, $idAktivity, $kodTymu, $ignorovatLimity);
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

    public static function najdiTymPodleKodu(int $idAktivity, int $kodTymu): int
    {
        return self::service()->najdiTymPodleKodu($idAktivity, $kodTymu);
    }

    public static function zkontrolujVolnouKapacituVTymu(int $idTymu): void
    {
        self::service()->zkontrolujVolnouKapacituVTymu($idTymu);
    }

    public static function odhlasUzivateleOdTymu(int $idUzivatele, int $idAktivity): void
    {
        self::service()->odhlasUzivateleOdTymu($idUzivatele, $idAktivity);
    }

    public static function infoOTymuUzivatele(int $idUzivatele, int $idAktivity): ?InfoOTymu
    {
        return self::service()->infoOTymuUzivatele($idUzivatele, $idAktivity);
    }

    public static function vratKodTymuProUzivatele(int $idUzivatele, int $idAktivity): int
    {
        return self::service()->vratKodTymuProUzivatele($idUzivatele, $idAktivity);
    }

    public static function jeKapitanem(int $idUzivatele, int $idAktivity): bool
    {
        return self::service()->jeKapitanem($idUzivatele, $idAktivity);
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

    public static function nastavVerejnostTymu(int $kodTymu, int $idAktivity, bool $verejny): void
    {
        self::service()->nastavVerejnostTymu($kodTymu, $idAktivity, $verejny);
    }

    public static function zkontrolujZeJeKapitan(int $kodTymu, int $idAktivity, int $idUzivatele): void
    {
        self::service()->zkontrolujZeJeKapitan($kodTymu, $idAktivity, $idUzivatele);
    }

    public static function verejnostTymuPodleKodu(int $kodTymu, int $idAktivity): ?bool
    {
        return self::service()->verejnostTymuPodleKodu($kodTymu, $idAktivity);
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

    /** @return int[] */
    public static function expirovaneTymyIds(?int $hajeniHodin = null): array
    {
        return self::service()->expirovaneTymyIds($hajeniHodin);
    }

    // todo(tym): používat id a ne kód týmu
    /** @return \Uzivatel[] seřazení: kapitán první, pak ostatní podle pořadí přihlášení */
    public static function clenoveTymu(int $kodTymu, int $idAktivity): array
    {
        return self::service()->clenoveTymu($kodTymu, $idAktivity);
    }

    public static function idKapitanaTymu(int $kodTymu, int $idAktivity): ?int
    {
        return self::service()->idKapitanaTymu($kodTymu, $idAktivity);
    }

    /** @return TymVSeznamu[] */
    public static function vsechnyTymy(int $idAktivity): array
    {
        return self::service()->vsechnyTymy($idAktivity);
    }

    public static function pregenerujKodTymu(int $kodTymu, int $idAktivity): int
    {
        return self::service()->pregenerujKodTymu($kodTymu, $idAktivity);
    }

    /**
     * Přidá tým na aktivitu (záznam do akce_tym_akce).
     * Pokud tým na aktivitě už je, nic se nestane.
     */
    public static function pridejTymNaAktivitu(int $idTymu, int $idAktivity): void
    {
        self::service()->pridejTymNaAktivitu($idTymu, $idAktivity);
    }

    /**
     * Vrátí ID týmu, ve kterém je uživatel na dané aktivitě, nebo null.
     */
    public static function idTymuUzivatele(int $idUzivatele, int $idAktivity): ?int
    {
        return self::service()->idTymuUzivatele($idUzivatele, $idAktivity);
    }

    /** Vrátí timestamp založení týmu uživatele na dané aktivitě, nebo null */
    public static function casZalozeniTymuUzivatele(int $idUzivatele, int $idAktivity): ?int
    {
        return self::service()->casZalozeniTymuUzivatele($idUzivatele, $idAktivity);
    }

    /**
     * Vrátí ID aktivit, na které je tým přihlášen, kromě zadané výjimky.
     * @return int[]
     */
    public static function idDalsichAktivitTymu(int $idTymu, int $vyjmaIdAktivity = -1): array
    {
        return self::service()->idDalsichAktivitTymu($idTymu, $vyjmaIdAktivity);
    }
}
