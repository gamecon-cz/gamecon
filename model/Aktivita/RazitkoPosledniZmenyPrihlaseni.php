<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

use Symfony\Component\Filesystem\Filesystem;

class RazitkoPosledniZmenyPrihlaseni
{
    public const RAZITKO_POSLEDNI_ZMENY = 'razitko_posledni_zmeny';

    public static function smazRazitkaPoslednichZmen(Aktivita $aktivita, Filesystem $filesystem) {
        if (defined('TESTING') && TESTING
            && defined('TEST_MAZAT_VSECHNA_RAZITKA_POSLEDNICH_ZMEN') && TEST_MAZAT_VSECHNA_RAZITKA_POSLEDNICH_ZMEN
        ) {
            /**
             * Při testování online prezence se vypisují i aktivity, které organizátor ve skutečnosti neorganizuje.
             * Proto musíme mazat všechna razítka, protože smazat je jen těm, kteří ji opravdu ogranizují, nestačí - neorganizujícímu testerovi by se nenačetly změny.
             */
            $filesystem->remove(self::dejAdresarProRazitkaPoslednichZmen());
            return;
        }
        foreach (self::dejAdresareProRazitkaPoslednichZmenProOrganizatory($aktivita) as $adresar) {
            $filesystem->remove($adresar);
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

    /**
     * @var \Uzivatel
     */
    private $vypravec;
    /**
     * @var Aktivita[]
     */
    private $organizovaneAktivity;
    /**
     * @var Filesystem
     */
    private $filesystem;
    /**
     * @var ZmenaStavuPrihlaseni|null
     */
    private $posledniZmena;

    /**
     * @param \Uzivatel $vypravec
     * @param Aktivita[] $organizovaneAktivity
     * @param Filesystem $filesystem
     */
    public function __construct(
        \Uzivatel  $vypravec,
        array      $organizovaneAktivity,
        Filesystem $filesystem
    ) {
        $this->vypravec = $vypravec;
        $this->organizovaneAktivity = $organizovaneAktivity;
        $this->filesystem = $filesystem;
    }

    private function dejAktivituSPosledniZmenou(?ZmenaStavuPrihlaseni $posledniZmena): Aktivita {
        if ($posledniZmena === null) {
            // Žádná poslední změna přihlášení? Tak bereme jakoukoli aktivitu.
            return reset($this->organizovaneAktivity);
        }
        $aktivitaSPosledniZmenouVPoli = array_filter(
            $this->organizovaneAktivity,
            static function (Aktivita $aktivita) use ($posledniZmena) {
                return $aktivita->id() === $posledniZmena->idAktivity();
            }
        );
        return reset($aktivitaSPosledniZmenouVPoli);
    }

    public function dejPosledniZmenu(): ?ZmenaStavuPrihlaseni {
        if (!$this->posledniZmena) {
            $this->posledniZmena = AktivitaPrezence::dejPosledniZmenaStavuPrihlaseniAktivit(
                null, // bereme každého účastníka
                $this->organizovaneAktivity
            );
        }
        return $this->posledniZmena;
    }

    private function dejObsah(?ZmenaStavuPrihlaseni $posledniZmena): array {
        return [
            self::RAZITKO_POSLEDNI_ZMENY => $this->dejRazitkoPosledniZmeny($posledniZmena),
        ];
    }

    private function dejRazitkoPosledniZmeny(?ZmenaStavuPrihlaseni $zmenaStavuPrihlaseni): string {
        return $zmenaStavuPrihlaseni === null
            ? ''
            : md5(($zmenaStavuPrihlaseni->stavPrihlaseniProJs() ?? '') . ($zmenaStavuPrihlaseni->casZmenyProJs() ?? ''));
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
        $posledniZmena = $this->dejPosledniZmenu();
        $obsah = $this->dejObsah($posledniZmena);
        $obsahJson = json_encode($obsah, JSON_THROW_ON_ERROR);
        $souborRazitka = $this->dejCestuKSouboruRazitka();

        if (!is_readable($souborRazitka) || file_get_contents($souborRazitka) !== $obsahJson) {
            $this->zapis($obsahJson, $souborRazitka);
        }

        return $obsah[self::RAZITKO_POSLEDNI_ZMENY];
    }

    private function zapis(string $obsahJson, string $souborRazitka): bool {

        $this->filesystem->mkdir(dirname($souborRazitka));

        $zapsano = file_put_contents($souborRazitka, $obsahJson);
        if ($zapsano === false) {
            throw new \RuntimeException('Nelze zapsat do souboru ' . $souborRazitka);
        }
        return (bool)$zapsano;
    }
}
