<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Cas\DateTimeImmutableStrict;
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

    public function hromadneOdhlasit(
        ?Zaznamnik         $zaznamnik,
        \DateTimeInterface $platnostZpetneKDatu = null
    ): int {
        $nejblizsiHromadneOdhlasovaniKdy = $this->systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy($platnostZpetneKDatu);
        $uzivatelSystem                  = \Uzivatel::zId(\Uzivatel::SYSTEM);
        foreach ($this->neplaticiAKategorie($nejblizsiHromadneOdhlasovaniKdy)
                 as ['uzivatel' => $uzivatel, 'kategorie_neplatice' => $kategorieNeplatice]) {
            if ($kategorieNeplatice->melByBytOdhlasen()) {
                try {
                    $uzivatel->gcOdhlas($uzivatelSystem, $this->systemoveNastaveni, $zaznamnik);
                    $this->odhlasenoCelkem++;
                    set_time_limit(30); // jenom pro jistotu, mělo by to trvat maximálně sekundu
                } catch (Chyba $chyba) {
                    $potiz = sprintf(
                        "Nelze ohlásit účastníka %s s ID %d: '%s'",
                        $uzivatel->jmenoNick(),
                        $uzivatel->id(),
                        $chyba->getMessage()
                    );
                    $zaznamnik->pridejZpravu("Potíže: $potiz");
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
        $idUzivatelu = dbFetchColumn(<<<SQL
SELECT uzivatele_hodnoty.id_uzivatele
FROM uzivatele_hodnoty
LEFT JOIN platne_role_uzivatelu AS role ON uzivatele_hodnoty.id_uzivatele = role.id_uzivatele
LEFT JOIN prava_role on role.id_role = prava_role.id_role
WHERE prava_role.id_role = $0
    AND prava_role.id_prava != $1
SQL,
            [
                0 => Role::PRIHLASEN_NA_LETOSNI_GC,
                1 => Pravo::NERUSIT_AUTOMATICKY_OBJEDNAVKY,
            ]
        );
        foreach ($idUzivatelu as $idUzivatele) {
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
    public function neplaticiAKategorie(\DateTimeInterface $nejblizsiHromadneOdhlasovaniKdy = null): \Generator {
        $ted                             = $this->systemoveNastaveni->ted();
        $nejblizsiHromadneOdhlasovaniKdy ??= $this->systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy();

        if ($nejblizsiHromadneOdhlasovaniKdy > $ted) {
            throw new NaHromadneOdhlasovaniJeBrzy(
                sprintf(
                    "Hromadné odhlášení může být spuštěno nejdříve v '%s'",
                    $nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB)
                )
            );
        }

        $platnostZpetneKDatu ??= $ted->modify('-1 day');
        if ($nejblizsiHromadneOdhlasovaniKdy < $platnostZpetneKDatu) {
            throw new NaHromadneOdhlasovaniJePozde(
                sprintf(
                    "Hromadné odhlášení může být spuštěno nanejvýš den po platnosti.
Platnost hromadného odhlášení byla '%s', teď je '%s' a nejpozději šlo hromadně odhlásit v '%s'",
                    $nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB),
                    $ted->format(DateTimeCz::FORMAT_DB),
                    $platnostZpetneKDatu->format(DateTimeCz::FORMAT_DB),
                )
            );
        }

        $nejblizsiVlnaOtevreniAktivitKdy = $this->systemoveNastaveni->nejblizsiVlnaKdy($platnostZpetneKDatu);
        if ($nejblizsiHromadneOdhlasovaniKdy >= $nejblizsiVlnaOtevreniAktivitKdy) {
            throw new NaHromadneOdhlasovaniJePozde(
                sprintf(
                    "Nejbližší vlna aktivit už začala v '%s', nemůžeme začít hromadně odhlašovat k okamžiku '%s'",
                    $nejblizsiVlnaOtevreniAktivitKdy->format(DateTimeCz::FORMAT_DB),
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
                yield ['neplatic' => $uzivatel, 'kategorie_neplatice' => $kategorieNeplatice];
            }
        }
    }

    public function cfoNotifikovanOBrzkemHromadnemOdhlaseniKdy(\DateTimeInterface $hromadneOdhlasovaniKdy): ?DateTimeImmutableStrict {
        return $this->posledniHromadnaAkceKdy(
            self::SKUPINA,
            $this->sestavNazevAkceHromadnehoOdhlaseni($hromadneOdhlasovaniKdy),
        );
    }

    public function zalogujCfoNotifikovanOBrzkemHromadnemOdhlaseni(
        int                $budeOdhlaseno,
        \DateTimeInterface $hromadneOdhlasovaniKdy,
        \Uzivatel          $odeslal
    ) {
        $this->zalogujHromadnouAkci(
            self::SKUPINA,
            $this->sestavNazevAkceHromadnehoOdhlaseni($hromadneOdhlasovaniKdy),
            $budeOdhlaseno,
            $odeslal
        );
    }

    private function sestavNazevAkceEmailuOBlizicimSeHromadnemOdhlaseni(\DateTimeInterface $hromadneOdhlasovaniKdy): string {
        return 'email-cfo-brzke-odhlaseni-' . $hromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_CAS_SOUBOR);
    }
}
