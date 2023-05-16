<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

use Symfony\Component\Filesystem\Filesystem;

class RazitkoPosledniZmenyPrihlaseni
{
    public static function smazRazitkaPoslednichZmen(Aktivita $aktivita, Filesystem $filesystem)
    {
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

    private static function smazRazitkaPoslednichZmenVAdresari(string $dir, Filesystem $filesystem)
    {
        $jsonFiles = glob($dir . '/**/*.json');
        if (!$jsonFiles) {
            return;
        }
        foreach ($jsonFiles as $jsonFile) {
            $json            = json_decode(file_get_contents($jsonFile), true);
            $emptyValuesJson = array_fill_keys(array_keys($json), '');
            $filesystem->dumpFile($jsonFile, json_encode($emptyValuesJson));
        }
    }

    /**
     * @param Aktivita $aktivita
     * @return string[]
     */
    private static function dejAdresareProRazitkaPoslednichZmenProOrganizatory(Aktivita $aktivita): array
    {
        $adresare = [];
        foreach ($aktivita->organizatori() as $vypravec) {
            $adresare[] = self::dejAdresarProRazitkoPosledniZmeny($vypravec);
        }
        return array_unique($adresare);
    }

    private static function dejAdresarProRazitkoPosledniZmeny(\Uzivatel $vypravec): string
    {
        return self::dejAdresarProRazitkaPoslednichZmen() . '/vypravec-' . $vypravec->id();
    }

    private static function dejAdresarProRazitkaPoslednichZmen(): string
    {
        return ADMIN_STAMPS . '/zmeny';
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
     * @var ZmenaStavuAktivity|null
     */
    private $posledniZmenaStavuAktivity;
    /**
     * @var ZmenaPrihlaseni|null
     */
    private $posledniZmenaPrihlaseni;
    /**
     * @var string
     */
    private $jsonKlicProRazitko;

    /**
     * @param \Uzivatel $vypravec
     * @param null|ZmenaStavuAktivity $posledniZmenaStavuAktivity
     * @param null|ZmenaPrihlaseni $posledniZmenaPrihlaseni
     * @param Filesystem $filesystem
     * @param string $jsonKlicProRazitko
     */
    public function __construct(
        \Uzivatel           $vypravec,
        ?ZmenaStavuAktivity $posledniZmenaStavuAktivity,
        ?ZmenaPrihlaseni    $posledniZmenaPrihlaseni,
        Filesystem          $filesystem,
        string              $jsonKlicProRazitko,
    )
    {
        $this->vypravec                   = $vypravec;
        $this->posledniZmenaStavuAktivity = $posledniZmenaStavuAktivity;
        $this->posledniZmenaPrihlaseni    = $posledniZmenaPrihlaseni;
        $this->filesystem                 = $filesystem;
        $this->jsonKlicProRazitko         = $jsonKlicProRazitko;
    }

    public function dejUrlRazitkaPosledniZmeny(): string
    {
        $relativniCestaRazitka = str_replace(ADMIN, '', $this->dejCestuKSouboruRazitka());
        return rtrim(URL_ADMIN, '/') . '/' . ltrim($relativniCestaRazitka, '/');
    }

    private function dejCestuKSouboruRazitka(): string
    {
        $adresarProRazitkoPosledniZmeny = self::dejAdresarProRazitkoPosledniZmeny($this->vypravec);

        return $adresarProRazitkoPosledniZmeny . "/posledni-zmena-prihlaseni.json";
    }

    public function dejPotvrzeneRazitkoPosledniZmeny(bool $prepsatStare = true): string
    {
        $razitko = static::spocitejRazitko($this->posledniZmenaStavuAktivity, $this->posledniZmenaPrihlaseni);
        $obsah   = $this->sestavObsah($razitko);
        $this->zapisObsah($obsah, $prepsatStare);

        return $razitko;
    }

    private static function spocitejRazitko(?ZmenaStavuAktivity $zmenaStavuAktivity, ?ZmenaPrihlaseni $zmenaPrihlaseni): string
    {
        return md5(json_encode([
            'zmena_stavu_aktivity' => $zmenaStavuAktivity !== null
                ? [
                    'stav_aktivity' => $zmenaStavuAktivity->stavAktivityProJs() ?? '',
                    'cas_zmeny'     => $zmenaStavuAktivity->casZmenyProJs() ?? '',
                ]
                : [],
            'zmena_prihlaseni'     => $zmenaPrihlaseni !== null
                ? [
                    'typ_prezence' => $zmenaPrihlaseni->typPrezenceProJs() ?? '',
                    'cas_zmeny'    => $zmenaPrihlaseni->casZmenyProJs() ?? '',
                ]
                : [],
        ]));
    }

    private function sestavObsah(string $razitko): array
    {
        $obsah = [
            $this->jsonKlicProRazitko => $razitko,
        ];
        if (defined('TESTING') && TESTING) {
            $obsah['debug'] = [
                'posledniZmenaStavuAktivity' => $this->posledniZmenaStavuAktivity !== null
                    ? [
                        'idAktivity'        => $this->posledniZmenaStavuAktivity->idAktivity(),
                        'idLogu'            => $this->posledniZmenaStavuAktivity->idLogu(),
                        'casZmeny'          => $this->posledniZmenaStavuAktivity->casZmeny()->format(DATE_ATOM),
                        'casZmenyProJs'     => $this->posledniZmenaStavuAktivity->casZmenyProJs(),
                        'stavAktivity'      => $this->posledniZmenaStavuAktivity->stavAktivity(),
                        'stavAktivityProJs' => $this->posledniZmenaStavuAktivity->stavAktivityProJs(),
                    ]
                    : null,
                'posledniZmenaPrihlaseni'    => $this->posledniZmenaPrihlaseni !== null
                    ? [
                        'idUzivatele'      => $this->posledniZmenaPrihlaseni->idUzivatele(),
                        'idAktivity'       => $this->posledniZmenaPrihlaseni->idAktivity(),
                        'idLogu'           => $this->posledniZmenaPrihlaseni->idLogu(),
                        'casZmeny'         => $this->posledniZmenaPrihlaseni->casZmeny()->format(DATE_ATOM),
                        'casZmenyProJs'    => $this->posledniZmenaPrihlaseni->casZmenyProJs(),
                        'typPrezence'      => $this->posledniZmenaPrihlaseni->typPrezence(),
                        'typPrezenceProJs' => $this->posledniZmenaPrihlaseni->typPrezenceProJs(),
                    ]
                    : null,
            ];
        }
        return $obsah;
    }

    private function zapisObsah(array $obsah, bool $prepsatStare)
    {
        $obsahJson     = json_encode($obsah, JSON_THROW_ON_ERROR);
        $souborRazitka = $this->dejCestuKSouboruRazitka();

        if (!is_readable($souborRazitka) || ($prepsatStare && file_get_contents($souborRazitka) !== $obsahJson)) {
            $this->zapisDoSouboru($obsahJson, $souborRazitka);
        }
    }

    private function zapisDoSouboru(string $data, string $soubor)
    {
        $this->filesystem->mkdir(dirname($soubor));
        $this->filesystem->dumpFile($soubor, $data);
    }
}
