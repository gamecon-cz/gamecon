<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

use Symfony\Component\Filesystem\Filesystem;

class RazitkoPosledniZmenyPrihlaseni
{
    public static function smazRazitkaPoslednichZmen(Aktivita $aktivita, Filesystem $filesystem) {
        if (defined('TESTING') && TESTING
            && defined('TEST_MAZAT_VSECHNA_RAZITKA_POSLEDNICH_ZMEN') && TEST_MAZAT_VSECHNA_RAZITKA_POSLEDNICH_ZMEN
        ) {
            /**
             * Při testování online prezence se vypisují i aktivity, které organizátor ve skutečnosti neorganizuje.
             * Proto musíme mazat všechna razítka.
             * Kdybychom je smazali jen těm, kteří aktivity opravdu ogranizují, tak by se neorganizujícímu testerovi nenačetly změny.
             */
            self::smazRazitkaPoslednichZmenVAdresari(self::dejAdresarProRazitkaPoslednichZmen(), $filesystem);
            return;
        }
        foreach (self::dejAdresareProRazitkaPoslednichZmenProOrganizatory($aktivita) as $adresar) {
            self::smazRazitkaPoslednichZmenVAdresari($adresar, $filesystem);
        }
    }

    private static function smazRazitkaPoslednichZmenVAdresari(string $dir, Filesystem $filesystem) {
        $jsonFiles = glob($dir . '/**/*.json');
        if (!$jsonFiles) {
            return;
        }
        foreach ($jsonFiles as $jsonFile) {
            $json = json_decode(file_get_contents($jsonFile), true);
            $emptyValuesJson = array_fill_keys(array_keys($json), '');
            $filesystem->dumpFile($jsonFile, json_encode($emptyValuesJson));
        }
    }

    /**
     * @param Aktivita $aktivita
     * @return string[]
     */
    private static function dejAdresareProRazitkaPoslednichZmenProOrganizatory(Aktivita $aktivita): array {
        $adresare = [];
        foreach ($aktivita->organizatori() as $vypravec) {
            $adresare[] = self::dejAdresarProRazitkoPosledniZmeny($vypravec);
        }
        return array_unique($adresare);
    }

    private static function dejAdresarProRazitkoPosledniZmeny(\Uzivatel $vypravec): string {
        return self::dejAdresarProRazitkaPoslednichZmen() . '/vypravec-' . $vypravec->id();
    }

    private static function dejAdresarProRazitkaPoslednichZmen(): string {
        return ADMIN_STAMPS . '/zmeny';
    }

    public static function dejRazitko(PosledniZmenyStavuPrihlaseni $posledniZmenyStavuPrihlaseni): string {
        return self::spocitejRazitko($posledniZmenyStavuPrihlaseni->posledniZmenaStavuPrihlaseni());
    }

    /**
     * @var \Uzivatel
     */
    private $vypravec;
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @var ZmenaStavuPrihlaseni|null
     */
    private $posledniZmena;
    /**
     * @var string
     */
    private $jsonKlicProRazitko;

    /**
     * @param \Uzivatel $vypravec
     * @param Aktivita[] $posledniZmena
     * @param Filesystem $filesystem
     * @param string $jsonKlicProRazitko
     */
    public function __construct(
        \Uzivatel             $vypravec,
        ?ZmenaStavuPrihlaseni $posledniZmena,
        Filesystem            $filesystem,
        string                $jsonKlicProRazitko
    ) {
        $this->vypravec = $vypravec;
        $this->posledniZmena = $posledniZmena;
        $this->filesystem = $filesystem;
        $this->jsonKlicProRazitko = $jsonKlicProRazitko;
    }

    private function sestavObsah(string $razitko): array {
        $obsah = [
            $this->jsonKlicProRazitko => $razitko,
        ];
        if (defined('TESTING') && TESTING) {
            $obsah['debug'] = $this->posledniZmena
                ? [
                    'idUzivatele' => $this->posledniZmena->idUzivatele(),
                    'idAktivity' => $this->posledniZmena->idAktivity(),
                    'idLogu' => $this->posledniZmena->idLogu(),
                    'casZmeny' => $this->posledniZmena->casZmeny()->format(DATE_ATOM),
                    'typPrezence' => $this->posledniZmena->typPrezence(),
                ]
                : null;
        }
        return $obsah;
    }

    private static function spocitejRazitko(?ZmenaStavuPrihlaseni $zmenaStavuPrihlaseni): string {
        return $zmenaStavuPrihlaseni === null
            ? md5('') // aspoň něco ve smyslu "razítko je platné"
            : md5(($zmenaStavuPrihlaseni->typPrezenceProJs() ?? '') . ($zmenaStavuPrihlaseni->casZmenyProJs() ?? ''));
    }

    public function dejUrlRazitkaPosledniZmeny(): string {
        $relativniCestaRazitka = str_replace(ADMIN, '', $this->dejCestuKSouboruRazitka());
        return rtrim(URL_ADMIN, '/') . '/' . ltrim($relativniCestaRazitka, '/');
    }

    private function dejCestuKSouboruRazitka(): string {
        $adresarProRazitkoPosledniZmeny = self::dejAdresarProRazitkoPosledniZmeny($this->vypravec);

        return $adresarProRazitkoPosledniZmeny . "/posledni-zmena-prihlaseni.json";
    }

    public function dejPotvrzeneRazitkoPosledniZmeny(): string {
        $razitko = static::spocitejRazitko($this->posledniZmena);
        $obsah = $this->sestavObsah($razitko);
        $this->zapisObsah($obsah);

        return $razitko;
    }

    private function zapisObsah(array $obsah) {
        $obsahJson = json_encode($obsah, JSON_THROW_ON_ERROR);
        $souborRazitka = $this->dejCestuKSouboruRazitka();

        if (!is_readable($souborRazitka) || file_get_contents($souborRazitka) !== $obsahJson) {
            $this->zapisDoSouboru($obsahJson, $souborRazitka);
        }
    }

    private function zapisDoSouboru(string $data, string $soubor) {
        $this->filesystem->mkdir(dirname($soubor));
        $this->filesystem->dumpFile($soubor, $data);
    }
}
