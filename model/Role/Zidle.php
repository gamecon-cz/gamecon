<?php declare(strict_types=1);

namespace Gamecon\Role;

/**
 * @method static Zidle zId($id)
 */
class Zidle extends \DbObject
{

    protected static $tabulka = 'r_zidle_soupis';
    protected static $pk = 'id_zidle';

    /**
     * Konstanty jsou kopie SQL tabulky `r_zidle_soupis`
     */
    // TRVALÉ ŽIDLE
    public const ORGANIZATOR            = ZIDLE_ORGANIZATOR; // Organizátor (zdarma), Člen organizačního týmu GC
    public const ORGANIZATOR_S_BONUSY_1 = ZIDLE_ORGANIZATOR_S_BONUSY_1;
    public const ORGANIZATOR_S_BONUSY_2 = ZIDLE_ORGANIZATOR_S_BONUSY_2;
    public const CESTNY_ORGANIZATOR     = ZIDLE_CESTNY_ORGANIZATOR; // Bývalý organizátor GC
    public const SPRAVCE_FINANCI_GC     = ZIDLE_SPRAVCE_FINANCI_GC; // Organizátor, který může nakládat s financemi GC
    public const ADMIN                  = ZIDLE_ADMIN; // Spec. židle pro úpravy databáze. NEPOUŽÍVAT.
    public const VYPRAVECSKA_SKUPINA    = ZIDLE_VYPRAVECSKA_SKUPINA; // Organizátorská skupina pořádající na GC (dodavatelé, …)

    // DOČASNÉ ROČNÍKOVÉ ŽIDLE
    public const LETOSNI_VYPRAVEC             = ZIDLE_VYPRAVEC; // Organizátor aktivit na GC
    public const LETOSNI_ZAZEMI               = ZIDLE_ZAZEMI; // Členové zázemí GC (kuchařky, …)
    public const LETOSNI_INFOPULT             = ZIDLE_INFOPULT; // Operátor infopultu
    public const LETOSNI_PARTNER              = ZIDLE_PARTNER; // Vystavovatelé, lidé od deskovek, atp.
    public const LETOSNI_DOBROVOLNIK_SENIOR   = ZIDLE_DOBROVOLNIK_SENIOR; // Dobrovolník dlouhodobě spolupracující s GC
    public const LETOSNI_STREDECNI_NOC_ZDARMA = ZIDLE_STREDECNI_NOC_ZDARMA;
    public const LETOSNI_NEDELNI_NOC_ZDARMA   = ZIDLE_NEDELNI_NOC_ZDARMA;
    public const LETOSNI_NEODHLASOVAT         = ZIDLE_NEODHLASOVAT;
    public const LETOSNI_HERMAN               = ZIDLE_HERMAN;
    public const LETOSNI_BRIGADNIK            = ZIDLE_BRIGADNIK;

    protected const ZIDLE_VYPRAVEC_ID_ZAKLAD             = 6;
    protected const ZIDLE_ZAZEMI_ID_ZAKLAD               = 7;
    protected const ZIDLE_INFOPULT_ID_ZAKLAD             = 8;
    protected const ZIDLE_PARTNER_ID_ZAKLAD              = 13;
    protected const ZIDLE_DOBROVOLNIK_SENIOR_ID_ZAKLAD   = 17;
    protected const ZIDLE_STREDECNI_NOC_ZDARMA_ID_ZAKLAD = 18;
    protected const ZIDLE_NEDELNI_NOC_ZDARMA_ID_ZAKLAD   = 19;
    protected const ZIDLE_NEODHLASOVAT_ID_ZAKLAD         = 23;
    protected const ZIDLE_HERMAN_ID_ZAKLAD               = 24;
    protected const ZIDLE_BRIGADNIK_ID_ZAKLAD            = 25;

    // ŽIDLE ÚČASTI
    public const PRIHLASEN_NA_LETOSNI_GC = ZIDLE_PRIHLASEN;
    public const PRITOMEN_NA_LETOSNIM_GC = ZIDLE_PRITOMEN;
    public const ODJEL_Z_LETOSNIHO_GC    = ZIDLE_ODJEL;

    protected const ZIDLE_PRIHLASEN_ID_ZAKLAD = 1;
    protected const ZIDLE_PRITOMEN_ID_ZAKLAD  = 2;
    protected const ZIDLE_ODJEL_ID_ZAKLAD     = 3;

    public const UDALOST_PRIHLASEN = 'přihlášen';
    public const UDALOST_PRITOMEN  = 'přítomen';
    public const UDALOST_ODJEL     = 'odjel';

    public const JAKYKOLI_ROK               = -1;
    public const KOEFICIENT_ROCNIKOVE_ZIDLE = -100000;

    public const TYP_ROCNIKOVA = 'rocnikova';
    public const TYP_UCAST     = 'ucast';
    public const TYP_TRVALA    = 'trvala';

    // TYP TRVALE
    public const VYZNAM_ORGANIZATOR_ZDARMA     = 'ORGANIZATOR_ZDARMA';
    public const VYZNAM_ORGANIZATOR_S_BONUSY_1 = 'ORGANIZATOR_S_BONUSY_1';
    public const VYZNAM_ORGANIZATOR_S_BONUSY_2 = 'ORGANIZATOR_S_BONUSY_2';
    public const VYZNAM_CESTNY_ORGANIZATOR     = 'CESTNY_ORGANIZATOR';
    public const VYZNAM_SPRAVCE_FINANCI_GC     = 'SPRAVCE_FINANCI_GC';
    public const VYZNAM_ADMIN                  = 'ADMIN';
    public const VYZNAM_VYPRAVECSKA_SKUPINA    = 'VYPRAVECSKA_SKUPINA';
    // TYP ROCNIKOVE
    public const VYZNAM_BRIGADNIK            = 'BRIGADNIK';
    public const VYZNAM_DOBROVOLNIK_SENIOR   = 'DOBROVOLNIK_SENIOR';
    public const VYZNAM_HERMAN               = 'HERMAN';
    public const VYZNAM_INFOPULT             = 'INFOPULT';
    public const VYZNAM_NEDELNI_NOC_ZDARMA   = 'NEDELNI_NOC_ZDARMA';
    public const VYZNAM_NEODHLASOVAT         = 'NEODHLASOVAT';
    public const VYZNAM_PARTNER              = 'PARTNER';
    public const VYZNAM_STREDECNI_NOC_ZDARMA = 'STREDECNI_NOC_ZDARMA';
    public const VYZNAM_VYPRAVEC             = 'VYPRAVEC';
    public const VYZNAM_ZAZEMI               = 'ZAZEMI';
    // TYP UCAST
    public const VYZNAM_PRIHLASEN = 'PRIHLASEN';
    public const VYZNAM_PRITOMEN  = 'PRITOMEN';
    public const VYZNAM_ODJEL     = 'ODJEL';

    /**
     * @return int[]
     */
    public static function idckaTrvalychZidli(): array {
        return [
            Zidle::ORGANIZATOR,
            Zidle::ORGANIZATOR_S_BONUSY_1,
            Zidle::ORGANIZATOR_S_BONUSY_2,
            Zidle::CESTNY_ORGANIZATOR,
            Zidle::SPRAVCE_FINANCI_GC,
            Zidle::ADMIN,
            Zidle::VYPRAVECSKA_SKUPINA,
        ];
    }

    public static function vyznamPodleKodu(string $kodZidle): string {
        return preg_replace('~^GC\d+_~', '', $kodZidle);
    }

    /**
     * Přihlásil se, neboli registroval, na GameCon
     * @param int $rok
     * @return int
     */
    public static function PRIHLASEN_NA_LETOSNI_GC(int $rok = ROK): int {
        return self::preProUcastRoku($rok) - self::ZIDLE_PRIHLASEN_ID_ZAKLAD;
    }

    /**
     * Prošel infopultem a byl na GameConu
     * @param int $rok
     * @return int
     */
    public static function PRITOMEN_NA_LETOSNIM_GC(int $rok = ROK): int {
        return self::preProUcastRoku($rok) - self::ZIDLE_PRITOMEN_ID_ZAKLAD;
    }

    /**
     * Prošel infopultem na odchodu a odjel z GameConu
     * @param int $rok
     * @return int
     */
    public static function ODJEL_Z_LETOSNIHO_GC(int $rok = ROK): int {
        return self::preProUcastRoku($rok) - self::ZIDLE_ODJEL_ID_ZAKLAD;
    }

    private static function preProUcastRoku(int $rok): int {
        return -($rok - 2000) * 100; // předpona pro židle a práva vázaná na daný rok
    }

    public static function LETOSNI_VYPRAVEC(int $rok = ROK): int {
        return self::idRocnikoveZidle(self::ZIDLE_VYPRAVEC_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_ZAZEMI(int $rok = ROK): int {
        return self::idRocnikoveZidle(self::ZIDLE_ZAZEMI_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_INFOPULT(int $rok = ROK): int {
        return self::idRocnikoveZidle(self::ZIDLE_INFOPULT_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_PARTNER(int $rok = ROK): int {
        return self::idRocnikoveZidle(self::ZIDLE_PARTNER_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_DOBROVOLNIK_SENIOR(int $rok = ROK): int {
        return self::idRocnikoveZidle(self::ZIDLE_DOBROVOLNIK_SENIOR_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_STREDECNI_NOC_ZDARMA(int $rok = ROK): int {
        return self::idRocnikoveZidle(self::ZIDLE_STREDECNI_NOC_ZDARMA_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_NEDELNI_NOC_ZDARMA(int $rok = ROK): int {
        return self::idRocnikoveZidle(self::ZIDLE_NEDELNI_NOC_ZDARMA_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_NEODHLASOVAT(int $rok = ROK): int {
        return self::idRocnikoveZidle(self::ZIDLE_NEODHLASOVAT_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_HERMAN(int $rok = ROK): int {
        return self::idRocnikoveZidle(self::ZIDLE_HERMAN_ID_ZAKLAD, $rok);
    }

    public static function LETOSNI_BRIGADNIK(int $rok = ROK): int {
        return self::idRocnikoveZidle(self::ZIDLE_BRIGADNIK_ID_ZAKLAD, $rok);
    }

    public static function idRocnikoveZidle(int $zakladIdZidle, int $rok) {
        // 6, 2023 = -2 023 006
        return self::preProRocnikovouZidli($rok) - $zakladIdZidle;
    }

    private static function preProRocnikovouZidli(int $rok): int {
        return $rok * self::KOEFICIENT_ROCNIKOVE_ZIDLE; // 2023 = 202 300 000
    }

    /**
     * @param int[] $idsZidli
     * @return bool
     */
    public static function obsahujiOrganizatora(array $idsZidli): bool {
        $idZidli = array_map(static function ($idZidle) {
            return (int)$idZidle;
        }, $idsZidli);
        $maZidle = array_intersect($idZidli, self::dejIdckaZidliSOrganizatory());
        return count($maZidle) > 0;
    }

    /**
     * @return int[]
     */
    public static function dejIdckaZidliSOrganizatory(): array {
        return [self::ORGANIZATOR, self::ORGANIZATOR_S_BONUSY_1, self::ORGANIZATOR_S_BONUSY_2];
    }

    public static function nazevZidle(int $idZidle): string {
        return match ($idZidle) {
            self::ORGANIZATOR => 'Organizátor (zdarma)',
            self::LETOSNI_VYPRAVEC => 'Vypravěč',
            self::LETOSNI_ZAZEMI => 'Zázemí',
            self::LETOSNI_INFOPULT => 'Infopult',
            self::VYPRAVECSKA_SKUPINA => 'Vypravěčská skupina',
            self::LETOSNI_PARTNER => 'Partner',
            self::CESTNY_ORGANIZATOR => 'Čestný organizátor',
            self::ADMIN => 'Admin',
            self::LETOSNI_DOBROVOLNIK_SENIOR => 'Dobrovolník senior',
            self::LETOSNI_STREDECNI_NOC_ZDARMA => 'Středeční noc zdarma',
            self::LETOSNI_NEDELNI_NOC_ZDARMA => 'Nedělní noc zdarma',
            self::SPRAVCE_FINANCI_GC => 'Správce financí GC',
            self::ORGANIZATOR_S_BONUSY_1 => 'Organizátor (s bonusy 1)',
            self::ORGANIZATOR_S_BONUSY_2 => 'Organizátor (s bonusy 2)',
            self::LETOSNI_NEODHLASOVAT => 'Neodhlašovat',
            self::LETOSNI_HERMAN => 'Herman',
            self::LETOSNI_BRIGADNIK => 'Brigádník',
            default => self::nazevZidleStareUcasti($idZidle),
        };
    }

    private static function nazevZidleStareUcasti(int $idZidle): string {
        $rok     = self::rokDleZidleUcasti($idZidle);
        $udalost = self::udalostDleZidle($idZidle);
        return "GC{$rok} {$udalost}";
    }

    public static function rokDleZidleUcasti(int $idZidleUcastiNagGc): int {
        if (self::jeToUcastNaGc($idZidleUcastiNagGc)) {
            return (int)(abs($idZidleUcastiNagGc) / 100) + 2000;
        }
        throw new \LogicException("Role (židle) s ID $idZidleUcastiNagGc není účast na GC");
    }

    public static function udalostDleZidle(int $zidleUcastiNagGc): string {
        if (!self::jeToUcastNaGc($zidleUcastiNagGc)) {
            throw new \LogicException("Role (židle) s ID $zidleUcastiNagGc v sobě nemá ročník a tím ani událost");
        }
        switch (abs($zidleUcastiNagGc) % 100) {
            case 1 :
                return self::UDALOST_PRIHLASEN;
            case 2 :
                return self::UDALOST_PRITOMEN;
            case 3 :
                return self::UDALOST_ODJEL;
            default :
                throw new \RuntimeException("Role (židle) s ID $zidleUcastiNagGc v sobě má neznámou událost");
        }
    }

    /**
     * Rozmysli, zda není lepší použít čitelnější @see \Gamecon\Role\Zidle::TYP_UCAST
     */
    public static function jeToUcastNaGc(int $zidle): bool {
        return $zidle < 0 && $zidle > self::KOEFICIENT_ROCNIKOVE_ZIDLE;
    }

    /**
     * Rozmysli, zda není lepší použít čitelnější @see \Gamecon\Role\Zidle::TYP_ROCNIKOVA
     */
    public static function jeToRocnikovaZidle(int $zidle): bool {
        return $zidle <= self::KOEFICIENT_ROCNIKOVE_ZIDLE;
    }

    public static function jePouzeProTentoRocnik(int $idZidle, int $rocnik = ROK): bool {
        if (!self::jeToUcastNaGc($idZidle) && !self::jeToRocnikovaZidle($idZidle)) {
            return false;
        }
        return self::rokDleZidleUcasti($idZidle) === $rocnik;
    }

    public static function vsechnyZidleUcastiProRocnik(int $rocnik = ROK): array {
        return [
            Zidle::PRIHLASEN_NA_LETOSNI_GC => self::pridejGcRocnikPrefix($rocnik, 'přihlášen'),
            Zidle::PRITOMEN_NA_LETOSNIM_GC => self::pridejGcRocnikPrefix($rocnik, 'přítomen'),
            Zidle::ODJEL_Z_LETOSNIHO_GC    => self::pridejGcRocnikPrefix($rocnik, 'odjel'),
        ];
    }

    private static function pridejGcRocnikPrefix(int $rocnik, string $nazev): string {
        return self::prefixRocniku($rocnik) . ' ' . $nazev;
    }

    public static function vsechnyRocnikoveZidle(int $rocnik = ROK): array {
        $idckaRocnikovychZidli = [
            self::LETOSNI_VYPRAVEC($rocnik),
            self::LETOSNI_ZAZEMI($rocnik),
            self::LETOSNI_INFOPULT($rocnik),
            self::LETOSNI_PARTNER($rocnik),
            self::LETOSNI_DOBROVOLNIK_SENIOR($rocnik),
            self::LETOSNI_STREDECNI_NOC_ZDARMA($rocnik),
            self::LETOSNI_NEDELNI_NOC_ZDARMA($rocnik),
            self::LETOSNI_NEODHLASOVAT($rocnik),
            self::LETOSNI_HERMAN($rocnik),
            self::LETOSNI_BRIGADNIK($rocnik),
        ];
        $vsechnyRocnikoveZidle = [];
        foreach ($idckaRocnikovychZidli as $id) {
            $vsechnyRocnikoveZidle[$id] = self::nazevZidle($id);
        }
        return $vsechnyRocnikoveZidle;
    }

    public function jmenoZidle(): ?string {
        return $this->r['jmeno_zidle'] ?? null;
    }

    public static function platiProRocnik(int $zidleProRok, int $rocnik = ROK): bool {
        return $zidleProRok === self::JAKYKOLI_ROK
            || self::platiPouzeProRocnik($zidleProRok, $rocnik);
    }

    public static function platiPouzeProRocnik(int $zidleProRok, int $rocnik = ROK): bool {
        return $zidleProRok === $rocnik;
    }

    public static function prefixRocniku(int $rocnik = ROK): string {
        return 'GC' . $rocnik;
    }
}
