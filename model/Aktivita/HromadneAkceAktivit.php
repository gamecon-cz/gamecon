<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

use Gamecon\Aktivita\Exceptions\NaHromadnouAutomatickouAktivaciJePozde;
use Gamecon\Aktivita\Exceptions\NevhodnyCasProAutomatickouHromadnouAktivaci;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Logger\LogHomadnychAkciTrait;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;

class HromadneAkceAktivit
{
    private const SKUPINA = 'aktivity';

    use LogHomadnychAkciTrait;

    private int $automatickyAktivovanoCelkem = 0;

    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni) {
    }

    public function hromadneAktivovatAutomaticky(\DateTimeInterface $platnostZpetneKDatu = null): int {
        $nejblizsiVlnaKdy = $this->systemoveNastaveni->nejblizsiVlnaKdy();
        $ted              = $this->systemoveNastaveni->ted();

        if ($nejblizsiVlnaKdy > $ted) {
            throw new NevhodnyCasProAutomatickouHromadnouAktivaci(
                sprintf(
                    "Hromadná aktivace může být spuštěna nejdříve v '%s' podle nejbližší vlny.",
                    $nejblizsiVlnaKdy->format(DateTimeCz::FORMAT_DB),
                )
            );
        }

        $platnostZpetneKDatu ??= $ted->modify('-1 day');
        if ($nejblizsiVlnaKdy < $platnostZpetneKDatu) {
            throw new NaHromadnouAutomatickouAktivaciJePozde(
                sprintf(
                    "Hromadná aktivace může být spuštěna nanejvýš den po platnosti.
Platnost hromadné aktivace byla '%s', teď je '%s' a aktivaci současné vlny šlo pustit nejpozději v '%s'",
                    $nejblizsiVlnaKdy->format(DateTimeCz::FORMAT_DB),
                    $ted->format(DateTimeCz::FORMAT_DB),
                    $platnostZpetneKDatu->format(DateTimeCz::FORMAT_DB),
                )
            );
        }

        $result                      = dbQuery(
            'UPDATE akce_seznam SET stav=$0 WHERE stav=$1 AND rok=$2',
            [0 => StavAktivity::AKTIVOVANA, 1 => StavAktivity::PRIPRAVENA, 2 => $this->systemoveNastaveni->rocnik()]
        );
        $automatickyAktivovanoCelkem = (int)dbAffectedOrNumRows($result);

        $this->zalogujHromadnouAkci(
            self::SKUPINA,
            $this->sestavNazevAkceHromadneAktivace($nejblizsiVlnaKdy),
            $automatickyAktivovanoCelkem,
            \Uzivatel::zId(\Uzivatel::SYSTEM, true)
        );

        $this->automatickyAktivovanoCelkem = $automatickyAktivovanoCelkem;

        return $automatickyAktivovanoCelkem;
    }

    public function automatickyAktivovanoCelkem(): int {
        return $this->automatickyAktivovanoCelkem;
    }

    public function hromadneAktivovatRucne(\Uzivatel $aktivujici, int $rocnik = null): int {
        $result                    = dbQuery(
            'UPDATE akce_seznam SET stav=$0 WHERE stav=$1 AND rok=$2',
            [0 => StavAktivity::AKTIVOVANA, 1 => StavAktivity::PRIPRAVENA, 2 => $rocnik]
        );
        $hromadneAktivovanoAktivit = (int)dbAffectedOrNumRows($result);

        $this->zalogujHromadnouAkci(
            self::SKUPINA,
            $this->nazevAkceHromadneRucniAktivace(),
            $hromadneAktivovanoAktivit,
            $aktivujici
        );

        return $hromadneAktivovanoAktivit;
    }

    private function nazevAkceHromadneRucniAktivace(): string {
        return 'rucni-aktivace';
    }

    /**
     * Odemče hromadně zamčené aktivity a odhlásí ty, kteří nesestavili teamy.
     * Vrací počet odemčených teamů (=>uvolněných míst)
     */
    public function odemciTeamoveHromadne(\Uzivatel $odemykajici): int {
        $o                       = dbQuery('SELECT id_akce, zamcel FROM akce_seznam WHERE zamcel AND zamcel_cas < NOW() - INTERVAL ' . Aktivita::HAJENI_TEAMU_HODIN . ' HOUR');
        $odemcenoTymovychAktivit = 0;
        while (list($aid, $uid) = mysqli_fetch_row($o)) {
            // uvolnění zámku je součástí odhlášení, pokud je sám -> done
            Aktivita::zId($aid)->odhlas(\Uzivatel::zId($uid), $odemykajici, 'hromadne-odemceni-teamovych');
            $odemcenoTymovychAktivit++;
        }
        $this->zalogujHromadnouAkci(
            self::SKUPINA,
            $this->nazevAkceHromadnehoOdemceniTeamovych(),
            $odemcenoTymovychAktivit,
            \Uzivatel::zId(\Uzivatel::SYSTEM, true)
        );

        return $odemcenoTymovychAktivit;
    }

    private function nazevAkceHromadnehoOdemceniTeamovych(): string {
        return 'odemceni-tymovych';
    }

    public function automatickaAktivaceProvedenaKdy(\DateTimeInterface $vlnaKdy = null): ?\DateTimeInterface {
        $vlnaKdy   ??= $this->systemoveNastaveni->nejblizsiVlnaKdy();
        $nazevAkce = $this->sestavNazevAkceHromadneAktivace($vlnaKdy);

        return $this->posledniHromadnaAkceKdy(self::SKUPINA, $nazevAkce);
    }

    private function sestavNazevAkceHromadneAktivace(\DateTimeInterface $vlnaKdy): string {
        return 'aktivace-' . $vlnaKdy->format(DateTimeCz::FORMAT_CAS_SOUBOR);
    }
}
