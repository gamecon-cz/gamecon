<?php

declare(strict_types=1);

namespace Gamecon\Uzivatel;

use Gamecon\Cas\DateTimeCz;
use Gamecon\Pravo;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Uzivatel\Exceptions\NaHromadneOdhlasovaniJeBrzy;
use Gamecon\Uzivatel\Exceptions\NaHromadneOdhlasovaniJePozde;
use Chyba;

class OdhlaseniNeplaticu
{
    private int $odhlaseno = 0;

    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni) {
    }

    /**
     * @throws Chyba
     */
    public function hromadneOdhlasit(): int {
        $ted                             = $this->systemoveNastaveni->ted();
        $platnostZpetneKDatu             = $ted->modify('-1 day');
        $nejblizsiHromadneOdhlasovaniKdy = $this->systemoveNastaveni->nejblizsiHromadneOdhlasovaniKdy($platnostZpetneKDatu);
        $nejblizsiVlnaKdy                = $this->systemoveNastaveni->nejblizsiVlnaKdy($platnostZpetneKDatu);

        if ($nejblizsiHromadneOdhlasovaniKdy < $platnostZpetneKDatu) {
            throw new NaHromadneOdhlasovaniJePozde(
                sprintf(
                    "Hromadné odhlášení může být spuštěno nanejvýš den po platnosti. Platnost hromadného odhlášení byla '%s' a teď je '%s'",
                    $nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB),
                    $ted->format(DateTimeCz::FORMAT_DB)
                )
            );
        }

        if ($nejblizsiHromadneOdhlasovaniKdy >= $nejblizsiVlnaKdy) {
            throw new NaHromadneOdhlasovaniJePozde(
                sprintf(
                    "Nejbližší vlna aktivit už začala v '%s', nemůžeme začít hromadně odhlašovat k okamžiku '%s'",
                    $nejblizsiVlnaKdy->format(DateTimeCz::FORMAT_DB),
                    $nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB)
                )
            );
        }

        if ($nejblizsiHromadneOdhlasovaniKdy > $ted) {
            throw new NaHromadneOdhlasovaniJeBrzy(
                sprintf(
                    "Hromadné odhlášení může být spuštěno nejdříve v '%s'",
                    $nejblizsiHromadneOdhlasovaniKdy->format(DateTimeCz::FORMAT_DB)
                )
            );
        }

        $potize         = [];
        $uzivatelSystem = \Uzivatel::zId(\Uzivatel::SYSTEM);
        foreach ($this->uzivateleKeKontrole() as $uzivatel) {
            $kategorieNeplatice = KategorieNeplatice::vytvorProVlnu($uzivatel, $nejblizsiVlnaKdy);
            if ($kategorieNeplatice->melByBytOdhlasen()) {
                try {
                    $uzivatel->gcOdhlas($uzivatelSystem);
                    $this->odhlaseno++;
                    set_time_limit(30); // jenom pro jistotu, mělo by to trvat maximálně sekundu
                } catch (Chyba $chyba) {
                    $potize[] = sprintf(
                        "Nelze ohlásit účastníka %s s ID %d: '%s'",
                        $uzivatel->jmenoNick(),
                        $uzivatel->id(),
                        $chyba->getMessage()
                    );
                }
            }
        }

        if ($potize) {
            throw new Chyba(implode('; ', $potize));
        }

        return $this->odhlaseno;
    }

    private function uzivateleKeKontrole(): \Generator {
        $idUzivatelu = dbFetchColumn(<<<SQL
SELECT uzivatele_hodnoty.id_uzivatele
FROM uzivatele_hodnoty
LEFT JOIN platne_role_uzivatelu AS role ON uzivatele_hodnoty.id_uzivatele = role.id_uzivatele
LEFT JOIN prava_role on role.id_role = prava_role.id_role
WHERE prava_role.id_role != $0
SQL,
            [0 => Pravo::NERUSIT_AUTOMATICKY_OBJEDNAVKY]
        );
        foreach ($idUzivatelu as $iduzivatele) {
            yield \Uzivatel::zId($iduzivatele);
        }
    }

    public function odhlaseno(): int {
        return $this->odhlaseno;
    }

}