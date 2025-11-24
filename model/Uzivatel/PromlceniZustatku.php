<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Kanaly\GcMail;
use Gamecon\Logger\JobResultLogger;
use Gamecon\Logger\LogHomadnychAkciTrait;
use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Enum\TypVarovaniPromlceni;
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
        private readonly JobResultLogger $jobResultLogger,
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
    uzivatele_hodnoty.id_uzivatele,
    prihlaseni.roky AS prihlaseniNaRocniky,
    kladny_pohyb.rokPosledniPlatby,
    kladny_pohyb.mesicPosledniPlatby,
    kladny_pohyb.denPosledniPlatby
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
        YEAR(MAX(provedeno)) AS rokPosledniPlatby,
        MONTH(MAX(provedeno)) AS mesicPosledniPlatby,
        DAY(MAX(provedeno)) AS denPosledniPlatby
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
            $uzivatel = Uzivatel::zId($r['id_uzivatele']);

            $uzivatele[] = new UzivatelKPromlceni(
                uzivatel: $uzivatel,
                prihlaseniNaRocniky: $r['prihlaseniNaRocniky'] ?? '',
                rokPosledniPlatby: isset($r['rokPosledniPlatby']) ? (int)$r['rokPosledniPlatby'] : null,
                mesicPosledniPlatby: isset($r['mesicPosledniPlatby']) ? (int)$r['mesicPosledniPlatby'] : null,
                denPosledniPlatby: isset($r['denPosledniPlatby']) ? (int)$r['denPosledniPlatby'] : null,
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
        $maxRok         = $this->systemoveNastaveni->poPrihlasovaniUcastniku($aktualniRocnik)
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

    /**
     * Odešle varovné e-maily uživatelům o promlčení zůstatků
     *
     * @param TypVarovaniPromlceni $typVarovani Typ varovného e-mailu (měsíc/týden před registrací)
     * @param bool $znovu Zda má být varovný e-mail odeslán znovu i když už byl odeslán
     * @return int Počet odeslaných e-mailů, nebo -1 pokud se odeslání nespustilo
     */
    public function odesliVarovneEmaily(TypVarovaniPromlceni $typVarovani, bool $znovu = false): int
    {
        $rocnik = $this->systemoveNastaveni->rocnik();
        $regGcOd = $this->systemoveNastaveni->prihlasovaniUcastnikuOd($rocnik);

        // Zkontroluj, jestli je správný čas
        $casovyOffset = match ($typVarovani) {
            TypVarovaniPromlceni::MESIC => '-1 month',
            TypVarovaniPromlceni::TYDEN => '-1 week',
        };
        $ocekavanyTermin = (clone $regGcOd)->modify($casovyOffset);
        $ted = $this->systemoveNastaveni->ted();

        // Spustit pouze pokud jsme v rozmezí (s tolerancí 23 hodin)
        $jeSpravnyCas = match ($typVarovani) {
            // Pro měsíc: příliš brzy NEBO příliš pozdě
            TypVarovaniPromlceni::MESIC => $ted <= (clone $ocekavanyTermin)->modify('+23 hours') && $ted >= $ocekavanyTermin,
            // Pro týden: standardní rozmezí
            TypVarovaniPromlceni::TYDEN => $ted >= $ocekavanyTermin && $ted <= (clone $ocekavanyTermin)->modify('+23 hours'),
        };

        if (!$jeSpravnyCas) {
            $nazev = $this->dejNazevVarovani($typVarovani);
            $this->jobResultLogger->logs("Varovné e-maily o promlčení ($nazev): Není správný čas. Očekáváno: " . $ocekavanyTermin->format('Y-m-d H:i:s') . ', ted: ' . $ted->format('Y-m-d H:i:s'));
            return -1;
        }

        // Zkontroluj, jestli už nebyly e-maily odeslány
        $jizOdeslano = match ($typVarovani) {
            TypVarovaniPromlceni::MESIC => $this->varovaniMesicOdeslanoKdy($rocnik),
            TypVarovaniPromlceni::TYDEN => $this->varovaniTydenOdeslanoKdy($rocnik),
        };

        if ($jizOdeslano && !$znovu) {
            $nazev = $this->dejNazevVarovani($typVarovani);
            $this->jobResultLogger->logs("Varovné e-maily o promlčení ($nazev): E-maily už byly odeslány pro rocnik $rocnik");
            return -1;
        }

        $uzivatele = $this->najdiUzivateleKPromlceni();

        if (count($uzivatele) === 0) {
            $nazev = $this->dejNazevVarovani($typVarovani);
            $this->jobResultLogger->logs("Varovné e-maily o promlčení ($nazev): Žádní uživatelé k varování");
            return -1;
        }

        $pocetLet = self::ROK_NEPLATNOST;
        $pocetOdeslanychEmailu = 0;

        foreach ($uzivatele as $uzivatelKPromlceni) {
            $uzivatel = $uzivatelKPromlceni->uzivatel;
            if (!$uzivatel->mail()) {
                continue;
            }

            $zustatek = (int)$uzivatel->finance()->stav();
            $jmeno = $uzivatel->jmenoNick();

            $predmet = $this->dejEmailPredmet($typVarovani, $rocnik, $zustatek);
            $zprava = $this->dejEmailZpravu($typVarovani, $rocnik, $jmeno, $zustatek, $pocetLet, $regGcOd, $uzivatel);

            (new GcMail($this->systemoveNastaveni))
                ->adresat($uzivatel->mail())
                ->predmet($predmet)
                ->text($zprava)
                ->odeslat(GcMail::FORMAT_TEXT);

            $pocetOdeslanychEmailu++;
            set_time_limit(10); // Prodloužit timeout pro každý e-mail
        }

        // Zaloguj odeslání do databáze
        match ($typVarovani) {
            TypVarovaniPromlceni::MESIC => $this->zalogujVarovaniMesic($rocnik, $pocetOdeslanychEmailu),
            TypVarovaniPromlceni::TYDEN => $this->zalogujVarovaniTyden($rocnik, $pocetOdeslanychEmailu),
        };

        // Poslat CFO informaci o počtu odeslaných e-mailů
        $this->odesliInfoCfo($typVarovani, $rocnik, $pocetOdeslanychEmailu, $pocetLet, $regGcOd);

        $nazev = $this->dejNazevVarovani($typVarovani);
        $this->jobResultLogger->logs("Varovné e-maily o promlčení ($nazev): Odesláno $pocetOdeslanychEmailu e-mailů");

        return $pocetOdeslanychEmailu;
    }

    private function dejNazevVarovani(TypVarovaniPromlceni $typVarovani): string
    {
        return match ($typVarovani) {
            TypVarovaniPromlceni::MESIC => '1 měsíc',
            TypVarovaniPromlceni::TYDEN => '1 týden',
        };
    }

    private function dejEmailPredmet(TypVarovaniPromlceni $typVarovani, int $rocnik, int $zustatek): string
    {
        return match ($typVarovani) {
            TypVarovaniPromlceni::MESIC => "GameCon $rocnik - Tvůj zůstatek {$zustatek} Kč bude promlčen",
            TypVarovaniPromlceni::TYDEN => "PŘIPOMÍNKA: GameCon $rocnik - Tvůj zůstatek {$zustatek} Kč bude promlčen",
        };
    }

    private function dejEmailZpravu(
        TypVarovaniPromlceni $typVarovani,
        int $rocnik,
        string $jmeno,
        int $zustatek,
        int $pocetLet,
        \DateTimeInterface $regGcOd,
        \Uzivatel $uzivatel,
    ): string {
        // Formátování počtu let
        $pattern = <<<ICU
        {pocetLet, plural,
            one {poslední # rok}
            few {poslední # roky}
            other {posledních # let}
        }
        ICU;
        $formatter = new \MessageFormatter('cs_CZ', $pattern);
        $posledniRoky = trim($formatter->format(['pocetLet' => $pocetLet]));
        $a = $uzivatel->koncovkaDlePohlavi();

        return match ($typVarovani) {
            TypVarovaniPromlceni::MESIC => <<<TEXT
Ahoj $jmeno,

máš na GameCon účtu zůstatek $zustatek Kč, ale {$posledniRoky} jsi se GameConu nezúčastnil{$a}.

Hrozí promlčení zůstatku na Tvém GameCon účtu kvůli tvé neaktivitě.

Zůstatek bude promlčen krátce po skončení letošního GameConu $rocnik.

Co můžeš proti tomu udělat:
- Registrovat se na letošní GameCon $rocnik (registrace začnou {$regGcOd->format('d.m.Y')})
- Kontaktovat nás na info@gamecon.cz a domluvit se, kam chceš zůstatek vrátit

Tvůj zůstatek: $zustatek Kč

Děkujeme za pochopení!
Tým GameConu
TEXT,
            TypVarovaniPromlceni::TYDEN => <<<TEXT
Ahoj $jmeno,

toto je připomínka našeho předchozího e-mailu.

Máš na GameCon účtu zůstatek $zustatek Kč, ale {$posledniRoky} jsi se GameConu nezúčastnil{$a}.

REGISTRACE UŽ ZA TÝDEN!
Registrace na GameCon $rocnik začínají {$regGcOd->formatCasZacatekUdalosti()}.

Pokud se nezaregistruješ a nezúčastníš se letošního GameConu, tvůj zůstatek bude promlčen krátce po skončení akce.

Co můžeš udělat:
- Registrovat se na letošní GameCon $rocnik
- Kontaktovat nás na info@gamecon.cz, pokud máš dotazy

Tvůj zůstatek: $zustatek Kč

Děkujeme!
Tým GameConu
TEXT,
        };
    }

    private function odesliInfoCfo(
        TypVarovaniPromlceni $typVarovani,
        int $rocnik,
        int $pocetEmailu,
        int $pocetLet,
        \DateTimeInterface $regGcOd,
    ): void {
        $cfosEmaily = Uzivatel::cfosEmaily();

        $predmet = match ($typVarovani) {
            TypVarovaniPromlceni::MESIC => "Varovné e-maily o promlčení zůstatků: odesláno $pocetEmailu e-mailů",
            TypVarovaniPromlceni::TYDEN => "Připomínka promlčení zůstatků: odesláno $pocetEmailu e-mailů",
        };

        $typTextu = match ($typVarovani) {
            TypVarovaniPromlceni::MESIC => 'Varovné',
            TypVarovaniPromlceni::TYDEN => 'Připomínkové',
        };

        $nazev = $this->dejNazevVarovani($typVarovani);

        $zprava = <<<TEXT
$typTextu e-maily o promlčení zůstatků ($nazev před otevřením registrací) byly odeslány.

Počet uživatelů: $pocetEmailu
Registrace začínají: {$regGcOd->format('d.m.Y H:i')}
Počet let neúčasti: $pocetLet

Uživatelé byli {$this->varovani($typVarovani)}, že jejich zůstatky budou promlčeny po skončení GameConu $rocnik, pokud se nezúčastní.
TEXT;

        (new GcMail($this->systemoveNastaveni))
            ->adresati($cfosEmaily ?: ['info@gamecon.cz'])
            ->predmet($predmet)
            ->text($zprava)
            ->odeslat(GcMail::FORMAT_TEXT);
    }

    private function varovani(TypVarovaniPromlceni $typVarovani): string
    {
        return match ($typVarovani) {
            TypVarovaniPromlceni::MESIC => 'varováni',
            TypVarovaniPromlceni::TYDEN => 'znovu upozorněni',
        };
    }
}
