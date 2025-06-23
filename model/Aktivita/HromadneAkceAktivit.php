<?php

declare(strict_types=1);

namespace Gamecon\Aktivita;

use Gamecon\Aktivita\Exceptions\NaHromadnouAutomatickouAktivaciJePozde;
use Gamecon\Aktivita\Exceptions\NevhodnyCasProAutomatickouHromadnouAktivaci;
use Gamecon\Cas\DateTimeCz;
use Gamecon\Logger\LogHomadnychAkciTrait;
use Gamecon\SystemoveNastaveni\SystemoveNastaveni;
use Gamecon\Aktivita\SqlStruktura\AkceSeznamSqlStruktura as Sql;

class HromadneAkceAktivit
{
    private const SKUPINA_AKTIVITY = 'aktivity';

    use LogHomadnychAkciTrait;

    private int $automatickyAktivovanoCelkem = 0;

    public function __construct(private readonly SystemoveNastaveni $systemoveNastaveni)
    {
    }

    public function hromadneAktivovatAutomaticky(\DateTimeInterface $platnostZpetneKDatu = null): int
    {
        $nejblizsiVlnaKdy = $this->systemoveNastaveni->nejblizsiVlnaKdy();
        $ted              = $this->systemoveNastaveni->ted();

        if ($nejblizsiVlnaKdy > $ted) {
            throw new NevhodnyCasProAutomatickouHromadnouAktivaci(
                sprintf(
                    "Hromadná aktivace může být spuštěna nejdříve v '%s' (%s) podle nejbližší vlny.",
                    $nejblizsiVlnaKdy->format(DateTimeCz::FORMAT_DB),
                    $nejblizsiVlnaKdy->relativniVBudoucnu($this->systemoveNastaveni->ted()),
                )
            );
        }

        $platnostZpetneKDatu ??= $ted->modify('-1 day');
        if ($nejblizsiVlnaKdy < $platnostZpetneKDatu) {
            $rozdil                = $ted->diff($platnostZpetneKDatu);
            $naposledySloPustitKdy = DateTimeCz::createFromInterface($nejblizsiVlnaKdy)->sub($rozdil);
            throw new NaHromadnouAutomatickouAktivaciJePozde(
                sprintf(
                    "Hromadná aktivace může být spuštěna nanejvýš den po platnosti.
Platnost současné vlny hromadné aktivace byla '%s' (%s), teď je '%s' a aktivaci současné vlny šlo pustit naposledy '%s' (%s)",
                    $nejblizsiVlnaKdy->format(DateTimeCz::FORMAT_DB),
                    $nejblizsiVlnaKdy->relativni(),
                    $ted->format(DateTimeCz::FORMAT_DB),
                    $naposledySloPustitKdy->format(DateTimeCz::FORMAT_DB),
                    $naposledySloPustitKdy->relativni(),
                )
            );
        }

        $automatickyAktivovanoCelkem = $this->hromadneAktivovat(
            \Uzivatel::zId(\Uzivatel::SYSTEM, true),
            $this->sestavNazevAkceHromadneAktivace($nejblizsiVlnaKdy),
            $this->systemoveNastaveni->rocnik(),
        );

        $this->automatickyAktivovanoCelkem = $automatickyAktivovanoCelkem;

        return $automatickyAktivovanoCelkem;
    }

    public function automatickyAktivovanoCelkem(): int
    {
        return $this->automatickyAktivovanoCelkem;
    }

    public function hromadneAktivovatRucne(\Uzivatel $aktivujici, int $rocnik = null): int
    {
        $rocnik ??= $this->systemoveNastaveni->rocnik();
        return $this->hromadneAktivovat($aktivujici, $this->nazevAkceHromadneRucniAktivace(), $rocnik);
    }

    private function hromadneAktivovat(\Uzivatel $aktivujici, string $nazevAkce, int $rocnik)
    {
        $zeStavu = StavAktivity::PRIPRAVENA;
        $doStavu = StavAktivity::AKTIVOVANA;
        dbBegin();
        dbQuery(<<<SQL
            INSERT INTO akce_stavy_log(id_akce, id_stav, kdy)
            SELECT id_akce, $doStavu, NOW()
            FROM akce_seznam WHERE stav = $zeStavu AND rok = $rocnik
            SQL,
        );
        $result                    = dbQuery(<<<SQL
            UPDATE akce_seznam SET stav = $doStavu WHERE stav = $zeStavu AND rok = $rocnik
            SQL,
        );
        $hromadneAktivovanoAktivit = (int)dbAffectedOrNumRows($result);
        dbCommit();

        $this->zalogujHromadnouAkci(
            self::SKUPINA_AKTIVITY,
            $nazevAkce,
            $hromadneAktivovanoAktivit,
            $aktivujici,
        );

        return $hromadneAktivovanoAktivit;
    }

    private function nazevAkceHromadneRucniAktivace(): string
    {
        return 'rucni-aktivace';
    }

    /**
     * Odemče hromadně zamčené aktivity a odhlásí ty, kteří nesestavili teamy.
     * Vrací počet odemčených teamů (=>uvolněných míst)
     */
    public function odemciTeamoveHromadne(\Uzivatel $odemykajici): int
    {
        $odemcenoTymovychAktivit = 0;

        $zamcene = dbFetchAll('SELECT id_akce, zamcel FROM akce_seznam WHERE zamcel AND zamcel_cas < NOW() - INTERVAL ' . Aktivita::HAJENI_TEAMU_HODIN . ' HOUR');
        foreach ($zamcene as [Sql::ID_AKCE => $aid, Sql::ZAMCEL => $uid]) {
            // uvolnění zámku je součástí odhlášení, pokud je sám -> done
            Aktivita::zId($aid)->odhlas(\Uzivatel::zId($uid), $odemykajici, 'hromadne-odemceni-teamovych');
            $odemcenoTymovychAktivit++;
        }
        if ($odemcenoTymovychAktivit > 0) {
            $this->zalogujHromadnouAkci(
                self::SKUPINA_AKTIVITY,
                $this->nazevAkceHromadnehoOdemceniTeamovych(),
                $odemcenoTymovychAktivit,
                \Uzivatel::zId(\Uzivatel::SYSTEM, true),
            );
        }

        return $odemcenoTymovychAktivit;
    }

    private function nazevAkceHromadnehoOdemceniTeamovych(): string
    {
        return 'odemceni-tymovych';
    }

    public function automatickaAktivaceProvedenaKdy(\DateTimeInterface $vlnaKdy = null): ?\DateTimeInterface
    {
        $vlnaKdy   ??= $this->systemoveNastaveni->nejblizsiVlnaKdy();
        $nazevAkce = $this->sestavNazevAkceHromadneAktivace($vlnaKdy);

        return $this->posledniHromadnaAkceKdy(self::SKUPINA_AKTIVITY, $nazevAkce);
    }

    private function sestavNazevAkceHromadneAktivace(\DateTimeInterface $vlnaKdy): string
    {
        return 'aktivace-' . $vlnaKdy->format(DateTimeCz::FORMAT_CAS_SOUBOR);
    }
}
