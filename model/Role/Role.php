<?php

declare(strict_types=1);

namespace Gamecon\Role;

use Gamecon\Role\Exceptions\NeznamyVyznamRole;
use Gamecon\Role\SqlStruktura\RoleSqlStruktura;

/**
 * @method static Role zId($id, bool $zCache = false)
 */
class Role extends \DbObject
{

    protected static $tabulka = RoleSqlStruktura::ROLE_TABULKA;
    protected static $pk      = RoleSqlStruktura::ID_ROLE;

    /**
     * Konstanty jsou kopie SQL tabulky `role_seznam`
     * PO PŘIDÁNÍ ROLE ROZŠIŘ SEZNAM @see \Gamecon\Role\Role::nazevRolePodleId
     */
    // TRVALÉ ROLE
    public const ORGANIZATOR          = 2; // Organizátor (zdarma), Člen organizačního týmu GC
    public const PUL_ORG_BONUS_UBYTKO = 21; // Ubytování zdarma
    public const PUL_ORG_BONUS_TRICKO = 22; // Dvě jakákoli trička zdarma
    public const CESTNY_ORGANIZATOR   = 15; // Bývalý organizátor GC
    public const CFO                  = 20; // Organizátor, který může nakládat s financemi GC
    public const PREZENCNI_ADMIN      = 16; // Pro změnu účastníků v uzavřených aktivitách. NEBEZPEČNÉ, NEPOUŽÍVAT!
    public const VYPRAVECSKA_SKUPINA  = 9; // Organizátorská skupina pořádající na GC (dodavatelé, …)
    public const CLEN_RADY            = 23;
    public const SEF_INFOPULTU        = 24;
    public const SEF_PROGRAMU         = 25;
    public const MINI_ORG             = 26;

    // DOČASNÉ ROČNÍKOVÉ ROLE
    public const LETOSNI_VYPRAVEC                   = ROLE_VYPRAVEC; // Organizátor aktivit na GC
    public const LETOSNI_ZAZEMI                     = ROLE_ZAZEMI; // Členové zázemí GC (kuchařky, …)
    public const LETOSNI_INFOPULT                   = ROLE_INFOPULT; // Operátor infopultu
    public const LETOSNI_PARTNER                    = ROLE_PARTNER; // Vystavovatelé, lidé od deskovek, atp.
    public const LETOSNI_DOBROVOLNIK_SENIOR         = ROLE_DOBROVOLNIK_SENIOR; // Dobrovolník dlouhodobě spolupracující s GC
    public const LETOSNI_STREDECNI_NOC_ZDARMA       = ROLE_STREDECNI_NOC_ZDARMA;
    public const LETOSNI_CTVRTECNI_NOC_ZDARMA       = ROLE_CTVRTECNI_NOC_ZDARMA;
    public const LETOSNI_PATECNI_NOC_ZDARMA         = ROLE_PATECNI_NOC_ZDARMA;
    public const LETOSNI_SOBOTNI_NOC_ZDARMA         = ROLE_SOBOTNI_NOC_ZDARMA;
    public const LETOSNI_NEDELNI_NOC_ZDARMA         = ROLE_NEDELNI_NOC_ZDARMA;
    public const LETOSNI_NEODHLASOVAT               = ROLE_NEODHLASOVAT;
    public const LETOSNI_HERMAN                     = ROLE_HERMAN;
    public const LETOSNI_BRIGADNIK                  = ROLE_BRIGADNIK;
    public const ZKONTROLOVANE_UDAJE_NA_LETOSNIM_GC = ROLE_ZKONTROLOVANE_UDAJE;

    public const ROLE_VYPRAVEC_ID_ZAKLAD             = 6;
    public const ROLE_ZAZEMI_ID_ZAKLAD               = 7;
    public const ROLE_INFOPULT_ID_ZAKLAD             = 8;
    public const ROLE_PARTNER_ID_ZAKLAD              = 13;
    public const ROLE_DOBROVOLNIK_SENIOR_ID_ZAKLAD   = 17;
    public const ROLE_STREDECNI_NOC_ZDARMA_ID_ZAKLAD = 18;
    public const ROLE_NEDELNI_NOC_ZDARMA_ID_ZAKLAD   = 19;
    public const ROLE_NEODHLASOVAT_ID_ZAKLAD         = 23;
    public const ROLE_HERMAN_ID_ZAKLAD               = 24;
    public const ROLE_BRIGADNIK_ID_ZAKLAD            = 25;
    public const ROLE_CTVRTECNI_NOC_ZDARMA_ID_ZAKLAD = 26;
    public const ROLE_PATECNI_NOC_ZDARMA_ID_ZAKLAD   = 27;
    public const ROLE_SOBOTNI_NOC_ZDARMA_ID_ZAKLAD   = 28;

    // ROLE ÚČASTI
    public const PRIHLASEN_NA_LETOSNI_GC = ROLE_PRIHLASEN;
    public const PRITOMEN_NA_LETOSNIM_GC = ROLE_PRITOMEN;
    public const ODJEL_Z_LETOSNIHO_GC    = ROLE_ODJEL;

    protected const ROLE_PRIHLASEN_ID_ZAKLAD           = 1;
    protected const ROLE_PRITOMEN_ID_ZAKLAD            = 2;
    protected const ROLE_ODJEL_ID_ZAKLAD               = 3;
    protected const ROLE_ZKONTROLOVANE_UDAJE_ID_ZAKLAD = 29;

    public const UDALOST_PRIHLASEN = 'přihlášen';
    public const UDALOST_PRITOMEN  = 'přítomen';
    public const UDALOST_ODJEL     = 'odjel';

    public const JAKYKOLI_ROCNIK           = -1;
    public const KOEFICIENT_ROCNIKOVE_ROLE = -100000;

    public const TYP_ROCNIKOVA = 'rocnikova';
    public const TYP_UCAST     = 'ucast';
    public const TYP_TRVALA    = 'trvala';

    public const KATEGORIE_OMEZENA = 0; // může přidělovat jen člen rady
    public const KATEGORIE_BEZNA   = 1; // může přidělovat každý

    // TYP TRVALE
    public const VYZNAM_ORGANIZATOR_ZDARMA  = 'ORGANIZATOR_ZDARMA';
    public const VYZNAM_PUL_ORG_UBYTKO      = 'PUL_ORG_UBYTKO';
    public const VYZNAM_PUL_ORG_TRICKO      = 'PUL_ORG_TRICKO';
    public const VYZNAM_MINI_ORG            = 'MINI_ORG';
    public const VYZNAM_CESTNY_ORGANIZATOR  = 'CESTNY_ORGANIZATOR';
    public const VYZNAM_CFO                 = 'CFO';
    public const VYZNAM_ADMIN               = 'ADMIN';
    public const VYZNAM_VYPRAVECSKA_SKUPINA = 'VYPRAVECSKA_SKUPINA';
    public const VYZNAM_CLEN_RADY           = 'CLEN_RADY';
    public const VYZNAM_SEF_INFOPULTU       = 'SEF_INFOPULTU';
    // TYP ROCNIKOVE
    public const VYZNAM_BRIGADNIK            = 'BRIGADNIK';
    public const VYZNAM_DOBROVOLNIK_SENIOR   = 'DOBROVOLNIK_SENIOR';
    public const VYZNAM_HERMAN               = 'HERMAN';
    public const VYZNAM_INFOPULT             = 'INFOPULT';
    public const VYZNAM_NEODHLASOVAT         = 'NEODHLASOVAT';
    public const VYZNAM_PARTNER              = 'PARTNER';
    public const VYZNAM_STREDECNI_NOC_ZDARMA = 'STREDECNI_NOC_ZDARMA';
    public const VYZNAM_SOBOTNI_NOC_ZDARMA   = 'SOBOTNI_NOC_ZDARMA';
    public const VYZNAM_NEDELNI_NOC_ZDARMA   = 'NEDELNI_NOC_ZDARMA';
    public const VYZNAM_VYPRAVEC             = 'VYPRAVEC';
    public const VYZNAM_ZAZEMI               = 'ZAZEMI';
    // podtyp OVEROVACI (tyto role nelze přiřazovat přes admin stránku Práva)
    public const VYZNAM_ZKONTROLOVANE_UDAJE = 'ZKONTROLOVANE_UDAJE';
    // TYP UCAST
    public const VYZNAM_PRIHLASEN = 'PRIHLASEN';
    public const VYZNAM_PRITOMEN  = 'PRITOMEN';
    public const VYZNAM_ODJEL     = 'ODJEL';

    /**
     * @return int[]
     */
    public static function idckaTrvalychRoli(): array
    {
        return [
            Role::ORGANIZATOR,
            Role::PUL_ORG_BONUS_UBYTKO,
            Role::PUL_ORG_BONUS_TRICKO,
            Role::CESTNY_ORGANIZATOR,
            Role::CFO,
            Role::PREZENCNI_ADMIN,
            Role::VYPRAVECSKA_SKUPINA,
            Role::CLEN_RADY,
            Role::SEF_INFOPULTU,
            Role::SEF_PROGRAMU,
            Role::MINI_ORG,
        ];
    }

    public static function vyznamPodleKodu(string $kodRole): string
    {
        return preg_replace('~^GC\d+_~', '', $kodRole);
    }

    public static function kategoriePodleVyznamu(string $vyznam): int
    {
        return match ($vyznam) {
            self::VYZNAM_ORGANIZATOR_ZDARMA => self::KATEGORIE_OMEZENA,
            self::VYZNAM_PUL_ORG_UBYTKO => self::KATEGORIE_OMEZENA,
            self::VYZNAM_PUL_ORG_TRICKO => self::KATEGORIE_OMEZENA,
            self::VYZNAM_MINI_ORG => self::KATEGORIE_OMEZENA,
            self::VYZNAM_CESTNY_ORGANIZATOR => self::KATEGORIE_OMEZENA,
            self::VYZNAM_CFO => self::KATEGORIE_OMEZENA,
            self::VYZNAM_ADMIN => self::KATEGORIE_OMEZENA,
            self::VYZNAM_VYPRAVECSKA_SKUPINA => self::KATEGORIE_OMEZENA,
            self::VYZNAM_CLEN_RADY => self::KATEGORIE_OMEZENA,
            self::VYZNAM_SEF_INFOPULTU => self::KATEGORIE_OMEZENA,
            self::VYZNAM_BRIGADNIK => self::KATEGORIE_OMEZENA,
            self::VYZNAM_ZAZEMI => self::KATEGORIE_OMEZENA,

            self::VYZNAM_DOBROVOLNIK_SENIOR => self::KATEGORIE_BEZNA,
            self::VYZNAM_HERMAN => self::KATEGORIE_BEZNA,
            self::VYZNAM_INFOPULT => self::KATEGORIE_BEZNA,
            self::VYZNAM_NEODHLASOVAT => self::KATEGORIE_BEZNA,
            self::VYZNAM_PARTNER => self::KATEGORIE_BEZNA,
            self::VYZNAM_STREDECNI_NOC_ZDARMA => self::KATEGORIE_BEZNA,
            self::VYZNAM_SOBOTNI_NOC_ZDARMA => self::KATEGORIE_BEZNA,
            self::VYZNAM_NEDELNI_NOC_ZDARMA => self::KATEGORIE_BEZNA,
            self::VYZNAM_VYPRAVEC => self::KATEGORIE_BEZNA,

            self::VYZNAM_PRIHLASEN => self::KATEGORIE_OMEZENA,
            self::VYZNAM_PRITOMEN => self::KATEGORIE_OMEZENA,
            self::VYZNAM_ODJEL => self::KATEGORIE_OMEZENA,
            self::VYZNAM_ZKONTROLOVANE_UDAJE => self::KATEGORIE_OMEZENA,

            default => throw new NeznamyVyznamRole("Vyznam '$vyznam' je neznámý"),
        };
    }

    /**
     * Přihlásil se, neboli registroval, na GameCon
     * @param int $rok
     * @return int
     */
    public static function PRIHLASEN_NA_LETOSNI_GC(int $rok = ROCNIK): int
    {
        return self::prihlasenNaRocnik($rok);
    }

    /**
     * Prošel infopultem a byl na GameConu
     * @param int $rok
     * @return int
     */
    public static function PRITOMEN_NA_LETOSNIM_GC(int $rok = ROCNIK): int
    {
        return self::pritomenNaRocniku($rok);
    }

    /**
     * Prošel infopultem na odchodu a odjel z GameConu
     * @param int $rok
     * @return int
     */
    public static function ODJEL_Z_LETOSNIHO_GC(int $rok = ROCNIK): int
    {
        return self::odjelZRocniku($rok);
    }

    public static function ZKONTROLOVANE_UDAJE_PRO_LETOSNI_GC(int $rok = ROCNIK): int
    {
        return self::zkontrolovaneUdaje($rok);
    }

    public static function prihlasenNaRocnik(int $rok): int
    {
        return self::vytvorIdRoleUcasti($rok, self::ROLE_PRIHLASEN_ID_ZAKLAD);
    }

    /**
     * Například z roku 2024 a základu role 1 (přihlášen) vytvoří -2401
     *
     * SQL zápis pro "letošek" a základ role přihlášen: -(SUBSTR(YEAR(NOW()), -2) * 100 + 1);
     */
    private static function vytvorIdRoleUcasti(int $rok, int $zakladIdRole): int
    {
        return -(self::preProUcastRoku($rok) + $zakladIdRole);
    }

    public static function pritomenNaRocniku(int $rok): int
    {
        return self::vytvorIdRoleUcasti($rok, self::ROLE_PRITOMEN_ID_ZAKLAD);
    }

    public static function odjelZRocniku(int $rok): int
    {
        return self::vytvorIdRoleUcasti($rok, self::ROLE_ODJEL_ID_ZAKLAD);
    }

    public static function zkontrolovaneUdaje(int $rok): int
    {
        return self::idRocnikoveRole(self::ROLE_ZKONTROLOVANE_UDAJE_ID_ZAKLAD, $rok);
    }

    /**
     * Například z roku 2024 vytvoří 2400.
     *
     * SQL zápis pro "letošek" SUBSTR(YEAR(NOW()), -2) * 100;
     */
    private static function preProUcastRoku(int $rok): int
    {
        return ($rok - 2000) * 100; // předpona pro role a práva vázaná na daný rok
    }

    public static function LETOSNI_VYPRAVEC(int $rok = ROCNIK): int
    {
        return self::idRocnikoveRole(self::ROLE_VYPRAVEC_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_ZAZEMI(int $rok = ROCNIK): int
    {
        return self::idRocnikoveRole(self::ROLE_ZAZEMI_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_INFOPULT(int $rok = ROCNIK): int
    {
        return self::idRocnikoveRole(self::ROLE_INFOPULT_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_PARTNER(int $rok = ROCNIK): int
    {
        return self::idRocnikoveRole(self::ROLE_PARTNER_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_DOBROVOLNIK_SENIOR(int $rok = ROCNIK): int
    {
        return self::idRocnikoveRole(self::ROLE_DOBROVOLNIK_SENIOR_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_STREDECNI_NOC_ZDARMA(int $rok = ROCNIK): int
    {
        return self::idRocnikoveRole(self::ROLE_STREDECNI_NOC_ZDARMA_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_CTVRTECNI_NOC_ZDARMA(int $rok = ROCNIK): int
    {
        return self::idRocnikoveRole(self::ROLE_CTVRTECNI_NOC_ZDARMA_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_PATECNI_NOC_ZDARMA(int $rok = ROCNIK): int
    {
        return self::idRocnikoveRole(self::ROLE_PATECNI_NOC_ZDARMA_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_SOBOTNI_NOC_ZDARMA(int $rok = ROCNIK): int
    {
        return self::idRocnikoveRole(self::ROLE_SOBOTNI_NOC_ZDARMA_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_NEDELNI_NOC_ZDARMA(int $rok = ROCNIK): int
    {
        return self::idRocnikoveRole(self::ROLE_NEDELNI_NOC_ZDARMA_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_NEODHLASOVAT(int $rok = ROCNIK): int
    {
        return self::idRocnikoveRole(self::ROLE_NEODHLASOVAT_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_HERMAN(int $rok = ROCNIK): int
    {
        return self::idRocnikoveRole(self::ROLE_HERMAN_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_BRIGADNIK(int $rok = ROCNIK): int
    {
        return self::idRocnikoveRole(self::ROLE_BRIGADNIK_ID_ZAKLAD, $rok);
    }

    /**
     * SQL pro brigádníka
     * YEAR(NOW()) * -100000 - 25
     */
    public static function idRocnikoveRole(int $zakladIdRole, int $rok)
    {
        // 6, 2023 = -2 023 006
        return self::preProRocnikovouRoli($rok) - $zakladIdRole;
    }

    private static function preProRocnikovouRoli(int $rok): int
    {
        return $rok * self::KOEFICIENT_ROCNIKOVE_ROLE; // 2023 => 202300000
    }

    /**
     * @param int[] $idsRoli
     * @return bool
     */
    public static function obsahujiOrganizatora(array $idsRoli): bool
    {
        $idsRoliInt = array_map(static function ($idRole) {
            return (int)$idRole;
        }, $idsRoli);
        $maRole     = array_intersect($idsRoliInt, self::dejIdckaRoliSOrganizatory());

        return count($maRole) > 0;
    }

    /**
     * @return int[]
     */
    public static function dejIdckaRoliSOrganizatory(): array
    {
        return [self::ORGANIZATOR, self::PUL_ORG_BONUS_UBYTKO, self::PUL_ORG_BONUS_TRICKO, self::MINI_ORG];
    }

    public static function nazevRolePodleId(int $idRole): string
    {
        try {
            return match ($idRole) {
                self::ORGANIZATOR => 'Organizátor (zdarma)',
                self::VYPRAVECSKA_SKUPINA => 'Vypravěčská skupina',
                self::CESTNY_ORGANIZATOR => 'Čestný organizátor',
                self::PREZENCNI_ADMIN => 'Prezenční admin',
                self::CFO => 'CFO',
                self::PUL_ORG_BONUS_UBYTKO => 'Půl-org ubytkem',
                self::PUL_ORG_BONUS_TRICKO => 'Půl-org s tričkem',
                self::CLEN_RADY => 'Člen rady',
                self::SEF_INFOPULTU => 'Šéf infopultu',
                self::SEF_PROGRAMU => 'Šéf programu',
                self::MINI_ORG => 'Mini-org',
                //
                self::LETOSNI_VYPRAVEC => 'Vypravěč',
                self::LETOSNI_ZAZEMI => 'Zázemí',
                self::LETOSNI_INFOPULT => 'Infopult',
                self::LETOSNI_PARTNER => 'Partner',
                self::LETOSNI_DOBROVOLNIK_SENIOR => 'Dobrovolník senior',
                self::LETOSNI_STREDECNI_NOC_ZDARMA => 'Středeční noc zdarma',
                self::LETOSNI_CTVRTECNI_NOC_ZDARMA => 'Čtvrteční noc zdarma',
                self::LETOSNI_PATECNI_NOC_ZDARMA => 'Páteční noc zdarma',
                self::LETOSNI_SOBOTNI_NOC_ZDARMA => 'Sobotní noc zdarma',
                self::LETOSNI_NEDELNI_NOC_ZDARMA => 'Nedělní noc zdarma',
                self::LETOSNI_NEODHLASOVAT => 'Neodhlašovat',
                self::LETOSNI_HERMAN => 'Herman',
                self::LETOSNI_BRIGADNIK => 'Brigádník',
                //
                self::ZKONTROLOVANE_UDAJE_NA_LETOSNIM_GC => 'Zkontrolované údaje',
                //
                default => self::nazevRoleStareUcasti($idRole),
            };
        } catch (\LogicException $exception) {
            throw new \LogicException("Pro roli '$idRole' nemáme název.", 0, $exception);
        }
    }

    private static function nazevRoleStareUcasti(int $idRole): string
    {
        $rok     = self::rokDleRoleUcasti($idRole);
        $udalost = self::udalostDleRole($idRole);

        return "GC{$rok} {$udalost}";
    }

    public static function rokDleRoleUcasti(int $idRoleUcastiNagGc): int
    {
        if (self::jeToUcastNaGc($idRoleUcastiNagGc)) {
            return (int)(abs($idRoleUcastiNagGc) / 100) + 2000;
        }
        throw new \LogicException("Role (židle) s ID $idRoleUcastiNagGc není účast na GC");
    }

    public static function udalostDleRole(int $roleUcastiNagGc): string
    {
        if (!self::jeToUcastNaGc($roleUcastiNagGc)) {
            throw new \LogicException("Role (židle) s ID $roleUcastiNagGc v sobě nemá ročník a tím ani událost");
        }
        switch (abs($roleUcastiNagGc) % 100) {
            case 1:
                return self::UDALOST_PRIHLASEN;
            case 2:
                return self::UDALOST_PRITOMEN;
            case 3:
                return self::UDALOST_ODJEL;
            default:
                throw new \RuntimeException("Role (židle) s ID $roleUcastiNagGc v sobě má neznámou událost");
        }
    }

    /**
     * Rozmysli, zda není lepší použít čitelnější @see Role::TYP_UCAST
     */
    public static function jeToUcastNaGc(int $role): bool
    {
        return $role < 0 && $role > self::KOEFICIENT_ROCNIKOVE_ROLE;
    }

    /**
     * Rozmysli, zda není lepší použít čitelnější @see Role::TYP_ROCNIKOVA
     */
    public static function jeToRocnikovaRole(int $role): bool
    {
        return $role <= self::KOEFICIENT_ROCNIKOVE_ROLE;
    }

    public static function jeToRocnikovaOverovaciRole(int $role, int $rok): bool
    {
        return self::jeToRocnikovaRole($role)
            && in_array(
                $role,
                [
                    self::ZKONTROLOVANE_UDAJE_PRO_LETOSNI_GC($rok),
                ],
                true,
            );
    }

    public static function jePouzeProTentoRocnik(int $idRole, int $rocnik = ROCNIK): bool
    {
        if (!self::jeToUcastNaGc($idRole) && !self::jeToRocnikovaRole($idRole)) {
            return false;
        }

        return self::rokDleRoleUcasti($idRole) === $rocnik;
    }

    public static function vsechnyRoleUcastiProRocnik(int $rocnik = ROCNIK): array
    {
        return [
            Role::PRIHLASEN_NA_LETOSNI_GC => self::pridejGcRocnikPrefix($rocnik, 'přihlášen'),
            Role::PRITOMEN_NA_LETOSNIM_GC => self::pridejGcRocnikPrefix($rocnik, 'přítomen'),
            Role::ODJEL_Z_LETOSNIHO_GC    => self::pridejGcRocnikPrefix($rocnik, 'odjel'),
        ];
    }

    private static function pridejGcRocnikPrefix(int $rocnik, string $nazev): string
    {
        return self::prefixRocniku($rocnik) . ' ' . $nazev;
    }

    public static function vsechnyRocnikoveRole(int $rocnik = ROCNIK): array
    {
        $idckaRocnikovychRoli = [
            self::LETOSNI_VYPRAVEC($rocnik),
            self::LETOSNI_ZAZEMI($rocnik),
            self::LETOSNI_INFOPULT($rocnik),
            self::LETOSNI_PARTNER($rocnik),
            self::LETOSNI_DOBROVOLNIK_SENIOR($rocnik),
            self::LETOSNI_STREDECNI_NOC_ZDARMA($rocnik),
            self::LETOSNI_SOBOTNI_NOC_ZDARMA($rocnik),
            self::LETOSNI_NEDELNI_NOC_ZDARMA($rocnik),
            self::LETOSNI_NEODHLASOVAT($rocnik),
            self::LETOSNI_HERMAN($rocnik),
            self::LETOSNI_BRIGADNIK($rocnik),
            self::ZKONTROLOVANE_UDAJE_PRO_LETOSNI_GC($rocnik),
        ];
        $vsechnyRocnikoveRole = [];
        foreach ($idckaRocnikovychRoli as $id) {
            $vsechnyRocnikoveRole[$id] = self::nazevRolePodleId($id);
        }

        return $vsechnyRocnikoveRole;
    }

    public static function platiProRocnik(int $roleProRok, int $rocnik = ROCNIK): bool
    {
        return $roleProRok === self::JAKYKOLI_ROCNIK
            || self::platiPouzeProRocnik($roleProRok, $rocnik);
    }

    public static function platiPouzeProRocnik(int $roleProRok, int $rocnik = ROCNIK): bool
    {
        return $roleProRok === $rocnik;
    }

    public static function prefixRocniku(int $rocnik = ROCNIK): string
    {
        return 'GC' . $rocnik;
    }

    public function nazevRole(): ?string
    {
        return $this->r[RoleSqlStruktura::NAZEV_ROLE] ?? null;
    }

    public function kategorieRole(): ?int
    {
        $kategorie = $this->r[RoleSqlStruktura::KATEGORIE_ROLE] ?? null;

        return $kategorie === null
            ? null
            : (int)$kategorie;
    }
}
