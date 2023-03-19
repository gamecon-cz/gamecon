<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Aktivita\Aktivita;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeGamecon;
use Gamecon\Cas\DateTimeImmutableStrict;
use Gamecon\Kanaly\GcMail;
use Gamecon\Logger\LogHomadnychAkciTrait;
use Gamecon\Logger\Zaznamnik;
use Gamecon\Pravo;
use Gamecon\Role\Role;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Exceptions\NaHromadneOdhlasovaniJeBrzy;
use Gamecon\Uzivatel\Exceptions\NaHromadneOdhlasovaniJePozde;
use Chyba;

class HromadneOdhlaseniNeplaticu
{
    private const SKUPINA = 'uzivatele';

    use LogHomadnychAkciTrait;

    private int $odhlasenoCelkem = 0;

    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni) {
    }

    /**
     * @throws NaHromadneOdhlasovaniJeBrzy
     * @throws NaHromadneOdhlasovaniJePozde
     */
    public function hromadneOdhlasit(
        string             $zdrojOdhlaseniZaklad,
        ?Zaznamnik         $zaznamnik,
        \DateTimeInterface $platnostZpetneKDatu = null,
        \DateTimeInterface $nejblizsiHromadneOdhlasovaniKdy = null,
    ): int {
        $nejblizsiHromadneOdhlasovaniKdy ??= $this->systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy($platnostZpetneKDatu);
        $poradiHromadnehoOdhlasovani     = DateTimeGamecon::poradiHromadnehoOdhlasovani(
            $nejblizsiHromadneOdhlasovaniKdy,
            $this->systemoveNastaveni
        );
        $zdrojOdhlaseni                  = "$zdrojOdhlaseniZaklad-$poradiHromadnehoOdhlasovani";
        $uzivatelSystem                  = \Uzivatel::zId(\Uzivatel::SYSTEM);
        foreach ($this->neplaticiAKategorie($nejblizsiHromadneOdhlasovaniKdy, $platnostZpetneKDatu)
                 as ['neplatic' => $neplatic, 'kategorie_neplatice' => $kategorieNeplatice]
        ) {
            set_time_limit(30); // jenom pro jistotu, mělo by to trvat maximálně sekundu
            /**
             * @var \Uzivatel $neplatic
             * @var KategorieNeplatice $kategorieNeplatice
             */
            if ($kategorieNeplatice->melByBytOdhlasen()) {
                if ($kategorieNeplatice->maSmyslOdhlasitMuJenNeco()) {
                    $vysledekOdhlaseniJenNeco = null;
                    $predtimCelkemOdlhaseno   = 0;
                    do {
                        $vysledekOdhlaseniJenNeco = $this->odhlasMuJenNeco($neplatic, $zdrojOdhlaseni, $uzivatelSystem, $vysledekOdhlaseniJenNeco);
                        if ($vysledekOdhlaseniJenNeco->celkemOdhlaseno() > $predtimCelkemOdlhaseno) {
                            $kategorieNeplatice->obnovUdaje(false /* platby se nezměnily */);
                        }
                        $predtimCelkemOdlhaseno = $vysledekOdhlaseniJenNeco->celkemOdhlaseno();
                    } while ($vysledekOdhlaseniJenNeco->jesteNecoNeodhlasovano() && $kategorieNeplatice->melByBytOdhlasen());
                    if (!$kategorieNeplatice->melByBytOdhlasen()) {
                        $this->posliEmailSOdhlasenymiPolozkami($neplatic, $zdrojOdhlaseni);
                        continue; // povedlo se, postupným odhlašováním položek jsme se dostali až k tomu, že nemusíme odhlásit samotného účastníka
                    }
                }
                try {
                    $neplatic->odhlasZGc(
                        $zdrojOdhlaseni,
                        $uzivatelSystem,
                        $zaznamnik,
                    );
                    $zaznamnik?->pridejEntitu($neplatic);
                    $this->odhlasenoCelkem++;
                } catch (Chyba $chyba) {
                    $potiz = sprintf(
                        "Nelze ohlásit účastníka %s s ID %d: '%s'",
                        $neplatic->jmenoNick(),
                        $neplatic->id(),
                        $chyba->getMessage()
                    );
                    $zaznamnik?->pridejZpravu("Potíže: $potiz");
                }
            }
        }

        $this->zalogujHromadneOdhlaseni(
            $this->odhlasenoCelkem,
            $nejblizsiHromadneOdhlasovaniKdy,
            \Uzivatel::zId(\Uzivatel::SYSTEM, true)
        );

        return $this->odhlasenoCelkem;
    }

    private function odhlasMuJenNeco(
        \Uzivatel                $uzivatel,
        string                   $zdrojOdhlaseni,
        \Uzivatel                $odhlasujici,
        VysledekOdhlaseniJenNeco $vysledekOdhlaseniJenNeco = null
    ): VysledekOdhlaseniJenNeco {
        $vysledekOdhlaseniJenNeco ??= new VysledekOdhlaseniJenNeco();

        if ($vysledekOdhlaseniJenNeco->odhlasenoUbytovani() === null) {
            $vysledekOdhlaseniJenNeco->nastavOdhlasenoUbytovani(
                $uzivatel->shop($this->systemoveNastaveni)->zrusLetosniObjednaneUbytovani($zdrojOdhlaseni)
            );
            if ($vysledekOdhlaseniJenNeco->odhlasenoUbytovani() > 0) {
                return $vysledekOdhlaseniJenNeco;
            }
        }

        if ($vysledekOdhlaseniJenNeco->odhlasenoLarpu() === null) {
            $vysledekOdhlaseniJenNeco->nastavOdhlasenoLarpu(
                $uzivatel->shop($this->systemoveNastaveni)->zrusPrihlaseniNaLetosniLarpy(
                    $odhlasujici,
                    $zdrojOdhlaseni
                )
            );
            if ($vysledekOdhlaseniJenNeco->odhlasenoLarpu() > 0) {
                return $vysledekOdhlaseniJenNeco;
            }
        }

        if ($vysledekOdhlaseniJenNeco->odhlasenoRpg() === null) {
            $vysledekOdhlaseniJenNeco->nastavOdhlasenoRpg(
                $uzivatel->shop($this->systemoveNastaveni)->zrusPrihlaseniNaLetosniRpg(
                    $odhlasujici,
                    $zdrojOdhlaseni
                )
            );
            if ($vysledekOdhlaseniJenNeco->odhlasenoRpg() > 0) {
                return $vysledekOdhlaseniJenNeco;
            }
        }

        if ($vysledekOdhlaseniJenNeco->odhlasenoOstatnichAktivit() === null) {
            $vysledekOdhlaseniJenNeco->nastavOdhlasenoOstatnichAktivit(
            // respektive na zbývající
                $uzivatel->shop($this->systemoveNastaveni)->zrusPrihlaseniNaVsechnyAktivity(
                    $odhlasujici,
                    $zdrojOdhlaseni
                )
            );
        }
        return $vysledekOdhlaseniJenNeco;
    }

    private function zalogujHromadneOdhlaseni(
        int                $odhlaseno,
        \DateTimeInterface $hromadneOdhlasovaniKdy,
        \Uzivatel          $odhlasujici
    ) {
        $this->zalogujHromadnouAkci(
            self::SKUPINA,
            $this->sestavNazevAkceHromadnehoOdhlaseni($hromadneOdhlasovaniKdy),
            $odhlaseno,
            $odhlasujici
        );
    }

    private function sestavNazevAkceHromadnehoOdhlaseni(\DateTimeInterface $hromadneOdhlasovaniKdy): string {
        return 'odhlaseni-' . $hromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_CAS_SOUBOR);
    }

    private function uzivateleKeKontrole(): \Generator {
        $prihlasenNaLetosniGc = Role::PRIHLASEN_NA_LETOSNI_GC;
        $neodhlasovat         = Role::LETOSNI_NEODHLASOVAT;

        $result = dbQuery(<<<SQL
SELECT uzivatele_hodnoty.id_uzivatele
FROM uzivatele_hodnoty
WHERE EXISTS(SELECT * FROM platne_role_uzivatelu AS role
      WHERE uzivatele_hodnoty.id_uzivatele = role.id_uzivatele
        AND role.id_role = {$prihlasenNaLetosniGc}
    )
    AND NOT EXISTS(SELECT * FROM platne_role_uzivatelu AS role
        WHERE uzivatele_hodnoty.id_uzivatele = role.id_uzivatele
        AND role.id_role = {$neodhlasovat}
    )
SQL,
            dbConnectTemporary() // abychom nevyblokovali globální sdílené connection při postupném zpracovávání tohoto generátoru
        );
        while ($idUzivatele = mysqli_fetch_column($result)) {
            yield \Uzivatel::zId($idUzivatele, true);
        }
    }

    public function odhlasenoCelkem(): int {
        return $this->odhlasenoCelkem;
    }

    public function odhlaseniProvedenoKdy(\DateTimeInterface $hromadneOdhlasovaniKdy = null): ?\DateTimeInterface {
        $hromadneOdhlasovaniKdy ??= $this->systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy();
        $nazevAkce              = $this->sestavNazevAkceHromadnehoOdhlaseni($hromadneOdhlasovaniKdy);

        return $this->posledniHromadnaAkceKdy(self::SKUPINA, $nazevAkce);
    }

    /**
     * @return \Generator{neplatic: \Uzivatel, kategorie_neplatice: KategorieNeplatice}
     * @throws NaHromadneOdhlasovaniJeBrzy
     * @throws NaHromadneOdhlasovaniJePozde
     */
    public function neplaticiAKategorie(
        \DateTimeInterface $nejblizsiHromadneOdhlasovaniKdy = null,
        \DateTimeInterface $platnostZpetneKDatu = null,
        \DateTimeInterface $kDatu = null,

    ): \Generator {
        $nejblizsiHromadneOdhlasovaniKdy ??= $this->systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy();
        $kDatu                           ??= $this->systemoveNastaveni->ted();

        if ($nejblizsiHromadneOdhlasovaniKdy > $kDatu) {
            throw new NaHromadneOdhlasovaniJeBrzy(
                sprintf(
                    "Hromadné odhlášení může být spuštěno nejdříve v '%s'",
                    $nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB)
                )
            );
        }

        $platnostZpetneKDatu ??= $kDatu->modify('-1 day');
        if ($nejblizsiHromadneOdhlasovaniKdy < $platnostZpetneKDatu) {
            throw new NaHromadneOdhlasovaniJePozde(
                sprintf(
                    "Hromadné odhlášení může být spuštěno nanejvýš den po platnosti.
Platnost hromadného odhlášení byla '%s', teď je '%s' a nejpozději šlo hromadně odhlásit v '%s'",
                    $nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB),
                    $kDatu->format(DateTimeCz::FORMAT_DB),
                    $platnostZpetneKDatu->format(DateTimeCz::FORMAT_DB),
                )
            );
        }

        $nejblizsiVlnaKdy = $this->systemoveNastaveni->nejblizsiVlnaKdy($platnostZpetneKDatu);
        if ($nejblizsiHromadneOdhlasovaniKdy >= $nejblizsiVlnaKdy) {
            throw new NaHromadneOdhlasovaniJePozde(
                sprintf(
                    "Nejbližší vlna aktivit už začala v '%s', nemůžeme začít hromadně odhlašovat k okamžiku '%s'",
                    $nejblizsiVlnaKdy->format(DateTimeCz::FORMAT_DB),
                    $nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB)
                )
            );
        }

        foreach ($this->uzivateleKeKontrole() as $uzivatel) {
            $kategorieNeplatice = KategorieNeplatice::vytvorZHromadnehoOdhlasovani(
                $uzivatel,
                $nejblizsiHromadneOdhlasovaniKdy,
                $this->systemoveNastaveni
            );
            if ($kategorieNeplatice->melByBytOdhlasen()) {
                yield [
                    'neplatic'            => $uzivatel,
                    'kategorie_neplatice' => $kategorieNeplatice,
                ];
            }
        }
    }

    public function cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy(
        \DateTimeInterface $hromadneOdhlasovaniKdy,
        int                $poradiOznameni
    ): ?DateTimeImmutableStrict {
        return $this->posledniHromadnaAkceKdy(
            self::SKUPINA,
            $this->sestavNazevAkceEmailuCfoInfo($poradiOznameni, $hromadneOdhlasovaniKdy),
        );
    }

    public function neplaticiNotifikovaniOBrzkemHromadnemOdhlaseniKdy(
        \DateTimeInterface $hromadneOdhlasovaniKdy,
        int                $poradiOznameni
    ): ?DateTimeImmutableStrict {
        return $this->posledniHromadnaAkceKdy(
            self::SKUPINA,
            $this->sestavNazevAkceEmailuSVarovanim($poradiOznameni, $hromadneOdhlasovaniKdy),
        );
    }

    private function sestavNazevAkceEmailuSVarovanim(
        int                $poradiOznameni,
        \DateTimeInterface $hromadneOdhlasovaniKdy
    ): string {
        return "email-varobvani-neplaticum-brzke-odhlaseni-$poradiOznameni-" . $hromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_CAS_SOUBOR);
    }

    public function zalogujNotifikovaniCfoOBrzkemHromadnemOdhlaseni(
        int                $budeOdhlasenoPocet,
        \DateTimeInterface $hromadneOdhlasovaniKdy,
        int                $poradiOznameni,
        \Uzivatel          $odeslal,
        \DateTimeInterface $stalaSeKdy = null
    ) {
        $this->zalogujHromadnouAkci(
            self::SKUPINA,
            $this->sestavNazevAkceEmailuCfoInfo($poradiOznameni, $hromadneOdhlasovaniKdy),
            $budeOdhlasenoPocet,
            $odeslal,
            $stalaSeKdy
        );
    }

    private function sestavNazevAkceEmailuCfoInfo(
        int                $poradiOznameni,
        \DateTimeInterface $hromadneOdhlasovaniKdy
    ): string {
        return "email-cfo-brzke-odhlaseni-$poradiOznameni-" . $hromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_CAS_SOUBOR);
    }

    public function zalogujNotifikovaniNeplaticuOBrzkemHromadnemOdhlaseni(
        int                $pocetPotencialnichNeplaticu,
        \DateTimeInterface $hromadneOdhlasovaniKdy,
        int                $poradiOznameni,
        \Uzivatel          $odeslal,
        \DateTimeInterface $staloSeKdy = null
    ) {

        $this->zalogujHromadnouAkci(
            self::SKUPINA,
            $this->sestavNazevAkceEmailuSVarovanim($poradiOznameni, $hromadneOdhlasovaniKdy),
            $pocetPotencialnichNeplaticu,
            $odeslal,
            $staloSeKdy
        );
    }

    private function posliEmailSOdhlasenymiPolozkami(\Uzivatel $uzivatel, string $zdrojOdhlaseni) {
        $zruseneAktivityUzivatele = Aktivita::dejZruseneAktivityUzivatele(
            $uzivatel,
            $zdrojOdhlaseni,
            $this->systemoveNastaveni->rocnik()
        );

        $nazvyZrusenychNakupu = $uzivatel->shop($this->systemoveNastaveni)->dejNazvyZrusenychNakupu($zdrojOdhlaseni);
        if (!$zruseneAktivityUzivatele && !$nazvyZrusenychNakupu) {
            return;
        }

        $castiPredmetu = [];
        $castiTextu    = [];

        if ($nazvyZrusenychNakupu) {
            $castiPredmetu[] = 'objednávky';
            $castiTextu[]    = 'zrušit tvé objednávky ' . implode(', ', $nazvyZrusenychNakupu);
        }

        if ($zruseneAktivityUzivatele) {
            $castiPredmetu[]       = 'aktivity';
            $y                     = count($zruseneAktivityUzivatele) > 1
                ? 'y'
                : '';
            $te                    = count($castiTextu) > 0
                ? ''
                : 'tě ';
            $nazvyZrusenychAktivit = array_map(
                static fn(Aktivita $aktivita) => $aktivita->nazev(),
                $zruseneAktivityUzivatele
            );
            $castiTextu[]          = "{$te}odlásit z aktivit$y " . implode(', ', $nazvyZrusenychAktivit);
        }

        $text = implode(' a ', $castiTextu);

        (new GcMail())
            ->adresat($uzivatel->mail())
            ->predmet('Odhlášené ' . implode(' a ', $castiPredmetu))
            ->text(<<<TEXT
                Jelikož tvé finance nedorazili na účet Gameconu včas, museli jsme $text

                Aktivity si můžeš znovu přihlásit v další vlně, předměty si můžeš znovu objednat kdykoliv. Jen prosíme ohlídej své platby.

                Tým Gameconu
                TEXT
            )
            ->odeslat();
    }
}
