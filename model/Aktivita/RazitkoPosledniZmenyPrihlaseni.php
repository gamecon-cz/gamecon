<?php declare(strict_types=1);

namespace Gamecon\Aktivita;

use Symfony\Component\Filesystem\Filesystem;

class RazitkoPosledniZmenyPrihlaseni
{
    public const RAZITKO_POSLEDNI_ZMENY = 'razitko_posledni_zmeny';
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

    private function dejPosledniZmenu(): ?ZmenaStavuPrihlaseni {
        if (!$this->posledniZmena) {
            $this->posledniZmena = AktivitaPrezence::dejPosledniZmenaStavuPrihlaseniAktivit(
                null, // bereme každého účastníka
                $this->organizovaneAktivity
            );
        }
        return $this->posledniZmena;
    }

    private function dejObsah(): array {
        $posledniZmena = $this->dejPosledniZmenu();

        return [
            'id_aktivity' => $this->dejAktivituSPosledniZmenou($posledniZmena)->id(),
            'id_vypravece' => $this->vypravec->id(),
            'stav_prihlaseni' => $posledniZmena !== null
                ? $posledniZmena->stavPrihlaseniProJs()
                : null,
            'cas_zmeny' => $posledniZmena !== null
                ? $posledniZmena->casZmenyProJs()
                : null,
            self::RAZITKO_POSLEDNI_ZMENY => $this->dejRazitkoPosledniZmeny($posledniZmena),
        ];
    }

    private function dejRazitkoPosledniZmeny(?ZmenaStavuPrihlaseni $zmenaStavuPrihlaseni): string {
        return $zmenaStavuPrihlaseni === null
            ? ''
            : md5(($zmenaStavuPrihlaseni->stavPrihlaseniProJs() ?? '') . ($zmenaStavuPrihlaseni->casZmenyProJs() ?? ''));
    }

    private function dejCestuKSouboruRazitka(): string {
        $posledniZmena = $this->dejPosledniZmenu();
        $aktivitaSPosledniZmenou = $this->dejAktivituSPosledniZmenou($posledniZmena);

        $adresarProRazitkoPosledniZmeny = AktivitaPrezence::dejAdresarProRazitkoPosledniZmeny($this->vypravec, $aktivitaSPosledniZmenou);

        return $adresarProRazitkoPosledniZmeny . '/posledni-zmena-prihlaseni.json';
    }

    public function dejUrlRazitkaPosledniZmeny(): string {
        $relativniCestaRazitka = str_replace(ADMIN, '', $this->dejCestuKSouboruRazitka());
        return rtrim(URL_ADMIN, '/') . '/' . ltrim($relativniCestaRazitka, '/');
    }

    public function dejPotvrzeneRazitkoPosledniZmeny(): string {
        return $this->zapis();
    }

    private function zapis(): string {
        $obsah = $this->dejObsah();
        $obsahJson = json_encode($obsah, JSON_THROW_ON_ERROR);
        $souborRazitka = $this->dejCestuKSouboruRazitka();

        if (is_readable($souborRazitka) && file_get_contents($souborRazitka) === $obsahJson) {
            return $obsah[self::RAZITKO_POSLEDNI_ZMENY]; // neni co menit
        }

        $this->filesystem->mkdir(dirname($souborRazitka));

        $zapsano = file_put_contents($souborRazitka, $obsahJson);
        if ($zapsano === false) {
            throw new \RuntimeException('Nelze zapsat do souboru ' . $souborRazitka);
        }
        return $obsah[self::RAZITKO_POSLEDNI_ZMENY];
    }
}
