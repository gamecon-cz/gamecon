<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Logger\LogHomadnychAkciTrait;
use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Uzivatel;

/**
 * Třída zodpovídající za automatické promlčení zůstatků uživatelů
 */
class PromlceniZustatku
{
    use LogHomadnychAkciTrait;

    private const ROK_NEPLATNOST = 3; // Počet let bez účasti, po kterých se zůstatek promlčí
    private const SKUPINA_PROMLCENI = 'promlceni-zustatku';

    public function __construct(
        private readonly SystemoveNastaveni $systemoveNastaveni,
    ) {
    }

    /**
     * Najde uživatele, jejichž zůstatek má být promlčen
     * (pozitivní zůstatek + neúčast na GC po dobu ROK_NEPLATNOST let)
     *
     * @return UzivatelKPromlceni[] Pole uživatelů určených k promlčení
     */
    public function najdiUzivateleKPromlceni(): array
    {
        $aktualniRocnik = $this->systemoveNastaveni->rocnik();
        $ucastDoRoku    = $aktualniRocnik - self::ROK_NEPLATNOST;

        $ucast     = Role::TYP_UCAST;
        $prihlasen = Role::VYZNAM_PRIHLASEN;

        $result = dbQuery(<<<SQL
SELECT
    uzivatele_hodnoty.id_uzivatele AS uzivatel,
    uzivatele_hodnoty.jmeno_uzivatele AS jmeno,
    uzivatele_hodnoty.prijmeni_uzivatele AS prijmeni,
    uzivatele_hodnoty.email1_uzivatele AS email,
    uzivatele_hodnoty.telefon_uzivatele AS telefon,
    uzivatele_hodnoty.zustatek,
    prihlaseni.roky AS prihlaseniNaRocniky,
    kladny_pohyb.cas_posledni_platby AS kladny_pohyb,
    kladny_pohyb.rok_posledni_platby,
    kladny_pohyb.mesic_posledni_platby,
    kladny_pohyb.den_posledni_platby
FROM uzivatele_hodnoty
LEFT JOIN (
    SELECT id_uzivatele,
           GROUP_CONCAT(role.rocnik_role ORDER BY role.rocnik_role ASC SEPARATOR ';') AS roky,
    COUNT(*) AS pocet
    FROM platne_role_uzivatelu
    JOIN role_seznam AS role ON platne_role_uzivatelu.id_role = role.id_role
    WHERE role.typ_role = '$ucast' AND role.vyznam_role = '$prihlasen'
    GROUP BY id_uzivatele
) AS prihlaseni ON prihlaseni.id_uzivatele = uzivatele_hodnoty.id_uzivatele
LEFT JOIN (
    SELECT
        id_uzivatele,
        MAX(provedeno) AS cas_posledni_platby,
        YEAR(MAX(provedeno)) AS rok_posledni_platby,
        MONTH(MAX(provedeno)) AS mesic_posledni_platby,
        DAY(MAX(provedeno)) AS den_posledni_platby
    FROM platby
    WHERE castka > 0
    GROUP BY id_uzivatele
) AS kladny_pohyb ON kladny_pohyb.id_uzivatele = uzivatele_hodnoty.id_uzivatele
WHERE
    uzivatele_hodnoty.zustatek > 0
    AND (EXISTS(
            SELECT 1
            FROM platne_role_uzivatelu
            JOIN role_seznam AS role ON platne_role_uzivatelu.id_role = role.id_role
            WHERE platne_role_uzivatelu.id_uzivatele = uzivatele_hodnoty.id_uzivatele
                AND role.typ_role = '$ucast'
                AND role.vyznam_role = '$prihlasen'
            HAVING MAX(role.rocnik_role) <= $ucastDoRoku
    )
        OR NOT EXISTS (
            SELECT 1
            FROM platne_role_uzivatelu
            JOIN role_seznam AS role ON platne_role_uzivatelu.id_role = role.id_role
            WHERE platne_role_uzivatelu.id_uzivatele = uzivatele_hodnoty.id_uzivatele
                AND role.typ_role = '$ucast'
                AND role.vyznam_role = '$prihlasen'
        )
    )
SQL,
        );

        $uzivatele = [];
        while ($r = mysqli_fetch_assoc($result)) {
            $uzivatel = Uzivatel::zId($r['uzivatel']);

            $uzivatele[] = new UzivatelKPromlceni(
                uzivatel: $uzivatel,
                prihlaseniNaRocniky: $r['prihlaseniNaRocniky'] ?? '',
                kladnyPohyb: $r['kladny_pohyb'] ?? null,
                rokPosledniPlatby: isset($r['rok_posledni_platby']) ? (int)$r['rok_posledni_platby'] : null,
                mesicPosledniPlatby: isset($r['mesic_posledni_platby']) ? (int)$r['mesic_posledni_platby'] : null,
                denPosledniPlatby: isset($r['den_posledni_platby']) ? (int)$r['den_posledni_platby'] : null,
            );
        }

        return $uzivatele;
    }

    /**
     * Promlčí zůstatky pro zadané ID uživatelů
     *
     * @param int[] $idsUzivatelu
     * @param int $idAdmina ID administrátora provádějícího promlčení (nebo Uzivatel::SYSTEM pro automatické)
     * @return array ['pocet' => int, 'suma' => float] Počet promlčených uživatelů a celková suma
     */
    public function promlcZustatky(array $idsUzivatelu, int $idAdmina): array
    {
        $pocet = 0;
        $suma  = 0;

        foreach ($idsUzivatelu as $id) {
            $odpoved = dbOneLine('
                SELECT id_uzivatele, zustatek
                FROM uzivatele_hodnoty
                WHERE id_uzivatele = $0
            ', [$id]);

            if (!$odpoved) {
                continue;
            }

            $zustatek = $odpoved['zustatek'];
            $suma     += $zustatek;

            dbQuery('UPDATE uzivatele_hodnoty SET zustatek = 0 WHERE id_uzivatele = $0', [$id]);

            // Logování
            $soubor = LOGY . '/promlceni-' . date('Y-m-d_H-i-s') . '.log';
            $cas    = date('Y-m-d H:i:s');
            $zprava = "Promlčení provedl admin s id:          $idAdmina";
            file_put_contents($soubor, "$cas $zprava\n", FILE_APPEND);
            $zprava = "Promlčení zůstatku pro uživatele s id: $id";
            file_put_contents($soubor, "$cas $zprava\n", FILE_APPEND);
            $zprava = "Promlčená částka:                      $zustatek Kč" . "\n";
            file_put_contents($soubor, "$cas $zprava\n", FILE_APPEND);

            $pocet++;
        }

        return ['pocet' => $pocet, 'suma' => $suma];
    }

    /**
     * Vytvoří data pro CFO report s informacemi o promlčených zůstatcích
     *
     * @param UzivatelKPromlceni[] $uzivatele Pole uživatelů určených k promlčení
     * @return array[] Data připravená pro Report::zPole()
     */
    public function vytvorCfoReport(array $uzivatele): array
    {
        $aktualniRocnik = $this->systemoveNastaveni->rocnik();
        $maxRok         = $this->systemoveNastaveni->poPrihlasovaniUcastniku()
            ? $aktualniRocnik
            : $aktualniRocnik - 1;

        $ucastPodleRoku = [];
        for ($rokUcasti = 2009; $rokUcasti <= $maxRok; $rokUcasti++) {
            $ucastPodleRoku[Role::pritomenNaRocniku($rokUcasti)] = 'účast ' . $rokUcasti;
        }

        $obsah = [];
        foreach ($uzivatele as $uzivatelKPromlceni) {
            $ucastiHistorie = [];
            $idsRoliUcastnika = $uzivatelKPromlceni->prihlaseniNaRocniky
                ? explode(';', $uzivatelKPromlceni->prihlaseniNaRocniky)
                : [];

            foreach ($ucastPodleRoku as $nazevUcasti) {
                $rocnik = (int)str_replace('účast ', '', $nazevUcasti);
                $ucastiHistorie[$nazevUcasti] = in_array((string)$rocnik, $idsRoliUcastnika, true)
                    ? 'ano'
                    : 'ne';
            }

            $obsah[] = [
                'id_uzivatele'          => $uzivatelKPromlceni->uzivatel->id(),
                'nick'                  => $uzivatelKPromlceni->uzivatel->login(),
                'jmeno'                 => $uzivatelKPromlceni->uzivatel->krestniJmeno(),
                'prijmeni'              => $uzivatelKPromlceni->uzivatel->prijmeni(),
                'email'                 => $uzivatelKPromlceni->uzivatel->mail(),
                ...$ucastiHistorie,
                'rok_posledni_platby'   => $uzivatelKPromlceni->rokPosledniPlatby,
                'mesic_posledni_platby' => $uzivatelKPromlceni->mesicPosledniPlatby,
                'den_posledni_platby'   => $uzivatelKPromlceni->denPosledniPlatby,
                'promlcena_castka'      => $uzivatelKPromlceni->uzivatel->finance()->stav(),
            ];
        }

        return $obsah;
    }

    /**
     * Vrátí počet let bez účasti, po kterých se zůstatek promlčí
     */
    public static function getPocetLetNeplatnosti(): int
    {
        return self::ROK_NEPLATNOST;
    }

    /**
     * Zjistí, zda už bylo pro daný ročník odesláno varovné upozornění (1 měsíc před)
     */
    public function varovaniMesicOdeslanoKdy(int $rocnik): ?\DateTimeInterface
    {
        return $this->posledniHromadnaAkceKdy(
            self::SKUPINA_PROMLCENI,
            $this->nazevAkceVarovaniMesic($rocnik),
        );
    }

    /**
     * Zjistí, zda už bylo pro daný ročník odesláno varovné upozornění (1 týden před)
     */
    public function varovaniTydenOdeslanoKdy(int $rocnik): ?\DateTimeInterface
    {
        return $this->posledniHromadnaAkceKdy(
            self::SKUPINA_PROMLCENI,
            $this->nazevAkceVarovaniTyden($rocnik),
        );
    }

    /**
     * Zjistí, zda už bylo pro daný ročník provedeno automatické promlčení
     */
    public function automatickaPromlceniProvedenaKdy(int $rocnik): ?\DateTimeInterface
    {
        return $this->posledniHromadnaAkceKdy(
            self::SKUPINA_PROMLCENI,
            $this->nazevAkceAutomatickePromlceni($rocnik),
        );
    }

    /**
     * Zaloguje odeslání varovného e-mailu (1 měsíc před)
     */
    public function zalogujVarovaniMesic(int $rocnik, int $pocetEmailu): void
    {
        $this->zalogujHromadnouAkci(
            self::SKUPINA_PROMLCENI,
            $this->nazevAkceVarovaniMesic($rocnik),
            $pocetEmailu,
            Uzivatel::zId(Uzivatel::SYSTEM, true),
        );
    }

    /**
     * Zaloguje odeslání varovného e-mailu (1 týden před)
     */
    public function zalogujVarovaniTyden(int $rocnik, int $pocetEmailu): void
    {
        $this->zalogujHromadnouAkci(
            self::SKUPINA_PROMLCENI,
            $this->nazevAkceVarovaniTyden($rocnik),
            $pocetEmailu,
            Uzivatel::zId(Uzivatel::SYSTEM, true),
        );
    }

    /**
     * Zaloguje provedení automatického promlčení
     */
    public function zalogujAutomatickePromlceni(int $rocnik, int $pocetUzivatelu, float $celkovaSuma): void
    {
        $this->zalogujHromadnouAkci(
            self::SKUPINA_PROMLCENI,
            $this->nazevAkceAutomatickePromlceni($rocnik),
            "$pocetUzivatelu uživatelů, $celkovaSuma Kč",
            Uzivatel::zId(Uzivatel::SYSTEM, true),
        );
    }

    private function nazevAkceVarovaniMesic(int $rocnik): string
    {
        return "varovani-mesic-$rocnik";
    }

    private function nazevAkceVarovaniTyden(int $rocnik): string
    {
        return "varovani-tyden-$rocnik";
    }

    private function nazevAkceAutomatickePromlceni(int $rocnik): string
    {
        return "automaticke-promlceni-$rocnik";
    }
}
